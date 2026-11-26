<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\IncidentPhoto;
use App\Models\IncidentUpdate;
use App\Services\Audit\AuditLogger;
use App\Support\CommandScope;
use App\Support\NigerianPhone;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The command centre's incident desk: reports from members and agents as they come in,
 * with photos, the reporter's number, the place, and the follow-ups. Super admins see the whole
 * site; a state coordinator sees their own state's incidents.
 */
class IncidentDeskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['filter' => ['nullable', Rule::in(['open', 'all'])]]);
        $state = CommandScope::state($request->user());
        $scoped = Incident::query()->when($state, fn (Builder $q) => $q->where('state_id', $state->id));

        $incidents = (clone $scoped)
            ->when(($data['filter'] ?? 'open') === 'open', fn (Builder $q) => $q->whereIn('status', ['new', 'seen']))
            ->with('reporter:id,name', 'lga:id,name', 'ward:id,name', 'pollingUnit:id,code,name')
            ->withCount(['photos', 'updates'])
            // Unhandled first, then the most urgent, then the newest.
            ->orderByRaw("CASE status WHEN 'new' THEN 0 WHEN 'seen' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE urgency WHEN 'critical' THEN 0 WHEN 'serious' THEN 1 ELSE 2 END")
            ->latest('updated_at')
            ->limit(60)->get();

        $today = CarbonImmutable::now(config('membership.timezone'))->startOfDay()->utc();

        return response()->json([
            'incidents' => $incidents->map(fn (Incident $i) => $this->summary($i))->values(),
            'counts' => [
                'new' => (clone $scoped)->where('status', 'new')->count(),
                'open' => (clone $scoped)->whereIn('status', ['new', 'seen'])->count(),
                'critical' => (clone $scoped)->whereIn('status', ['new', 'seen'])->where('urgency', 'critical')->count(),
                'today' => (clone $scoped)->where('created_at', '>=', $today)->count(),
            ],
        ])->setPrivate();
    }

    public function show(Request $request, Incident $incident, AuditLogger $audit): JsonResponse
    {
        $this->inScope($incident);
        $incident->load('reporter.memberProfile', 'reporter.lga', 'reporter.ward', 'lga', 'ward', 'pollingUnit', 'handler:id,name', 'photos', 'updates.user:id,name', 'updates.photos');

        // Opening a new report tells the reporter it has been seen.
        if ($incident->status === 'new') {
            $incident->forceFill(['status' => 'seen', 'seen_at' => now(), 'handled_by' => $request->user()->id])->save();
        }
        $audit->record($request, AuditLogger::INCIDENT_VIEWED, $incident);

        $tz = config('membership.timezone');
        $reporter = $incident->reporter;
        $profile = $reporter->memberProfile;

        return response()->json($this->summary($incident) + [
            'description' => $incident->description,
            'photos' => $incident->photos->whereNull('incident_update_id')->map(fn (IncidentPhoto $p) => route('incidents.photos.show', $p))->values(),
            'location' => $incident->latitude !== null ? ['lat' => $incident->latitude, 'lng' => $incident->longitude, 'accuracy' => $incident->accuracy] : null,
            'reporter' => [
                'id' => $reporter->id,
                'name' => $reporter->name,
                'role' => match (true) {
                    $reporter->role === Role::Coordinator => 'Coordinator',
                    $profile?->canvasser_status?->value === 'approved' => site_term('Canvasser'),
                    default => 'Member',
                },
                'member_number' => $profile?->member_number,
                'home' => collect([$reporter->ward?->name, $reporter->lga?->name])->filter()->implode(', ') ?: null,
                'phone' => $reporter->phone,
                'phone_display' => NigerianPhone::format($reporter->phone),
                'whatsapp' => ltrim((string) ($profile?->whatsapp_phone ?: $reporter->phone), '+'),
                'profile' => $reporter->role === Role::Member ? staff_route('members.show', $reporter->id) : null,
            ],
            'handler' => $incident->handler?->name,
            'timeline' => $incident->updates->map(fn (IncidentUpdate $u) => [
                'id' => $u->id,
                'body' => $u->body,
                'by' => $u->user?->name,
                'from_command' => $u->from_command,
                'time' => $u->created_at->setTimezone($tz)->format('j M, H:i'),
                'photos' => $u->photos->map(fn (IncidentPhoto $p) => route('incidents.photos.show', $p))->values(),
            ])->values(),
        ])->setPrivate();
    }

    /** Resolve, close as not real, reopen, or add a note. Each one is kept on the timeline. */
    public function update(Request $request, Incident $incident, AuditLogger $audit): JsonResponse
    {
        $this->inScope($incident);
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['seen', 'resolved', 'dismissed'])],
            'note' => ['nullable', 'string', 'max:1000', 'required_without:status'],
        ]);

        $lines = [];
        if (isset($data['status']) && $data['status'] !== $incident->status) {
            $incident->forceFill([
                'status' => $data['status'],
                'handled_by' => $request->user()->id,
                'seen_at' => $incident->seen_at ?? now(),
                'closed_at' => in_array($data['status'], ['resolved', 'dismissed'], true) ? now() : null,
            ])->save();
            $lines[] = ['seen' => 'Reopened.', 'resolved' => 'Marked resolved.', 'dismissed' => 'Closed: not a real incident.'][$data['status']];
        }
        if (filled($data['note'] ?? null)) {
            $lines[] = trim($data['note']);
        }
        if ($lines) {
            $incident->updates()->create(['user_id' => $request->user()->id, 'body' => implode(' ', $lines), 'from_command' => true]);
            $incident->touch();
        }
        $audit->record($request, AuditLogger::INCIDENT_UPDATED, $incident, array_filter(['status' => $data['status'] ?? null]));

        return response()->json(['ok' => true]);
    }

    /** @return array<string, mixed> */
    private function summary(Incident $i): array
    {
        $tz = config('membership.timezone');

        return [
            'id' => $i->id,
            'category' => $i->category->value,
            'category_label' => $i->category->label(),
            'urgency' => $i->urgency->value,
            'urgency_label' => $i->urgency->short(),
            'status' => $i->status,
            'status_label' => Incident::STATUSES[$i->status] ?? $i->status,
            'excerpt' => mb_strimwidth($i->description, 0, 140, '…'),
            'place' => $i->place() ?: 'Place not given',
            'lga_id' => $i->lga_id,
            'lat' => $i->latitude,
            'lng' => $i->longitude,
            'by' => $i->reporter?->name,
            'photo_count' => $i->photos_count ?? null,
            'update_count' => $i->updates_count ?? null,
            'time' => $i->created_at->setTimezone($tz)->format('H:i'),
            'day' => $i->created_at->setTimezone($tz)->isToday() ? null : $i->created_at->setTimezone($tz)->format('j M'),
            'updated' => $i->updated_at->toIso8601ZuluString(),
        ];
    }

    private function inScope(Incident $incident): void
    {
        $state = CommandScope::state(request()->user());
        abort_if($state && $incident->state_id !== $state->id, 404);
    }
}
