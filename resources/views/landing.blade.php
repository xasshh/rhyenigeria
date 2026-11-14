@php
    $slogan = config('membership.slogan');

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
    @include('partials.public-header', ['current' => 'home'])

    <main>
        {{-- Header slider: backgrounds alternate white, green, white… by position; the layout decides what is on the slide. --}}
        @php
            $themes = $slides->keys()->map(fn ($i) => $i % 2 === 0 ? 'light' : 'dark')->values()->all() ?: ['dark'];
            $yellow = 'bg-accent text-forest hover:bg-[#d9a404]';
        @endphp
        <section class="relative isolate overflow-hidden"
                 x-data="heroSlider({ count: {{ max($slides->count(), 1) }} })"
                 x-on:mouseenter="hovering = true" x-on:mouseleave="hovering = false"
                 x-on:focusin="focused = true" x-on:focusout="focused = $el.contains($event.relatedTarget)"
                 x-on:touchstart.passive="touchStart($event)" x-on:touchend.passive="touchEnd($event)"
                 x-on:keydown="key($event)"
                 role="region" aria-roledescription="carousel" aria-label="Highlights">
            <h1 class="sr-only">Renewed Hope Youth Engagement — {{ $slogan }}</h1>

            <div class="grid" aria-live="off">
                @forelse ($slides as $i => $slide)
                    @php($light = $themes[$i] === 'light')
                    <article @class([
                            'relative isolate overflow-hidden transition-opacity duration-700 [grid-area:1/1]',
                            'data-[active=false]:invisible data-[active=false]:opacity-0',
                            'bg-white text-forest' => $light,
                            'bg-action text-white' => ! $light,
                        ])
                        data-active="{{ $i === 0 ? 'true' : 'false' }}" :data-active="index === {{ $i }} ? 'true' : 'false'"
                        :inert="index !== {{ $i }}" @if ($i !== 0) inert @endif
                        role="group" aria-roledescription="slide" aria-label="{{ $i + 1 }} of {{ $slides->count() }}">

                        <x-fingerprint @class(['absolute -top-28 -left-40 -z-10 w-[520px]', 'text-brand/[0.24]' => $light, 'text-accent/[0.22]' => ! $light]) />
                        <x-fingerprint @class(['absolute -right-24 -bottom-48 -z-10 hidden w-[560px] lg:block', 'text-brand/[0.24]' => $light, 'text-accent/[0.22]' => ! $light]) />

                        @if ($slide->isClassic())
                            {{-- The original landing-page hero (RHYE-2026 Figma). --}}
                            <div class="mx-auto grid max-w-[1440px] items-center gap-12 px-5 pt-14 pb-28 sm:px-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,560px)] lg:gap-10 lg:px-[66px] lg:pt-16 lg:pb-32">
                                {{-- Phones: the President's picture first, the text under it. --}}
                                <div class="order-2 lg:order-1">
                                    @if ($slide->eyebrow)
                                        <p @class(['font-script text-[26px] leading-none tracking-[-0.02em] italic sm:text-[32px] lg:pl-[124px]', 'text-brand' => $light, 'text-accent' => ! $light])>{{ $slide->eyebrow }}</p>
                                    @endif
                                    <h2 @class(['mt-4 text-[44px] leading-[1.14] font-bold tracking-[-0.02em] text-balance sm:text-6xl lg:text-[72px] lg:leading-[86px]', 'text-brand' => $light])>{{ $slide->headline }}</h2>
                                    @if ($slide->body)
                                        <p @class(['font-body mt-5 max-w-[583px] text-lg leading-relaxed tracking-[-0.02em] sm:text-[24px] sm:leading-[34px]', 'text-brand' => $light, 'text-white/90' => ! $light])>{{ $slide->body }}</p>
                                    @endif
                                    <div class="mt-10 flex flex-wrap gap-2.5">
                                        @if ($slide->button_label && $slide->button_url)
                                            <a href="{{ $slide->button_url }}" class="rounded-[10px] px-6 py-3 text-lg leading-6 font-bold transition-colors {{ $yellow }}">{{ $slide->button_label }}</a>
                                        @endif
                                        <a href="{{ route('empowerment') }}" @class(['rounded-[10px] border px-9 py-3 text-lg leading-6 font-bold transition-colors', 'border-brand text-brand hover:bg-brand/10' => $light, 'border-white/60 text-white hover:bg-white/10' => ! $light])>Empowerment</a>
                                    </div>
                                </div>

                                <div class="relative order-1 mx-auto mb-4 w-full max-w-[320px] sm:max-w-[400px] lg:order-2 lg:mb-0 lg:max-w-[460px]">
                                    {{-- Same rounded 4:5 frame as the other slides; badges and the logo sticker overlap its edges. --}}
                                    <div @class(['aspect-[4/5] w-full overflow-hidden rounded-[24px] bg-white', 'shadow-[0_30px_60px_-25px_rgba(15,42,28,0.45)] ring-1 ring-line' => $light, 'shadow-[0_30px_60px_-25px_rgba(0,0,0,0.6)] ring-1 ring-white/10' => ! $light])>
                                        @if ($slide->imageUrl())
                                            <img src="{{ $slide->imageUrl() }}" alt="{{ $slide->image_alt ?? '' }}" class="h-full w-full object-cover object-top" width="900" height="1126" @if ($i === 0) fetchpriority="high" @else loading="lazy" @endif>
                                        @endif
                                    </div>
                                    <x-logo-tag class="-top-4 -right-3 sm:-right-5" />
                                    @foreach ([
                                        ['5,000+ youth empowered', 'top-[10%] -left-3 max-w-[11rem] sm:-left-10 sm:max-w-[13.5rem]'],
                                        ['275+ Events Organized', '-right-3 bottom-[22%] max-w-[11rem] sm:-right-10 sm:max-w-[13.5rem]'],
                                        ['36+ Nationwide', '-bottom-5 left-[8%]'],
                                    ] as [$stat, $position])
                                        <p class="font-stat absolute {{ $position }} rounded-xl border-2 border-[#b3b3b3] bg-white px-3.5 py-2 text-sm leading-tight text-black sm:rounded-[20px] sm:px-6 sm:py-4 sm:text-xl sm:leading-[28px]">
                                            {{ $stat }}
                                            @if ($loop->iteration === 2)
                                                <svg @class(['absolute -top-6 -right-1 h-12 w-10 sm:-top-7 sm:h-14 sm:w-12', 'text-[#545454]' => $light, 'text-accent' => ! $light]) viewBox="0 0 48 56" fill="currentColor" aria-hidden="true">
                                                    <path d="M28 20c1.6 9 5.5 13 14 15-8.500 2-12.400 6-14 15-1.600-9-5.500-13-14-15 8.500-2 12.400-6 14-15z"/>
                                                    <path d="M11 6c.9 5 3 7.200 7.500 8.200C14 15.200 11.900 17.400 11 22.400 10.100 17.400 8 15.200 3.500 14.200 8 13.200 10.100 11 11 6z"/>
                                                    <path d="M36 2c.5 3 1.800 4.300 4.500 4.900C37.800 7.500 36.500 8.800 36 11.800c-.5-3-1.800-4.300-4.500-4.900C34.200 6.300 35.500 5 36 2z"/>
                                                </svg>
                                            @endif
                                        </p>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="mx-auto grid max-w-[1440px] items-center gap-8 px-5 pt-10 pb-28 sm:px-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,460px)] lg:gap-14 lg:px-[66px] lg:pt-14 lg:pb-32">
                                <div class="order-2 lg:order-1">
                                    @if ($slide->eyebrow)
                                        <p @class(['inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-[12px] font-semibold tracking-[0.14em] uppercase sm:text-[13px]', 'bg-action/10 text-action' => $light, 'bg-white/15 text-white' => ! $light])>{{ $slide->eyebrow }}</p>
                                    @endif
                                    <h2 class="mt-4 font-headline text-[38px] leading-[1.02] font-extrabold tracking-[-0.03em] text-balance sm:text-[52px] lg:text-[64px]">{{ $slide->headline }}</h2>
                                    @if ($slide->body)
                                        <p @class(['mt-5 max-w-[560px] font-body text-[18px] leading-relaxed sm:text-[20px]', 'text-neutral-700' => $light, 'text-white/90' => ! $light])>{{ $slide->body }}</p>
                                    @endif
                                    <div class="mt-8 flex flex-wrap gap-3">
                                        @if ($slide->button_label && $slide->button_url)
                                            <a href="{{ $slide->button_url }}" class="inline-flex min-h-[52px] items-center gap-2 rounded-xl px-6 text-[17px] font-bold transition-colors {{ $yellow }}">{{ $slide->button_label }} <x-icon name="arrow-right" class="h-4 w-4" /></a>
                                        @endif
                                        <a href="{{ route('empowerment') }}" @class(['inline-flex min-h-[52px] items-center gap-2 rounded-xl border px-6 text-[17px] font-bold transition-colors', 'border-action/40 text-action hover:bg-action/10' => $light, 'border-white/50 text-white hover:bg-white/10' => ! $light])><x-icon name="gift" class="h-5 w-5" /> Empowerment</a>
                                    </div>
                                </div>
                                <div class="order-1 lg:order-2">
                                    {{-- Flyers and photos of people share one frame so the slides look uniform. --}}
                                    @if ($slide->imageUrl())
                                        <div class="relative mx-auto w-full max-w-[340px] sm:max-w-[400px] lg:max-w-none">
                                        <x-logo-tag class="-bottom-4 -left-3 sm:-left-5" />
                                        <a href="{{ $slide->originalImageUrl() ?? $slide->imageUrl() }}" target="_blank" rel="noopener" @class(['mx-auto block w-full max-w-[340px] overflow-hidden rounded-[24px] bg-white sm:max-w-[400px] lg:max-w-none', 'shadow-[0_30px_60px_-25px_rgba(15,42,28,0.45)] ring-1 ring-line' => $light, 'shadow-[0_30px_60px_-25px_rgba(0,0,0,0.6)] ring-1 ring-white/10' => ! $light]) aria-label="Open the full image">
                                            <img src="{{ $slide->imageUrl() }}" alt="{{ $slide->image_alt ?? '' }}" class="aspect-[4/5] h-auto w-full object-cover object-top" width="1280" height="1600" @if ($i === 0) fetchpriority="high" @else loading="lazy" @endif>
                                        </a>
                                        </div>
                                    @else
                                        <div @class(['relative mx-auto flex aspect-[4/5] w-full max-w-[340px] items-center justify-center overflow-hidden rounded-[24px] border border-dashed sm:max-w-[400px] lg:max-w-none', 'border-action/30 bg-action/[0.04]' => $light, 'border-white/30 bg-white/[0.06]' => ! $light])>
                                            <x-fingerprint @class(['absolute w-3/4', 'text-brand/20' => $light, 'text-accent/30' => ! $light]) />
                                            <span @class(['relative rounded-full px-4 py-2 text-sm', 'bg-white text-neutral-600' => $light, 'bg-forest/60 text-white/80' => ! $light])>Image coming soon</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </article>
                @empty
                    <article class="bg-action text-white [grid-area:1/1]">
                        <div class="mx-auto max-w-[1440px] px-5 py-16 sm:px-8 lg:px-[66px]">
                            <h2 class="font-headline text-[44px] leading-[1.02] font-extrabold tracking-[-0.03em] sm:text-[64px]">{{ $slogan }}</h2>
                            <div class="mt-8 flex flex-wrap gap-3">
                                <a href="{{ route('join') }}" class="inline-flex min-h-[52px] items-center rounded-xl px-6 text-[17px] font-bold {{ $yellow }}">Join the Movement</a>
                                <a href="{{ route('empowerment') }}" class="inline-flex min-h-[52px] items-center gap-2 rounded-xl border border-white/50 px-6 text-[17px] font-bold text-white hover:bg-white/10"><x-icon name="gift" class="h-5 w-5" /> Empowerment</a>
                            </div>
                        </div>
                    </article>
                @endforelse
            </div>

            @if ($slides->count() > 1)
                {{-- Controls sit over the bottom of every slide and switch colours to suit it. --}}
                <div class="pointer-events-none absolute inset-x-0 bottom-0 z-10">
                    <div class="mx-auto flex max-w-[1440px] items-center justify-between gap-4 px-5 pb-6 text-white transition-colors duration-500 data-[theme=light]:text-forest sm:px-8 lg:px-[66px] lg:pb-8"
                         data-theme="{{ $themes[0] }}" :data-theme="@js($themes)[index]">
                        <div class="pointer-events-auto flex items-center gap-1" role="group" aria-label="Choose a slide">
                            @foreach ($slides as $i => $slide)
                                <button type="button" class="group flex h-11 items-center px-1.5" x-on:click="go({{ $i }})" :aria-current="index === {{ $i }} ? 'true' : 'false'" aria-label="Slide {{ $i + 1 }}: {{ $slide->headline }}">
                                    <span class="block h-2 w-2 rounded-full bg-current opacity-35 transition-all duration-300 group-hover:opacity-60 data-[active=true]:w-8 data-[active=true]:opacity-100" data-active="{{ $i === 0 ? 'true' : 'false' }}" :data-active="index === {{ $i }} ? 'true' : 'false'"></span>
                                </button>
                            @endforeach
                        </div>
                        <div class="pointer-events-auto flex items-center gap-2">
                            <button type="button" class="flex h-11 w-11 items-center justify-center rounded-full border border-current/30 hover:bg-current/10" x-on:click="toggle()" :aria-label="stopped ? 'Play the slides' : 'Pause the slides'" aria-label="Pause the slides">
                                <x-icon name="pause" class="h-4 w-4" x-show="!stopped" />
                                <x-icon name="play" class="h-4 w-4" x-show="stopped" x-cloak />
                            </button>
                            <button type="button" class="flex h-11 w-11 items-center justify-center rounded-full border border-current/30 hover:bg-current/10" x-on:click="prev()" aria-label="Previous slide"><x-icon name="chevron-left" /></button>
                            <button type="button" class="flex h-11 w-11 items-center justify-center rounded-full border border-current/30 hover:bg-current/10" x-on:click="next()" aria-label="Next slide"><x-icon name="chevron-right" /></button>
                        </div>
                    </div>
                </div>
            @endif
        </section>

        {{-- Election countdown, directly under the slider --}}
        <section id="countdown" class="relative isolate overflow-hidden bg-action text-white" aria-labelledby="countdown-title"
                 x-data="countdown(@js($electionDate->toIso8601String()), @js($countdown))">
            {{-- Thumbprint watermark: the mark every voter leaves on the ballot. --}}
            <x-fingerprint class="absolute top-1/2 left-1/2 -z-10 w-[680px] -translate-x-1/2 -translate-y-1/2 text-accent/[0.16] sm:w-[820px]" stroke="1.6" />
            <x-fingerprint class="absolute -top-10 -right-10 -z-10 w-[180px] rotate-12 text-accent/40 sm:w-[240px]" />

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
                    <a href="{{ route('join') }}" class="inline-flex min-h-[52px] items-center gap-2 rounded-xl bg-accent px-6 text-[17px] font-bold text-forest hover:bg-[#d9a404]">Join the Movement <x-icon name="arrow-right" class="h-4 w-4" /></a>
                </div>
            </div>
        </section>

        {{-- Latest public news --}}
        @if ($news->isNotEmpty())
            <section id="news" class="bg-surface" aria-labelledby="news-title">
                <div class="mx-auto max-w-[1440px] px-5 py-16 sm:px-8 lg:px-[66px] lg:py-20">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p class="eyebrow text-action">News &amp; gallery</p>
                            <h2 id="news-title" class="mt-3 font-headline text-[36px] leading-[1.05] font-extrabold tracking-[-0.03em] text-forest sm:text-[48px]">From the field</h2>
                        </div>
                        <a href="{{ route('news.index') }}" class="inline-flex items-center gap-1.5 font-body text-[16px] font-bold text-action hover:underline">All news <x-icon name="arrow-right" class="h-4 w-4" /></a>
                    </div>
                    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($news as $post)
                            @include('partials.news-card', ['post' => $post])
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

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

    @include('partials.public-footer')
</body>
</html>
