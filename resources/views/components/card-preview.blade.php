@props([
    'name' => 'Your name',
    'badge' => 'Member',
    'tone' => 'member',
    'state' => 'State',
    'ward' => 'LGA · Ward',
    'number' => 'RHYE-PENDING',
    'qr' => null,
    // Alpine expressions; when given, the values follow the sign-up form as it is filled in.
    'bind' => [],
])
@php
    $qr ??= \App\Support\Qr::svg(route('home'));
    $text = fn (string $key, string $value) => isset($bind[$key]) ? 'x-text="'.e($bind[$key]).'"' : '';
@endphp
<div {{ $attributes->merge(['class' => 'relative w-full max-w-[377px] overflow-hidden rounded-[22px] bg-white text-ink shadow-[0_20px_50px_-20px_rgba(0,0,0,0.55)]']) }}>
    {{-- The green edge, broken where the name sits, as in the design. --}}
    <span class="absolute top-0 bottom-0 left-0 w-2 bg-action" aria-hidden="true"></span>
    <span class="absolute top-[30%] left-0 h-[32%] w-2 bg-white" aria-hidden="true"></span>

    <div class="py-5 pr-5 pl-9">
        <div class="flex items-start justify-between gap-3">
            <img src="{{ asset('images/logo-rhye.png') }}" alt="RHYE" class="h-9 w-auto" width="600" height="272">
            <p class="pt-2 text-[10.5px] font-medium tracking-[0.18em] text-neutral-600 uppercase">Membership card</p>
        </div>

        <p class="mt-5 truncate font-headline text-[27px] leading-tight font-bold tracking-[-0.01em]" {!! $text('name', $name) !!}>{{ $name }}</p>

        @if (isset($bind['tone']))
            <p class="mt-2 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[13px] font-semibold"
               x-bind:class="{{ $bind['tone'] }} === 'canvasser' ? 'bg-amber-soft text-amber-ink' : 'bg-[#e6f2e8] text-action-dark'">
                <svg x-show="{{ $bind['tone'] }} !== 'canvasser'" class="h-3 w-3" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M2 6.5 4.6 9 10 3.5"/></svg>
                <svg x-show="{{ $bind['tone'] }} === 'canvasser'" class="h-3 w-3" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><circle cx="6" cy="6" r="4.8"/><path d="M6 3.5V6l1.6 1"/></svg>
                <span {!! $text('badge', $badge) !!}>{{ $badge }}</span>
            </p>
        @else
            <p @class(['mt-2 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[13px] font-semibold', 'bg-amber-soft text-amber-ink' => $tone === 'canvasser', 'bg-[#e6f2e8] text-action-dark' => $tone !== 'canvasser'])>
                @if ($tone === 'canvasser')
                    <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><circle cx="6" cy="6" r="4.8"/><path d="M6 3.5V6l1.6 1"/></svg>
                @else
                    <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M2 6.5 4.6 9 10 3.5"/></svg>
                @endif
                {{ $badge }}
            </p>
        @endif

        <div class="mt-5 flex items-end justify-between gap-3">
            <dl class="grid min-w-0 flex-1 grid-cols-[auto_1fr] gap-x-7 gap-y-4 text-[14.5px]">
                <div class="min-w-0">
                    <dt class="text-[11px] font-medium tracking-[0.12em] text-neutral-600 uppercase">State</dt>
                    <dd class="mt-0.5 truncate font-medium" {!! $text('state', $state) !!}>{{ $state }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-[11px] font-medium tracking-[0.12em] text-neutral-600 uppercase">Ward</dt>
                    <dd class="mt-0.5 line-clamp-2 font-medium break-words" {!! $text('ward', $ward) !!}>{{ $ward }}</dd>
                </div>
                <div class="col-span-2 min-w-0">
                    <dt class="text-[11px] font-medium tracking-[0.12em] text-neutral-600 uppercase">Member no.</dt>
                    <dd class="mt-0.5 font-mono text-[15px] font-bold tracking-wide" {!! $text('number', $number) !!}>{{ $number }}</dd>
                </div>
            </dl>
            <div data-qr class="h-[88px] w-[88px] shrink-0 rounded-xl border border-line bg-white p-1.5 [&>svg]:h-full [&>svg]:w-full">{!! $qr !!}</div>
        </div>
    </div>
</div>
