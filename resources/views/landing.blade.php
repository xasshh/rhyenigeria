@php
    // Drop the real files into public/images and they are picked up automatically.
    $image = fn (string $name) => collect(['svg', 'png', 'webp', 'jpg'])
        ->map(fn ($ext) => "images/{$name}.{$ext}")
        ->first(fn ($path) => file_exists(public_path($path)));

    $rhyeLogo = $image('logo-rhye');
    $logo = $image('logo-renewed-hope');
    $partyLogo = $image('logo-apc');
    $portrait = $image('hero-portrait');

    $links = ['Home' => '#top', 'About' => '#about', 'Get Involved' => route('join'), 'Media' => '#media', 'Contact' => '#contact'];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Renewed Hope Youth Engagement</title>
    <meta name="description" content="Renewed Hope Youth Engagement — Youth for PBAT.">
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body id="top" class="bg-white font-display text-brand antialiased">
    {{-- Announcement bar --}}
    <div class="bg-brand px-4 py-4 text-center text-xs leading-[17px] text-white">
        Renewed Hope Youth Engagement
        <a href="{{ route('join') }}" class="ml-1 font-bold text-accent hover:underline">Support Us</a>
    </div>

    {{-- Navigation --}}
    <header class="mx-auto flex max-w-[1440px] items-center justify-between gap-6 px-5 py-5 sm:px-8 lg:px-[66px] lg:py-6">
        <a href="#top" class="flex shrink-0 items-center gap-4 sm:gap-9" aria-label="Renewed Hope Youth Engagement home">
            @if ($rhyeLogo)
                <img src="{{ asset($rhyeLogo) }}" alt="RHYE — Renewed Hope Youth Engagement" class="h-[40px] w-auto sm:h-[56px]" width="600" height="272">
                <span class="h-8 w-px bg-brand/20 sm:h-10" aria-hidden="true"></span>
            @endif
            @if ($logo)
                <img src="{{ asset($logo) }}" alt="Renewed Hope" class="h-[30px] w-auto sm:h-[37.5px]" width="285" height="150">
            @else
                <span class="text-2xl font-bold tracking-tight">Hope</span>
            @endif
            @if ($partyLogo)
                <img src="{{ asset($partyLogo) }}" alt="APC" class="h-[38px] w-auto sm:h-[45.5px]" width="200" height="182">
            @endif
        </a>

        <nav class="hidden items-center gap-7 text-sm lg:flex" aria-label="Main">
            @foreach ($links as $label => $href)
                <a href="{{ $href }}" @class(['inline-flex items-center gap-2 transition-colors hover:text-brand', 'font-bold text-brand' => $loop->first, 'text-brand-soft' => ! $loop->first]) @if ($loop->first) aria-current="page" @endif>
                    {{ $label }}
                    @if ($label === 'About')
                        <svg class="h-2 w-3.5 text-neutral-400" viewBox="0 0 14 8" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M1 1l6 6 6-6"/></svg>
                    @endif
                </a>
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
            <nav class="absolute right-0 z-20 mt-2 flex w-60 flex-col gap-1 rounded-xl border border-neutral-200 bg-white p-3 text-base shadow-lg" aria-label="Main">
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

    {{-- Hero --}}
    <main class="mx-auto grid max-w-[1440px] items-center gap-12 px-5 pt-8 pb-20 sm:px-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,620px)] lg:gap-10 lg:px-[66px] lg:pt-12 lg:pb-28">
        <div>
            <p class="font-script text-[26px] leading-none tracking-[-0.02em] italic sm:text-[32px] lg:pl-[124px]">Youth for PBAT</p>
            <h1 class="mt-4 text-[44px] leading-[1.14] font-bold tracking-[-0.02em] text-balance sm:text-6xl lg:text-[72px] lg:leading-[86px]">
                Renewed Hope <br class="hidden sm:block">Nation Rebuilt
            </h1>
            {{-- Placeholder copy from the design; replace with the final text. --}}
            <p class="font-body mt-5 max-w-[583px] text-lg leading-relaxed tracking-[-0.02em] sm:text-[26px] sm:leading-[34px]">
                Sub heading goes here and some additional text here. Sub heading goes here and some additional text here.
            </p>
            <div class="mt-10 flex flex-wrap gap-2.5">
                <a href="{{ route('join') }}" class="rounded-[10px] border border-brand bg-brand px-6 py-3 text-lg leading-6 font-bold text-white transition-colors hover:bg-brand-dark">Join the Movement</a>
                <a href="#empowerment" class="rounded-[10px] border border-brand px-9 py-3 text-lg leading-6 font-bold transition-colors hover:bg-brand/10">Empowerment</a>
            </div>
        </div>

        <div class="relative mx-auto aspect-square w-full max-w-[420px] sm:max-w-[520px] lg:max-w-[588px]">
            {{-- The exported portrait already includes the circular crop and green ring. --}}
            @if ($portrait)
                <img src="{{ asset($portrait) }}" alt="" class="h-full w-full" width="1176" height="1176" fetchpriority="high">
            @else
                <div class="h-full w-full rounded-full border-2 border-brand bg-brand/10"></div>
            @endif

            @foreach ([
                ['5,000+ youth empowered', 'top-[-7%] left-[5%] max-w-[13.5rem]'],
                ['275+ Events Organized', 'right-0 bottom-[8%] max-w-[13.5rem]'],
                ['36+ Nationwide', 'bottom-[-6%] left-[5%]'],
            ] as [$stat, $position])
                <p class="font-stat absolute {{ $position }} rounded-xl border-2 border-[#b3b3b3] bg-white px-4 py-2.5 text-base leading-tight text-black sm:rounded-[20px] sm:px-7 sm:py-[17px] sm:text-2xl sm:leading-[30px]">
                    {{ $stat }}
                    @if ($loop->iteration === 2)
                        <svg class="absolute -top-6 -right-1 h-12 w-10 text-[#545454] sm:-top-7 sm:h-14 sm:w-12" viewBox="0 0 48 56" fill="currentColor" aria-hidden="true">
                            <path d="M28 20c1.6 9 5.5 13 14 15-8.500 2-12.400 6-14 15-1.600-9-5.500-13-14-15 8.500-2 12.400-6 14-15z"/>
                            <path d="M11 6c.9 5 3 7.200 7.500 8.200C14 15.200 11.900 17.400 11 22.400 10.100 17.400 8 15.200 3.500 14.200 8 13.200 10.100 11 11 6z"/>
                            <path d="M36 2c.5 3 1.800 4.300 4.500 4.900C37.800 7.500 36.500 8.800 36 11.800c-.5-3-1.800-4.300-4.500-4.900C34.200 6.300 35.500 5 36 2z"/>
                        </svg>
                    @endif
                </p>
            @endforeach
        </div>
    </main>
</body>
</html>
