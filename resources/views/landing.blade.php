@php
    // Drop the real files into public/images and they are picked up automatically.
    $image = fn (string $name) => collect(['svg', 'png', 'webp', 'jpg'])
        ->map(fn ($ext) => "images/{$name}.{$ext}")
        ->first(fn ($path) => file_exists(public_path($path)));

    $rhyeLogo = $image('logo-rhye');
    $logo = $image('logo-renewed-hope');
    $partyLogo = $image('logo-apc');
    $slogan = config('membership.slogan');

    $links = ['Home' => '#top', 'Countdown' => '#countdown', 'Get Involved' => route('join'), 'Members map' => '#map', 'Contact' => '#contact'];
    $tz = config('membership.timezone');
    $localElection = $electionDate->setTimezone($tz);
    $units = ['weeks' => 'Weeks', 'days' => 'Days', 'hours' => 'Hours', 'minutes' => 'Minutes', 'seconds' => 'Seconds'];
    $pad = fn (int $n) => str_pad((string) $n, 2, '0', STR_PAD_LEFT);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f2a1c">
    <title>Renewed Hope Youth Engagement — {{ $slogan }}</title>
    <meta name="description" content="Renewed Hope Youth Engagement (RHYE): {{ $slogan }} Join the movement, get your digital membership card and count down to election day.">
    <meta property="og:title" content="Renewed Hope Youth Engagement — {{ $slogan }}">
    <meta property="og:description" content="Join the movement. Get your digital membership card and updates from your state.">
    @if ($slides->first()?->imageUrl())<meta property="og:image" content="{{ $slides->first()->imageUrl() }}">@endif
    @include('partials.head-icons')
    @fonts
    @vite(['resources/css/app.css', 'resources/js/site.js'])
</head>
<body id="top" class="bg-white font-display text-brand antialiased">
    {{-- Announcement bar --}}
    <div class="bg-forest px-4 py-3 text-center text-[13px] leading-snug text-white">
        <span class="font-semibold text-mint">{{ $slogan }}</span>
        <span class="mx-1.5 text-white/40" aria-hidden="true">·</span>
        Renewed Hope Youth Engagement
        <a href="{{ route('join') }}" class="ml-1 font-bold text-accent hover:underline">Support Us</a>
    </div>

    {{-- Navigation --}}
    <header class="mx-auto flex max-w-[1440px] items-center justify-between gap-6 px-5 py-4 sm:px-8 lg:px-[66px] lg:py-5">
        <a href="#top" class="flex shrink-0 items-center gap-4 sm:gap-9" aria-label="Renewed Hope Youth Engagement home">
            @if ($rhyeLogo)
                <img src="{{ asset($rhyeLogo) }}" alt="RHYE — Renewed Hope Youth Engagement" class="h-[40px] w-auto sm:h-[56px]" width="600" height="272">
                <span class="h-8 w-px bg-brand/20 sm:h-10" aria-hidden="true"></span>
            @endif
            @if ($logo)
                <img src="{{ asset($logo) }}" alt="Renewed Hope" class="h-[30px] w-auto sm:h-[37.5px]" width="285" height="150">
            @endif
            @if ($partyLogo)
                <img src="{{ asset($partyLogo) }}" alt="APC" class="h-[38px] w-auto sm:h-[45.5px]" width="200" height="182">
            @endif
        </a>

        <nav class="hidden items-center gap-7 text-sm xl:flex" aria-label="Main">
            @foreach ($links as $label => $href)
                <a href="{{ $href }}" @class(['transition-colors hover:text-brand', 'font-bold text-brand' => $loop->first, 'text-brand-soft' => ! $loop->first]) @if ($loop->first) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        <div class="hidden items-center gap-5 lg:flex">
            @auth
                <a href="{{ auth()->user()->homeRoute() }}" class="text-sm font-bold text-brand hover:underline">My dashboard</a>
            @else
                <a href="{{ route('login') }}" class="text-sm font-bold text-brand hover:underline">Sign in</a>
            @endauth
            <a href="{{ route('join') }}" class="rounded-[10px] bg-brand px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-brand-dark">Join the Movement</a>
        </div>

        {{-- Small screens: menu without JavaScript --}}
        <details class="group relative lg:hidden">
            <summary class="flex h-11 w-11 cursor-pointer list-none items-center justify-center rounded-lg border border-brand/40 [&::-webkit-details-marker]:hidden" aria-label="Menu">
                <svg class="h-5 w-5 group-open:hidden" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 5h14M3 10h14M3 15h14"/></svg>
                <svg class="hidden h-5 w-5 group-open:block" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4l12 12M16 4L4 16"/></svg>
            </summary>
            <nav class="absolute right-0 z-30 mt-2 flex w-60 flex-col gap-1 rounded-xl border border-neutral-200 bg-white p-3 text-base shadow-lg" aria-label="Main">
                @foreach ($links as $label => $href)
                    <a href="{{ $href }}" class="rounded-lg px-3 py-2.5 hover:bg-brand/10">{{ $label }}</a>
                @endforeach
                @auth
                    <a href="{{ auth()->user()->homeRoute() }}" class="rounded-lg px-3 py-2.5 font-bold hover:bg-brand/10">My dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="rounded-lg px-3 py-2.5 font-bold hover:bg-brand/10">Sign in</a>
                @endauth
                <a href="{{ route('join') }}" class="mt-1 rounded-lg bg-brand px-3 py-2.5 text-center font-bold text-white">Join the Movement</a>
            </nav>
        </details>
    </header>

    <main>
        {{-- Header slider --}}
        <section class="relative isolate overflow-hidden bg-forest text-white" aria-labelledby="slogan">
            <x-fingerprint class="absolute -top-28 -left-40 -z-10 w-[520px] text-white/[0.05]" />
            <x-fingerprint class="absolute -right-24 -bottom-48 -z-10 hidden w-[560px] text-mint/[0.07] lg:block" />

            <div class="mx-auto max-w-[1440px] px-5 pt-8 pb-8 sm:px-8 lg:px-[66px] lg:pt-12 lg:pb-12"
                 x-data="heroSlider({ count: {{ max($slides->count(), 1) }} })"
                 x-on:mouseenter="hovering = true" x-on:mouseleave="hovering = false"
                 x-on:focusin="focused = true" x-on:focusout="focused = $el.contains($event.relatedTarget)"
                 x-on:touchstart.passive="touchStart($event)" x-on:touchend.passive="touchEnd($event)"
                 x-on:keydown="key($event)"
                 role="region" aria-roledescription="carousel" aria-label="Highlights">

                <p class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                    <span class="font-script text-[24px] leading-none text-mint italic sm:text-[28px]">Youth for PBAT</span>
                    <span id="slogan" role="heading" aria-level="1" class="font-headline text-[17px] font-bold tracking-[0.01em] text-white/90 sm:text-[19px]">{{ $slogan }}</span>
                </p>

                <div class="mt-6 grid lg:mt-8" aria-live="off">
                    @forelse ($slides as $i => $slide)
                        <article class="grid items-center gap-8 transition-opacity duration-700 [grid-area:1/1] lg:grid-cols-[minmax(0,1fr)_minmax(0,500px)] lg:gap-14 {{ $i === 0 ? '' : 'invisible opacity-0' }}"
                                 :class="index === {{ $i }} ? 'visible opacity-100' : 'invisible opacity-0'"
                                 :inert="index !== {{ $i }}" @if ($i !== 0) inert @endif
                                 role="group" aria-roledescription="slide" aria-label="{{ $i + 1 }} of {{ $slides->count() }}">
                            <div class="order-2 lg:order-1">
                                @if ($slide->eyebrow)
                                    <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5 text-[12px] font-semibold tracking-[0.14em] text-mint uppercase sm:text-[13px]">{{ $slide->eyebrow }}</p>
                                @endif
                                <h2 class="mt-4 font-headline text-[38px] leading-[1.02] font-extrabold tracking-[-0.03em] text-balance sm:text-[52px] lg:text-[64px]">{{ $slide->headline }}</h2>
                                @if ($slide->body)
                                    <p class="mt-5 max-w-[560px] font-body text-[18px] leading-relaxed text-white/80 sm:text-[20px]">{{ $slide->body }}</p>
                                @endif
                                <div class="mt-8 flex flex-wrap gap-3">
                                    @if ($slide->button_label && $slide->button_url)
                                        <a href="{{ $slide->button_url }}" class="inline-flex min-h-[52px] items-center gap-2 rounded-xl bg-leaf px-6 text-[17px] font-bold text-forest transition-colors hover:bg-mint">{{ $slide->button_label }} <x-icon name="arrow-right" class="h-4 w-4" /></a>
                                    @endif
                                    <a href="{{ route('empowerment') }}" class="inline-flex min-h-[52px] items-center gap-2 rounded-xl border border-white/30 px-6 text-[17px] font-bold text-white transition-colors hover:bg-white/10"><x-icon name="gift" class="h-5 w-5" /> Empowerment</a>
                                </div>
                            </div>
                            <div class="order-1 lg:order-2">
                                @if ($slide->imageUrl())
                                    <a href="{{ $slide->originalImageUrl() ?? $slide->imageUrl() }}" target="_blank" rel="noopener" class="mx-auto block w-full max-w-[360px] overflow-hidden rounded-[24px] bg-white shadow-[0_30px_60px_-25px_rgba(0,0,0,0.6)] ring-1 ring-white/10 sm:max-w-[420px] lg:max-w-none" aria-label="Open the full flyer">
                                        <img src="{{ $slide->imageUrl() }}" alt="{{ $slide->image_alt ?? '' }}" class="aspect-[4/5] h-auto w-full object-cover" width="1280" height="1600" @if ($i === 0) fetchpriority="high" @else loading="lazy" @endif>
                                    </a>
                                @else
                                    <div class="relative mx-auto flex aspect-[4/5] w-full max-w-[360px] items-center justify-center overflow-hidden rounded-[24px] border border-dashed border-white/25 bg-white/[0.04] sm:max-w-[420px] lg:max-w-none">
                                        <x-fingerprint class="absolute w-3/4 text-white/10" />
                                        <span class="relative rounded-full bg-forest/80 px-4 py-2 text-sm text-white/70">Image coming soon</span>
                                    </div>
                                @endif
                            </div>
                        </article>
                    @empty
                        <article class="[grid-area:1/1]">
                            <h2 class="font-headline text-[44px] leading-[1.02] font-extrabold tracking-[-0.03em] sm:text-[64px]">{{ $slogan }}</h2>
                            <div class="mt-8 flex flex-wrap gap-3">
                                <a href="{{ route('join') }}" class="inline-flex min-h-[52px] items-center rounded-xl bg-leaf px-6 text-[17px] font-bold text-forest hover:bg-mint">Join the Movement</a>
                                <a href="{{ route('empowerment') }}" class="inline-flex min-h-[52px] items-center gap-2 rounded-xl border border-white/30 px-6 text-[17px] font-bold text-white hover:bg-white/10"><x-icon name="gift" class="h-5 w-5" /> Empowerment</a>
                            </div>
                        </article>
                    @endforelse
                </div>

                @if ($slides->count() > 1)
                    <div class="mt-8 flex items-center justify-between gap-4 lg:mt-10">
                        <div class="flex items-center gap-1" role="group" aria-label="Choose a slide">
                            @foreach ($slides as $i => $slide)
                                <button type="button" class="group flex h-11 items-center px-1.5" x-on:click="go({{ $i }})" :aria-current="index === {{ $i }} ? 'true' : 'false'" aria-label="Slide {{ $i + 1 }}: {{ $slide->headline }}">
                                    <span class="block h-2 rounded-full transition-all duration-300 {{ $i === 0 ? 'w-8 bg-leaf' : 'w-2 bg-white/35' }}" :class="index === {{ $i }} ? 'w-8 bg-leaf' : 'w-2 bg-white/35 group-hover:bg-white/60'"></span>
                                </button>
                            @endforeach
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" class="flex h-11 w-11 items-center justify-center rounded-full border border-white/25 hover:bg-white/10" x-on:click="toggle()" :aria-label="stopped ? 'Play the slides' : 'Pause the slides'" aria-label="Pause the slides">
                                <x-icon name="pause" class="h-4 w-4" x-show="!stopped" />
                                <x-icon name="play" class="h-4 w-4" x-show="stopped" x-cloak />
                            </button>
                            <button type="button" class="flex h-11 w-11 items-center justify-center rounded-full border border-white/25 hover:bg-white/10" x-on:click="prev()" aria-label="Previous slide"><x-icon name="chevron-left" /></button>
                            <button type="button" class="flex h-11 w-11 items-center justify-center rounded-full bg-white text-forest hover:bg-mint" x-on:click="next()" aria-label="Next slide"><x-icon name="chevron-right" /></button>
                        </div>
                    </div>
                @endif
            </div>
        </section>

        {{-- Election countdown, directly under the slider --}}
        <section id="countdown" class="relative isolate overflow-hidden bg-action text-white" aria-labelledby="countdown-title"
                 x-data="countdown(@js($electionDate->toIso8601String()), @js($countdown))">
            {{-- Thumbprint watermark: the mark every voter leaves on the ballot. --}}
            <x-fingerprint class="absolute top-1/2 left-1/2 -z-10 w-[680px] -translate-x-1/2 -translate-y-1/2 text-white/[0.08] sm:w-[820px]" stroke="1.6" />
            <x-fingerprint class="absolute -top-10 -right-10 -z-10 w-[180px] rotate-12 text-forest/25 sm:w-[240px]" />

            <div class="mx-auto max-w-[1200px] px-5 py-14 text-center sm:px-8 sm:py-20">
                <p class="eyebrow text-mint"><span class="mr-2 inline-block h-2 w-2 animate-pulse rounded-full bg-accent align-middle" aria-hidden="true"></span>{{ $electionLabel }}</p>
                <h2 id="countdown-title" class="mt-3 font-headline text-[38px] leading-none font-extrabold tracking-[-0.03em] sm:text-[60px] lg:text-[72px]">Countdown to Election Day</h2>
                <p class="mt-4 font-body text-lg text-white/85 sm:text-xl">{{ $localElection->format('l j F Y') }}</p>

                <div class="mx-auto mt-10 flex max-w-[980px] flex-wrap justify-center gap-2.5 sm:flex-nowrap sm:gap-4" x-show="!done" @if ($countdown['done']) hidden @endif role="timer" aria-live="off" aria-label="Time left until election day">
                    @foreach ($units as $unit => $label)
                        <div class="relative basis-[calc(33.333%-0.42rem)] overflow-hidden rounded-2xl bg-forest/90 px-2 pt-5 pb-4 shadow-[0_18px_40px_-20px_rgba(0,0,0,0.6)] ring-1 ring-white/10 sm:flex-1 sm:basis-0 sm:rounded-3xl sm:pt-7 sm:pb-5">
                            <span class="absolute inset-x-0 top-1/2 h-px bg-white/10" aria-hidden="true"></span>
                            <div class="relative h-[52px] overflow-hidden sm:h-[78px] lg:h-[92px]">
                                <template x-for="value in [{{ $unit === 'weeks' || $unit === 'days' ? $unit : 'pad('.$unit.')' }}]" :key="value">
                                    <span class="countdown-digit block font-headline text-[48px] leading-none font-extrabold tabular-nums sm:text-[72px] lg:text-[86px]" x-text="value"></span>
                                </template>
                                <span class="block font-headline text-[48px] leading-none font-extrabold tabular-nums sm:text-[72px] lg:text-[86px]" x-init="$el.remove()">{{ in_array($unit, ['weeks', 'days']) ? $countdown[$unit] : $pad($countdown[$unit]) }}</span>
                            </div>
                            <p class="relative mt-2 text-[11px] font-semibold tracking-[0.16em] text-mint uppercase sm:text-[13px]">{{ $label }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="mx-auto mt-10 max-w-[720px] rounded-3xl bg-forest/90 px-6 py-8 ring-1 ring-white/10" x-show="done" @unless ($countdown['done']) x-cloak @endunless>
                    <p class="font-headline text-[34px] leading-tight font-extrabold sm:text-[48px]">Today is Election Day: go and vote.</p>
                    <p class="mt-3 font-body text-lg text-white/85">Take your PVC to your polling unit. Every thumbprint counts.</p>
                </div>

                <div class="mt-10 flex flex-wrap justify-center gap-3">
                    <a href="{{ route('join') }}" class="inline-flex min-h-[52px] items-center gap-2 rounded-xl bg-white px-6 text-[17px] font-bold text-forest hover:bg-mint">Join the Movement <x-icon name="arrow-right" class="h-4 w-4" /></a>
                </div>
            </div>
        </section>

        {{-- Members across Nigeria --}}
        <section id="map" class="mx-auto grid max-w-[1440px] items-center gap-10 px-5 py-16 sm:px-8 lg:grid-cols-[minmax(0,420px)_minmax(0,1fr)] lg:gap-16 lg:px-[66px] lg:py-24" aria-labelledby="map-title">
            <div>
                <p class="eyebrow text-action">Every state. Every ward.</p>
                <h2 id="map-title" class="mt-3 font-headline text-[36px] leading-[1.05] font-extrabold tracking-[-0.03em] text-forest sm:text-[48px]">The movement across Nigeria</h2>
                <p class="mt-4 font-body text-lg leading-relaxed text-neutral-700">Darker states have more members. Hover or tap a state to see how many have joined, then tap again to join from there.</p>
                <dl class="mt-8 grid grid-cols-3 gap-4 border-t border-line pt-6">
                    @foreach ([['5,000+', 'youth empowered'], ['275+', 'events organised'], ['36+', 'states nationwide']] as [$n, $label])
                        <div>
                            <dt class="sr-only">{{ $label }}</dt>
                            <dd class="font-headline text-[28px] leading-none font-extrabold text-forest sm:text-[34px]">{{ $n }}</dd>
                            <dd class="mt-1.5 font-body text-[15px] text-neutral-600">{{ $label }}</dd>
                        </div>
                    @endforeach
                </dl>
                <a href="{{ route('join') }}" class="mt-8 inline-flex min-h-[52px] items-center gap-2 rounded-xl bg-action px-6 text-[17px] font-bold text-white hover:bg-action-dark">Join from your state <x-icon name="arrow-right" class="h-4 w-4" /></a>
            </div>
            <div class="rounded-[28px] bg-surface p-4 sm:p-8">
                <x-area-map :map="$map" :url="route('map.states')" key="code" :href="route('join', ['state' => '__KEY__'])"
                            action="Join from :name" label="Map of Nigeria's 36 states and the FCT, coloured by number of members" />
            </div>
        </section>
    </main>

    <footer id="contact" class="bg-forest text-white">
        <div class="mx-auto flex max-w-[1440px] flex-col gap-6 px-5 py-10 sm:px-8 lg:flex-row lg:items-center lg:justify-between lg:px-[66px]">
            <div>
                <p class="font-headline text-[26px] font-extrabold tracking-[-0.02em] text-mint">{{ $slogan }}</p>
                <p class="mt-1 font-body text-white/70">Renewed Hope Youth Engagement · Youth for PBAT</p>
            </div>
            <nav class="flex flex-wrap gap-x-6 gap-y-2 font-body text-[15px] text-white/80" aria-label="Footer">
                <a href="{{ route('join') }}" class="hover:text-white">Join the movement</a>
                <a href="{{ route('login') }}" class="hover:text-white">Sign in</a>
                <a href="{{ route('privacy') }}" class="hover:text-white">Privacy notice</a>
            </nav>
        </div>
    </footer>
</body>
</html>
