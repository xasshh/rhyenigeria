@php use App\Support\NigerianPhone; @endphp
<x-layouts.app title="Canvassers" heading="Canvasser applications">
    @unless ($canReview)
        <p class="mt-3 rounded-xl bg-surface px-4 py-3 text-sm text-neutral-600">Applications are approved by the state coordinator. You can see them here but not decide.</p>
    @endunless

    <div class="mt-4 inline-flex rounded-xl border border-line bg-white p-1 text-sm font-medium">
        @foreach (\App\Enums\CanvasserStatus::cases() as $s)
            <a href="{{ staff_route('canvassers.index', ['status' => $s->value]) }}" @class(['rounded-lg px-3 py-1.5 sm:px-4', 'bg-brand text-white' => $status === $s, 'text-neutral-600' => $status !== $s])>
                {{ $s->label() }} <span class="tabular-nums opacity-75">{{ number_format($counts['canvassers_'.$s->value]) }}</span>
            </a>
        @endforeach
    </div>

    <div class="mt-4 space-y-3">
        @forelse ($applications as $m)
            @php $p = $m->memberProfile; @endphp
            <article class="panel p-4 sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <a href="{{ staff_route('members.show', $m) }}" class="font-display font-bold text-brand-dark hover:underline">{{ $m->name }}</a>
                        <p class="text-sm text-neutral-600">{{ collect([$m->ward?->name, $m->lga?->name, auth()->user()->isSuperAdmin() ? $m->state?->name : null])->filter()->implode(', ') }}</p>
                        <p class="text-xs text-neutral-500"><span class="font-mono">{{ $p->member_number }}</span> · WhatsApp {{ NigerianPhone::mask($p->whatsapp_phone ?? $m->phone) }}</p>
                    </div>
                </div>
                @include('staff.partials.canvasser-review', ['m' => $m, 'p' => $p, 'canReview' => $canReview])
            </article>
        @empty
            <p class="panel p-8 text-center text-neutral-500">No {{ strtolower($status->label()) }} applications.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $applications->links() }}</div>
</x-layouts.app>
