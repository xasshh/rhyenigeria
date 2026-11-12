@php $tz = config('membership.timezone'); @endphp
<div class="mt-2 flex flex-wrap gap-1.5">
    @foreach ($p->canvasser_roles ?? [] as $role)
        <span class="badge bg-surface text-neutral-700">{{ \App\Enums\CanvasserRole::tryFrom($role)?->label() ?? $role }}</span>
    @endforeach
</div>
<p class="mt-2 text-sm text-neutral-600">Applied {{ $p->canvasser_applied_at?->setTimezone($tz)->format('j M Y') }}
    @if ($p->canvasser_reviewed_at)
        · {{ $p->canvasser_status->label() }} by {{ $p->reviewer?->name ?? 'a coordinator' }} on {{ $p->canvasser_reviewed_at->setTimezone($tz)->format('j M Y') }}
    @endif
</p>
@if ($p->canvasser_review_note)<p class="mt-1 rounded-lg bg-surface px-3 py-2 text-sm">“{{ $p->canvasser_review_note }}”</p>@endif

@if ($canReview)
    <form method="POST" action="{{ staff_route('canvassers.decide', $m) }}" class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-start">
        @csrf
        <input name="note" class="field py-2 text-sm sm:flex-1" maxlength="1000" placeholder="Note (optional)">
        <div class="flex gap-2">
            @if ($p->canvasser_status->value !== 'approved')
                <button name="decision" value="approve" class="btn-primary btn-sm flex-1">Approve</button>
            @endif
            @if ($p->canvasser_status->value !== 'rejected')
                <button name="decision" value="reject" class="btn-secondary btn-sm flex-1 text-red-700">{{ $p->canvasser_status->value === 'approved' ? 'Withdraw' : 'Reject' }}</button>
            @endif
        </div>
    </form>
@endif
