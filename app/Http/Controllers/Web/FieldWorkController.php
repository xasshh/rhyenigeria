<?php

namespace App\Http\Controllers\Web;

use App\Enums\DoorOutcome;
use App\Enums\FieldActivityKind;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\FieldActivity;
use App\Services\Tracking\LocationIngestor;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

/**
 * /membership/field: location sharing and activity logging for approved canvassers.
 *
 * Location is only ever recorded between a Start shift and End shift that the
 * canvasser presses themselves, after they have accepted the location notice.
 * Nobody else can switch it on.
 */
class FieldWorkController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->load('memberProfile', 'state');
        abort_unless($user->isApprovedCanvasser(), 403, 'Field work is for approved canvassers.');

        $shift = $user->activeShift()->first();

        return view('membership.field', [
            'user' => $user,
            'consented' => $user->hasAcceptedTrackingConsent() && $user->tracking_consent_version === (string) config('tracking.consent_version'),
            'shift' => $shift,
            'events' => Event::query()->forState($user->state_id)->where('starts_at', '>=', now()->subHours(12))->orderBy('starts_at')->limit(20)->get(['id', 'title', 'starts_at', 'venue']),
            'today' => FieldActivity::query()->where('user_id', $user->id)->where('recorded_at', '>=', now()->startOfDay())
                ->with('event:id,title')->latest('recorded_at')->limit(30)->get(),
            'outcomes' => DoorOutcome::cases(),
        ]);
    }

    public function acceptConsent(Request $request): RedirectResponse
    {
        $user = $this->canvasser($request);
        $request->validate(['accepted' => ['accepted']], ['accepted.accepted' => 'Tick the box to agree before you start.']);

        $user->tracking_consent_accepted_at = now();
        $user->tracking_consent_version = (string) config('tracking.consent_version');
        $user->save();

        return back()->with('status', 'Thank you. You can start a shift whenever you begin work.');
    }

    /** Withdrawing also ends a shift in progress, so tracking stops at once. */
    public function withdrawConsent(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->shifts()->whereNull('ended_at')->update(['ended_at' => now()]);
        $user->tracking_consent_accepted_at = null;
        $user->tracking_consent_version = null;
        $user->save();

        return back()->with('status', 'Location sharing is off and your consent has been withdrawn.');
    }

    public function startShift(Request $request): JsonResponse
    {
        $user = $this->canvasser($request);
        abort_unless($user->hasAcceptedTrackingConsent(), 403, 'Agree to the location notice before starting a shift.');

        if ($existing = $user->activeShift()->first()) {
            return response()->json(['shift' => ShiftController::present($existing)]);
        }

        try {
            $shift = DB::transaction(fn () => $user->shifts()->create(['started_at' => now()]));
        } catch (UniqueConstraintViolationException) {
            $shift = $user->activeShift()->first();
        }

        return response()->json(['shift' => ShiftController::present($shift)], 201);
    }

    public function endShift(Request $request): JsonResponse
    {
        $shift = $request->user()->activeShift()->first();
        $shift?->update(['ended_at' => now()]);

        return response()->json(['shift' => $shift ? ShiftController::present($shift) : null]);
    }

    /** A batch of GPS points from the canvasser's own phone. Same rules as the field-agent API. */
    public function locations(Request $request, LocationIngestor $ingestor): JsonResponse
    {
        $user = $this->canvasser($request);
        abort_unless($user->hasAcceptedTrackingConsent(), 403, 'Location sharing is off.');

        $data = $request->validate([
            'points' => ['required', 'array', 'min:1', 'max:'.(int) config('tracking.max_batch_size')],
            'points.*.uuid' => ['required', 'uuid'],
            'points.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'points.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'points.*.accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'points.*.recorded_at' => ['required', 'date'],
        ]);

        $result = $ingestor->ingest($user, $data['points']);

        return response()->json($result + ['shift_active' => $user->activeShift()->exists()]);
    }

    public function storeActivity(Request $request): JsonResponse
    {
        $user = $this->canvasser($request);
        $shift = $user->activeShift()->first();
        abort_if($shift === null, 409, 'Start a shift before logging activities.');

        $data = $request->validate([
            'uuid' => ['required', 'uuid'],
            'kind' => ['required', new Enum(FieldActivityKind::class)],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'households' => ['nullable', 'required_if:kind,door_to_door', 'integer', 'min:1', 'max:500'],
            'outcome' => ['nullable', 'required_if:kind,door_to_door', new Enum(DoorOutcome::class)],
            'event_id' => ['nullable', 'required_if:kind,event_check_in', 'integer',
                Rule::exists('events', 'id')->where(fn ($q) => $q->whereNull('state_id')->orWhere('state_id', $user->state_id))],
            'note' => ['nullable', 'required_if:kind,report', 'string', 'max:2000'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:'.config('membership.empowerment_photo_max_kb')],
        ], [
            'latitude.required' => 'We could not get your location. Turn on location and try again.',
            'households.required_if' => 'How many households did you reach?',
            'outcome.required_if' => 'Choose how the visit went.',
            'event_id.required_if' => 'Choose the event you are at.',
            'note.required_if' => 'Write a short note about what you did.',
        ]);

        if ($existing = FieldActivity::where('uuid', strtolower($data['uuid']))->where('user_id', $user->id)->first()) {
            return response()->json(['activity' => $this->present($existing), 'duplicate' => true]);
        }

        $activity = new FieldActivity([
            'uuid' => strtolower($data['uuid']),
            'kind' => $data['kind'],
            'households' => $data['kind'] === FieldActivityKind::DoorToDoor->value ? $data['households'] : null,
            'outcome' => $data['kind'] === FieldActivityKind::DoorToDoor->value ? $data['outcome'] : null,
            'event_id' => $data['kind'] === FieldActivityKind::EventCheckIn->value ? $data['event_id'] : null,
            'note' => $data['note'] ?? null,
            'accuracy' => $data['accuracy'] ?? null,
            'recorded_at' => now(),
        ]);
        $activity->user_id = $user->id;
        $activity->shift_id = $shift->id;
        $activity->state_id = $user->state_id;
        $activity->location = FieldActivity::pointExpression((float) $data['latitude'], (float) $data['longitude']);
        if ($request->hasFile('photo')) {
            $activity->photo_path = $request->file('photo')->store('field/'.$user->id, 'local');
        }
        $activity->save();

        // The ward the activity happened in, when ward boundaries are loaded.
        DB::update('UPDATE field_activities fa SET ward_id = (SELECT w.id FROM wards w WHERE ST_Covers(w.boundary, fa.location) ORDER BY w.id LIMIT 1) WHERE fa.id = ?', [$activity->id]);

        return response()->json(['activity' => $this->present($activity->fresh('event:id,title'))], 201);
    }

    /** An activity photo: the canvasser themselves, or staff who may see that member. */
    public function photo(Request $request, FieldActivity $activity)
    {
        $viewer = $request->user();
        abort_unless($activity->photo_path && ($viewer->id === $activity->user_id || $viewer->canSeeMember($activity->user)), 404);

        return response()->file(storage_path('app/private/'.$activity->photo_path), ['Cache-Control' => 'private, max-age=3600']);
    }

    private function canvasser(Request $request)
    {
        $user = $request->user()->loadMissing('memberProfile');
        abort_unless($user->isApprovedCanvasser(), 403, 'Field work is for approved canvassers.');

        return $user;
    }

    /** @return array<string, mixed> */
    private function present(FieldActivity $activity): array
    {
        return [
            'uuid' => $activity->uuid,
            'kind' => $activity->kind->value,
            'label' => $activity->kind->label(),
            'summary' => match ($activity->kind) {
                FieldActivityKind::DoorToDoor => $activity->households.' '.str('household')->plural($activity->households).' · '.$activity->outcome?->label(),
                FieldActivityKind::EventCheckIn => $activity->event?->title ?? 'Event',
                FieldActivityKind::Report => str((string) $activity->note)->limit(60)->toString(),
            },
            'time' => $activity->recorded_at->setTimezone(config('membership.timezone'))->format('g:ia'),
        ];
    }
}
