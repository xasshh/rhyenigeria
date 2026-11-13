@props(['user', 'profile', 'qrSvg'])
@php
    $place = array_values(array_filter([
        trim(collect([$user->ward?->name, $user->lga?->name])->filter()->implode(', ')),
        $user->state ? $user->state->name.' State' : null,
    ]));
    $joined = $profile->created_at->setTimezone(config('membership.timezone'))->format('j M Y');
@endphp
<div x-data="memberCard(@js(['name' => $user->name, 'number' => $profile->member_number, 'place' => $place, 'joined' => $joined, 'role' => $profile->roleLabel(), 'logo' => asset('images/logo-renewed-hope.png'), 'slogan' => config('membership.slogan')]))" {{ $attributes }}>
    <div class="relative mx-auto aspect-[1.586] w-full max-w-[400px] overflow-hidden rounded-[22px] bg-gradient-to-br from-brand-dark to-brand p-[5%] text-white shadow-xl shadow-brand/25">
        <div class="pointer-events-none absolute -top-16 -right-14 h-48 w-48 rounded-full bg-white/[0.07]"></div>
        <div class="pointer-events-none absolute -bottom-20 -left-6 h-44 w-44 rounded-full bg-accent/20"></div>
        <div class="absolute inset-x-0 bottom-0 h-1.5 bg-accent"></div>

        <div class="relative flex h-full flex-col">
            <div class="flex items-start justify-between gap-2">
                <span class="rounded-lg bg-white px-2 py-1"><img src="{{ asset('images/logo-renewed-hope.png') }}" alt="Renewed Hope" class="h-[clamp(20px,6vw,28px)] w-auto" width="285" height="150"></span>
                <p class="text-right text-[clamp(7px,2.1vw,9px)] leading-tight font-semibold tracking-[0.14em] text-white/80 uppercase">Membership card<br>Renewed Hope Youth Engagement</p>
            </div>

            <div class="mt-auto flex items-end justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[clamp(7px,2.1vw,9px)] font-semibold tracking-[0.14em] text-white/70 uppercase">Member</p>
                    <p class="truncate text-[clamp(14px,4.4vw,19px)] leading-tight font-bold">{{ $user->name }}</p>
                    <p class="mt-1.5 text-[clamp(7px,2.1vw,9px)] font-semibold tracking-[0.14em] text-white/70 uppercase">Member no.</p>
                    <p class="font-mono text-[clamp(12px,3.6vw,15px)] font-bold tracking-wide text-accent">{{ $profile->member_number }}</p>
                    <p class="mt-1.5 text-[clamp(9px,2.6vw,11px)] leading-snug text-white/90">
                        @foreach ($place as $line)<span class="block truncate">{{ $line }}</span>@endforeach
                    </p>
                </div>
                <div class="shrink-0 rounded-xl bg-white p-1.5" data-qr>
                    <div class="h-[clamp(64px,22vw,96px)] w-[clamp(64px,22vw,96px)] [&>svg]:h-full [&>svg]:w-full">{!! $qrSvg !!}</div>
                </div>
            </div>

            <div class="mt-2.5 flex items-center justify-between border-t border-white/25 pt-2 text-[clamp(9px,2.6vw,11px)]">
                <span class="text-white/80">Joined {{ $joined }}</span>
                <span class="hidden truncate px-2 font-semibold text-accent italic min-[380px]:inline">{{ config('membership.slogan') }}</span>
                <span class="font-bold">{{ $profile->roleLabel() }}</span>
            </div>
        </div>
    </div>

    <div class="mx-auto mt-3 flex max-w-[400px] justify-center">
        <button type="button" class="btn-secondary btn-sm" x-on:click="download" x-bind:disabled="busy">
            <x-icon name="download" class="h-4 w-4" />
            <span x-text="busy ? 'Preparing…' : 'Download as image'">Download as image</span>
        </button>
    </div>
</div>
