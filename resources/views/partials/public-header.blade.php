@php
    // Shared by the homepage and the public news pages. The homepage passes its own countdown; other pages compute it.
    $image = fn (string $name) => collect(['svg', 'png', 'webp', 'jpg'])
        ->map(fn ($ext) => "images/{$name}.{$ext}")
        ->first(fn ($path) => file_exists(public_path($path)));
    $rhyeLogo = $image('logo-rhye');
    $logo = $image('logo-renewed-hope');
    $partyLogo = $image('logo-apc');
    $slogan = config('membership.slogan');
    $electionDate ??= \App\Models\Setting::electionDate();
    $countdown ??= \App\Support\Countdown::parts($electionDate);
    $pad = fn (int $n) => str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    $current ??= 'home';
    $home = route('home');
    $links = ['Home' => $home, 'Countdown' => $home.'#countdown', 'News' => route('news.index'), 'Get Involved' => route('join'), 'Members map' => $home.'#map', 'Contact' => '#contact'];
    $currentLabel = ['home' => 'Home', 'news' => 'News'][$current] ?? null;
@endphp
    {{-- Announcement bar, with a compact live countdown --}}
    <div class="bg-forest px-4 py-2.5 text-[13px] leading-snug text-white" x-data="countdown(@js($electionDate->toIso8601String()), @js($countdown))">
        <div class="mx-auto flex max-w-[1440px] flex-wrap items-center justify-center gap-x-4 gap-y-1.5">
            <span class="font-semibold text-mint">{{ $slogan }}</span>
            <a href="{{ $home }}#countdown" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 font-semibold tabular-nums hover:bg-white/15" aria-label="Time left until election day">
                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-accent" aria-hidden="true"></span>
                <span x-show="!done" @if ($countdown['done']) hidden @endif>
                    <span class="text-white/70">Election Day in</span>
                    <span x-text="weeks">{{ $countdown['weeks'] }}</span>w
                    <span x-text="days">{{ $countdown['days'] }}</span>d
                    <span x-text="pad(hours) + ':' + pad(minutes) + ':' + pad(seconds)">{{ $pad($countdown['hours']) }}:{{ $pad($countdown['minutes']) }}:{{ $pad($countdown['seconds']) }}</span>
                </span>
                <span x-show="done" @unless ($countdown['done']) x-cloak @endunless>Today is Election Day: go and vote</span>
            </a>
            <span class="hidden sm:inline">Renewed Hope Youth Engagement <a href="{{ route('join') }}" class="ml-1 font-bold text-accent hover:underline">Support Us</a></span>
            <a href="{{ route('join') }}" class="font-bold text-accent hover:underline sm:hidden">Support Us</a>
            <x-social-links size="sm" class="hidden lg:flex" />
        </div>
    </div>

    {{-- Navigation --}}
    <header class="mx-auto flex max-w-[1440px] items-center justify-between gap-6 px-5 py-4 sm:px-8 lg:px-[66px] lg:py-5">
        <a href="{{ $home }}" class="flex shrink-0 items-center gap-4 sm:gap-9" aria-label="Renewed Hope Youth Engagement home">
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
                <a href="{{ $href }}" @class(['transition-colors hover:text-brand', 'font-bold text-brand' => $label === $currentLabel, 'text-brand-soft' => $label !== $currentLabel]) @if ($label === $currentLabel) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        <div class="hidden items-center gap-5 lg:flex">
            @auth
                <a href="{{ auth()->user()->homeRoute() }}" class="text-sm font-bold text-brand hover:underline">My dashboard</a>
            @else
                <a href="{{ route('login') }}" class="text-sm font-bold text-brand hover:underline">Sign in</a>
            @endauth
            <a href="{{ route('join') }}" class="rounded-[10px] bg-accent px-4 py-2.5 text-sm font-bold text-forest transition-colors hover:bg-[#d9a404]">Join the Movement</a>
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
                <a href="{{ route('join') }}" class="mt-1 rounded-lg bg-accent px-3 py-2.5 text-center font-bold text-forest hover:bg-[#d9a404]">Join the Movement</a>
            </nav>
        </details>
    </header>
