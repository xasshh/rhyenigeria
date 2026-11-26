<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VoiceCall;
use App\Services\Audit\AuditLogger;
use App\Services\Voice\AfricasTalkingVoice;
use App\Support\CommandScope;
use App\Support\NigerianPhone;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * The command centre's phone line. Placing a call rings the operator's phone first and,
 * when they answer, connects the contact (so every call goes out from the campaign number,
 * is recorded and is logged). Calls into the line show up here as they ring.
 * Until the line is connected, calls from operators' own phones and calls into an
 * ordinary hotline are logged here by hand, so follow-ups are not lost.
 */
class PhoneLineController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tz = config('membership.timezone');
        $calls = $this->visible($request)->with('contact:id,name,role,lga_id,ward_id', 'contact.lga:id,name', 'contact.ward:id,name', 'operator:id,name')
            ->latest('id')->limit(25)->get();

        return response()->json([
            'calls' => $calls->map(fn (VoiceCall $c) => [
                'id' => $c->id,
                'direction' => $c->direction,
                'channel' => $c->channel,
                'note' => $c->note,
                'status' => $c->status,
                'live' => $c->isLive(),
                'needs_call_back' => $c->needsCallBack(),
                'name' => $c->contact?->name,
                'area' => $c->contact ? collect([$c->contact->ward?->name, $c->contact->lga?->name])->filter()->implode(', ') : null,
                'phone' => $c->contact_phone,
                'phone_display' => NigerianPhone::format($c->contact_phone),
                'operator' => $c->operator?->name,
                'duration' => $c->duration_seconds,
                'recording' => $c->recording_url,
                'handled_by' => $c->handled_at ? 'Handled' : null,
                'time' => CarbonImmutable::parse($c->created_at)->setTimezone($tz)->format('H:i'),
                'day' => CarbonImmutable::parse($c->created_at)->setTimezone($tz)->isToday() ? null : CarbonImmutable::parse($c->created_at)->setTimezone($tz)->format('j M'),
            ])->values(),
            'waiting' => $this->visible($request)->whereIn('status', VoiceCall::FOLLOW_UP)->whereNull('handled_at')->count(),
        ])->setPrivate();
    }

    public function store(Request $request, AfricasTalkingVoice $voice, AuditLogger $audit): JsonResponse
    {
        if (! $voice->isConfigured()) {
            return response()->json(['message' => 'The phone line is not set up yet. Use your own phone for now.'], 503);
        }

        $desk = $voice->deskPhone();
        if ($desk === null) {
            return response()->json(['message' => 'No command centre phone is set up yet.'], 503);
        }

        $data = $request->validate([
            'user_id' => ['required_without:call_id', 'nullable', 'integer'],
            'call_id' => ['required_without:user_id', 'nullable', 'integer'],
        ]);

        [$phone, $contactId] = isset($data['user_id']) ? $this->contact($request, (int) $data['user_id']) : $this->caller($request, (int) $data['call_id']);

        try {
            $sessionId = $voice->call($desk);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $call = VoiceCall::create([
            'session_id' => $sessionId,
            'direction' => VoiceCall::OUTBOUND,
            'status' => 'ringing',
            'contact_phone' => $phone,
            'contact_user_id' => $contactId,
            'operator_id' => $request->user()->id,
            'operator_phone' => $desk,
        ]);

        $this->resolve($request, $data['call_id'] ?? null);

        $audit->record($request, AuditLogger::COMMAND_CALL_PLACED, $call, ['to' => NigerianPhone::mask($phone)]);

        return response()->json(['message' => 'The command centre phone ('.NigerianPhone::format($desk).') is ringing — pick it up and you will be connected.', 'id' => $call->id]);
    }

    /** The operator is about to call from their own phone (the call desk's tel: link): log it. */
    public function direct(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required_without:call_id', 'nullable', 'integer'],
            'call_id' => ['required_without:user_id', 'nullable', 'integer'],
        ]);
        [$phone, $contactId] = isset($data['user_id']) ? $this->contact($request, (int) $data['user_id']) : $this->caller($request, (int) $data['call_id']);

        $call = VoiceCall::create([
            'direction' => VoiceCall::OUTBOUND,
            'channel' => VoiceCall::PHONE,
            'status' => 'dialled',
            'contact_phone' => $phone,
            'contact_user_id' => $contactId,
            'operator_id' => $request->user()->id,
        ]);
        $this->resolve($request, $data['call_id'] ?? null);
        $audit->record($request, AuditLogger::COMMAND_CALL_PLACED, $call, ['to' => NigerianPhone::mask($phone), 'channel' => VoiceCall::PHONE]);

        return response()->json(['id' => $call->id]);
    }

    /** How an own-phone call went. */
    public function outcome(Request $request, VoiceCall $call): JsonResponse
    {
        abort_unless($call->channel === VoiceCall::PHONE && $call->direction === VoiceCall::OUTBOUND && $this->canSee($request, $call), 404);
        $data = $request->validate([
            'outcome' => ['required', Rule::in(array_keys(VoiceCall::OUTCOMES))],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $call->forceFill([
            'status' => VoiceCall::OUTCOMES[$data['outcome']],
            'note' => $data['note'] ?? $call->note,
            'ended_at' => $call->ended_at ?? now(),
        ])->save();

        return response()->json(['ok' => true]);
    }

    /** Someone answered (or missed) a call on an ordinary hotline phone and types it in. */
    public function incoming(Request $request): JsonResponse
    {
        $request->merge(['phone' => NigerianPhone::normalise($request->input('phone')) ?? $request->input('phone')]);
        $data = $request->validate([
            'phone' => ['required', 'regex:/^\+234[789][01]\d{8}$/'],
            'answered' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['phone.regex' => 'Enter the caller\'s Nigerian mobile number.']);

        $call = VoiceCall::create([
            'direction' => VoiceCall::INBOUND,
            'channel' => VoiceCall::PHONE,
            'status' => $data['answered'] ? 'completed' : 'missed',
            'contact_phone' => $data['phone'],
            'contact_user_id' => User::where('phone', $data['phone'])->value('id'),
            'operator_id' => $request->user()->id,
            'note' => $data['note'] ?? null,
        ]);

        return response()->json(['id' => $call->id, 'name' => $call->contact?->name]);
    }

    public function handled(Request $request, VoiceCall $call): JsonResponse
    {
        abort_unless($this->canSee($request, $call), 404);
        $call->forceFill(['handled_at' => now(), 'handled_by' => $request->user()->id])->save();

        return response()->json(['ok' => true]);
    }

    /** Calling someone back deals with the call that needed it. */
    private function resolve(Request $request, ?int $callId): void
    {
        if ($callId) {
            $this->visible($request)->whereKey($callId)->whereNull('handled_at')->update(['handled_at' => now(), 'handled_by' => $request->user()->id]);
        }
    }

    /** @return array{0: string, 1: int} Someone the call desk lists (same scope as the contacts search). */
    private function contact(Request $request, int $id): array
    {
        $state = CommandScope::state($request->user());
        $user = User::query()->whereKey($id)->where('is_active', true)->whereNotNull('phone')
            ->whereIn('role', [Role::Coordinator->value, Role::Member->value])
            ->when($state, fn ($q) => $q->where('state_id', $state->id))
            ->first();

        if (! $user) {
            throw ValidationException::withMessages(['user_id' => 'That person cannot be called from here.']);
        }

        return [$user->phone, $user->id];
    }

    /** @return array{0: string, 1: ?int} The other person on an earlier call. */
    private function caller(Request $request, int $id): array
    {
        $call = $this->visible($request)->find($id);
        if (! $call || ! str_starts_with($call->contact_phone, '+')) {
            throw ValidationException::withMessages(['call_id' => 'That caller cannot be called back.']);
        }

        return [$call->contact_phone, $call->contact_user_id];
    }

    /**
     * The calls this viewer may see. Super admins: all of them. A state coordinator: calls they made or
     * logged, and calls with people from their state (unknown callers into the line stay with super admins).
     */
    private function visible(Request $request): Builder
    {
        $viewer = $request->user();
        if ($viewer->isSuperAdmin()) {
            return VoiceCall::query();
        }

        return VoiceCall::query()->where(fn (Builder $q) => $q
            ->where('operator_id', $viewer->id)
            ->orWhereIn('contact_user_id', User::select('id')->where('state_id', $viewer->state_id)));
    }

    private function canSee(Request $request, VoiceCall $call): bool
    {
        return $this->visible($request)->whereKey($call->id)->exists();
    }
}
