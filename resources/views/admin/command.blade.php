@php
    /** @var \Carbon\CarbonImmutable $electionDate */
    $countdown = \App\Support\Countdown::parts($electionDate);
    $scope = $state ? $state->name.' State' : 'Nigeria';
    $agents = site_term('Canvassers');
@endphp
<x-layouts.base title="Election Command Centre" noindex>
    <x-slot:head>
        <meta name="theme-color" content="#030b07">
        <style>
            .cc { --bg:#030b07; --panel:rgba(8,26,17,.72); --line:rgba(46,204,113,.18); --glow:#2ee59d; --amber:#f2b705; --red:#ff4d4f; --text:#d8f3e3; --dim:#6f9a83;
                  background: radial-gradient(1200px 600px at 70% -10%, rgba(46,229,157,.10), transparent 60%), radial-gradient(900px 500px at -10% 110%, rgba(242,183,5,.06), transparent 60%), var(--bg); color: var(--text); }
            .cc-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-variant-numeric: tabular-nums; }
            .cc-grid { background-image: linear-gradient(var(--line) 1px, transparent 1px), linear-gradient(90deg, var(--line) 1px, transparent 1px); background-size: 48px 48px; opacity: .25; mask-image: radial-gradient(ellipse at center, #000 30%, transparent 80%); animation: cc-drift 40s linear infinite; }
            .cc-scan { background: repeating-linear-gradient(to bottom, rgba(255,255,255,.025) 0 1px, transparent 1px 3px); }
            .cc-scan::after { content: ''; position: absolute; inset: 0; background: linear-gradient(to bottom, transparent, rgba(46,229,157,.06), transparent); height: 18%; animation: cc-sweep 7s linear infinite; }
            .cc-panel { position: relative; background: var(--panel); border: 1px solid var(--line); border-radius: 14px; backdrop-filter: blur(6px); box-shadow: inset 0 0 40px rgba(46,229,157,.04), 0 0 0 1px rgba(0,0,0,.4); }
            .cc-panel::before, .cc-panel::after { content: ''; position: absolute; width: 14px; height: 14px; border-color: var(--glow); opacity: .8; pointer-events: none; }
            .cc-panel::before { top: -1px; left: -1px; border-top: 2px solid; border-left: 2px solid; border-top-left-radius: 14px; }
            .cc-panel::after { bottom: -1px; right: -1px; border-bottom: 2px solid; border-right: 2px solid; border-bottom-right-radius: 14px; }
            .cc-label { font-size: 11px; letter-spacing: .22em; text-transform: uppercase; color: var(--dim); font-weight: 600; }
            .cc-value { text-shadow: 0 0 18px rgba(46,229,157,.35); }
            .cc-bump { animation: cc-bump 1.6s ease-out; }
            .cc-live { box-shadow: 0 0 0 0 rgba(255,77,79,.7); animation: cc-pulse 1.6s infinite; }
            .cc-radar { background: conic-gradient(from 0deg, rgba(46,229,157,.0) 0deg, rgba(46,229,157,.0) 300deg, rgba(46,229,157,.28) 360deg); border-radius: 9999px; animation: cc-spin 6s linear infinite; mix-blend-mode: screen; }
            .cc-rings { background: repeating-radial-gradient(circle, transparent 0 58px, rgba(46,229,157,.10) 58px 59px); border-radius: 9999px; }
            .cc-map path { transition: fill .8s ease, filter .3s ease; cursor: crosshair; }
            .cc-map path:hover { filter: brightness(1.5) drop-shadow(0 0 6px rgba(46,229,157,.9)); }
            .cc-map path.cc-hot { animation: cc-hot 2.2s ease-in-out infinite; }
            .cc-line { stroke-dasharray: 2000; stroke-dashoffset: 2000; animation: cc-draw 2.4s ease-out forwards; filter: drop-shadow(0 0 6px rgba(46,229,157,.8)); }
            .cc-ticker { animation: cc-marquee 60s linear infinite; }
            .cc-caret::after { content: '▌'; animation: cc-blink 1s steps(1) infinite; color: var(--glow); margin-left: 2px; }
            .cc-boot-line { opacity: 0; animation: cc-fade .3s ease-out forwards; }
            @keyframes cc-drift { to { background-position: 48px 48px; } }
            @keyframes cc-sweep { from { top: -20%; } to { top: 110%; } }
            @keyframes cc-bump { 0% { color: #fff; text-shadow: 0 0 28px rgba(46,229,157,1); } 100% { } }
            @keyframes cc-pulse { 70% { box-shadow: 0 0 0 10px rgba(255,77,79,0); } 100% { box-shadow: 0 0 0 0 rgba(255,77,79,0); } }
            @keyframes cc-spin { to { transform: rotate(360deg); } }
            @keyframes cc-hot { 50% { filter: brightness(1.6) drop-shadow(0 0 8px rgba(242,183,5,.9)); } }
            @keyframes cc-draw { to { stroke-dashoffset: 0; } }
            @keyframes cc-marquee { from { transform: translateX(0); } to { transform: translateX(-50%); } }
            @keyframes cc-blink { 50% { opacity: 0; } }
            @keyframes cc-fade { to { opacity: 1; } }
            @media (prefers-reduced-motion: reduce) {
                .cc *, .cc *::before, .cc *::after { animation: none !important; transition: none !important; }
                .cc-line { stroke-dashoffset: 0; }
                .cc-boot-line { opacity: 1; }
            }
        </style>
    </x-slot:head>

    <div class="cc fixed inset-0 overflow-x-hidden overflow-y-auto" x-data="commandCentre(@js($config))">
        <div class="cc-grid pointer-events-none fixed inset-0" aria-hidden="true"></div>
        <div class="cc-scan pointer-events-none fixed inset-0 z-40 overflow-hidden" aria-hidden="true"></div>

        {{-- Boot sequence: once per browser session. --}}
        <div x-show="booting" x-transition:leave="transition duration-700" x-transition:leave-end="opacity-0" class="cc-mono fixed inset-0 z-50 flex items-center justify-center bg-[#020805] px-6" aria-hidden="true">
            <div class="w-full max-w-xl space-y-2 text-sm text-[var(--glow)] sm:text-base">
                <template x-for="(line, i) in bootLines" :key="i">
                    <p class="cc-boot-line" :style="`animation-delay:${i * 320}ms`" x-text="line"></p>
                </template>
                <p class="cc-boot-line cc-caret pt-3 text-lg font-bold tracking-[0.3em] text-white" :style="`animation-delay:${bootLines.length * 320}ms`">ACCESS GRANTED</p>
            </div>
        </div>

        <div class="relative z-10 mx-auto max-w-[1800px] px-4 pb-24 sm:px-6">
            {{-- Header --}}
            <header class="flex flex-wrap items-center justify-between gap-4 border-b border-[var(--line)] py-4">
                <div class="flex items-center gap-4">
                    <span class="shrink-0 rounded-lg bg-white p-1.5 shadow-[0_0_24px_rgba(46,229,157,.35)]"><img src="{{ asset(config('site.logo.0')) }}" alt="" class="h-9 w-auto sm:h-11" width="{{ config('site.logo.1') }}" height="{{ config('site.logo.2') }}"></span>
                    <div>
                        <p class="cc-label">{{ config('site.name') }} · {{ $scope }}</p>
                        <h1 class="font-headline text-lg leading-tight font-extrabold tracking-[0.06em] text-white uppercase sm:text-2xl sm:tracking-[0.08em]">Election Command Centre</h1>
                    </div>
                </div>
                <div class="cc-mono flex flex-wrap items-center gap-2 text-xs sm:gap-3 sm:text-sm">
                    <span class="inline-flex items-center gap-2 rounded-full border border-[var(--red)]/50 bg-[var(--red)]/10 px-3 py-1 font-bold tracking-[0.2em] text-[var(--red)]"><span class="cc-live h-2 w-2 rounded-full bg-[var(--red)]"></span>LIVE</span>
                    <span class="rounded-full border border-[var(--line)] px-3 py-1 text-[var(--dim)]">WAT <span class="text-white" x-text="clock">--:--:--</span></span>
                    <span class="hidden rounded-full border border-[var(--line)] px-3 py-1 text-[var(--dim)] md:inline">SYNC <span :class="error ? 'text-[var(--red)]' : 'text-[var(--glow)]'" x-text="error ? 'LOST' : (syncedAgo ?? '…')"></span></span>
                    <span class="hidden rounded-full border border-[var(--amber)]/40 px-3 py-1 text-[var(--amber)] lg:inline">CLEARANCE · SUPER ADMIN</span>
                    <button type="button" @click="fullscreen()" class="hidden rounded-full sm:inline-block border border-[var(--line)] px-3 py-1 text-[var(--text)] hover:border-[var(--glow)] hover:text-white">⛶ Full screen</button>
                    <a href="{{ route('admin.dashboard') }}" class="rounded-full border border-[var(--line)] px-3 py-1 text-[var(--dim)] hover:text-white">Exit</a>
                </div>
            </header>

            {{-- Key figures --}}
            <section class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6" aria-label="Key figures">
                @foreach ([
                    ['total', 'Total members', 'Registered supporters'],
                    ['today', 'Joined today', 'Since midnight WAT'],
                    ['week', 'This week', 'Since Monday'],
                    ['agents', $agents, 'Approved, in the field'],
                    ['on_shift', 'On shift now', 'Agents reporting live'],
                    ['pvc', 'PVC holders', 'Ready to vote'],
                ] as [$key, $label, $hint])
                    <div class="cc-panel min-w-0 p-3 sm:p-4">
                        <p class="cc-label">{{ $label }}</p>
                        <p class="cc-mono cc-value mt-2 text-2xl font-bold text-white sm:text-4xl" :class="bumped.{{ $key }} && 'cc-bump'" x-text="fmt(display.{{ $key }})">—</p>
                        <p class="mt-1 text-xs text-[var(--dim)]">
                            @if ($key === 'pvc')
                                <span class="text-[var(--glow)]" x-text="pct(display.pvc, display.total)"></span> of members
                            @elseif ($key === 'on_shift')
                                <span class="text-[var(--glow)]" x-text="fmt(data.field?.active ?? 0)"></span> active · <span x-text="fmt(data.field?.households ?? 0)"></span> households today
                            @else
                                {{ $hint }}
                            @endif
                        </p>
                    </div>
                @endforeach
            </section>

            <div class="mt-3 grid gap-3 xl:grid-cols-12">
                {{-- Situation map --}}
                <section class="cc-panel p-4 xl:col-span-7" aria-label="Situation map">
                    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                        <p class="cc-label">Situation map · members by {{ $areaNoun }}</p>
                        <p class="cc-mono text-xs text-[var(--amber)]"><span class="mr-1 inline-block h-2 w-2 rounded-full bg-[var(--amber)]"></span>Sign-ups today</p>
                    </div>
                    <div class="relative mx-auto mt-3 aspect-square max-h-[620px] w-full" x-ref="mapFrame" @pointerleave="tip = null">
                        <div class="cc-rings pointer-events-none absolute inset-[4%]" aria-hidden="true"></div>
                        <div class="cc-radar pointer-events-none absolute inset-[4%]" aria-hidden="true"></div>
                        <svg viewBox="{{ $map['viewBox'] }}" class="cc-map relative h-full w-full" x-ref="map" role="img" aria-label="Map of members by {{ $areaNoun }}" @pointermove="hover($event)" @click="pickArea($event)">
                            @foreach ($map['areas'] as $area)
                                <path d="{{ $area['d'] }}" data-id="{{ $area['id'] }}" data-name="{{ $area['name'] }}" fill="#0b1f15" stroke="#2ee59d" stroke-opacity=".35" stroke-width="1" vector-effect="non-scaling-stroke" />
                            @endforeach
                        </svg>
                        <div x-show="tip" x-cloak class="cc-mono pointer-events-none absolute z-10 w-max -translate-x-1/2 rounded-lg border border-[var(--glow)]/60 bg-[#04140c]/95 px-3 py-2 text-xs shadow-[0_0_20px_rgba(46,229,157,.35)]"
                             :style="tip && `left:${tip.x}px; top:${tip.y}px; transform: translate(-50%, calc(-100% - 12px))`">
                            <p class="text-sm font-bold tracking-wider text-white uppercase" x-text="tip?.name"></p>
                            <p class="text-[var(--glow)]"><span x-text="fmt(tip?.total ?? 0)"></span> members</p>
                            <p class="text-[var(--amber)]">+<span x-text="fmt(tip?.today ?? 0)"></span> today · <span x-text="fmt(tip?.agents ?? 0)"></span> agents</p>
                        </div>
                    </div>
                    <div x-show="picked" x-cloak class="cc-mono mt-3 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-[var(--glow)]/40 bg-[var(--glow)]/5 px-3 py-2 text-xs">
                        <span><span class="font-bold text-white uppercase" x-text="picked?.name"></span> · <span x-text="fmt(picked?.total ?? 0)"></span> members · +<span x-text="fmt(picked?.today ?? 0)"></span> today</span>
                        <button type="button" @click="callArea()" class="rounded-md bg-[var(--glow)] px-3 py-1.5 font-bold text-[#04140c]">☎ Call people here</button>
                    </div>
                </section>

                <div class="grid gap-3 xl:col-span-5">
                    {{-- Countdown --}}
                    <section class="cc-panel p-5" aria-label="Countdown" x-data="countdown(@js($electionDate->toIso8601String()), @js($countdown))">
                        <p class="cc-label">T-minus · {{ $electionLabel }}</p>
                        <div class="cc-mono mt-3 grid grid-cols-4 gap-2 text-center" x-show="!done">
                            @foreach (['totalDays' => 'Days', 'hours' => 'Hrs', 'minutes' => 'Min', 'seconds' => 'Sec'] as $unit => $label)
                                <div class="rounded-lg border border-[var(--line)] bg-black/30 py-3">
                                    <p class="cc-value text-2xl font-bold text-white sm:text-5xl" x-text="String({{ $unit }}).padStart(2, '0')">{{ str_pad((string) ($unit === 'totalDays' ? ($countdown['totalDays'] ?? 0) : $countdown[$unit]), 2, '0', STR_PAD_LEFT) }}</p>
                                    <p class="cc-label mt-1">{{ $label }}</p>
                                </div>
                            @endforeach
                        </div>
                        <p x-show="done" x-cloak class="cc-mono mt-3 text-2xl font-bold text-[var(--amber)]">ELECTION DAY: ALL UNITS DEPLOY</p>
                        <p class="cc-mono mt-3 text-xs text-[var(--dim)]">{{ $electionDate->setTimezone(config('membership.timezone'))->format('l j F Y · H:i') }} WAT</p>
                    </section>

                    {{-- Coverage --}}
                    <section class="cc-panel p-5" aria-label="Coverage">
                        <p class="cc-label">Ground coverage</p>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            @foreach ([['wards', 'wards_total', 'Wards reached'], ['areas', 'areas_total', ucfirst($areaNoun).'s reached']] as [$have, $of, $label])
                                <div class="flex items-center gap-4">
                                    <svg viewBox="0 0 100 100" class="h-20 w-20 shrink-0 -rotate-90 sm:h-24 sm:w-24" aria-hidden="true">
                                        <circle cx="50" cy="50" r="42" fill="none" stroke="rgba(46,229,157,.12)" stroke-width="9" />
                                        <circle cx="50" cy="50" r="42" fill="none" stroke="#2ee59d" stroke-width="9" stroke-linecap="round" pathLength="100"
                                                :stroke-dasharray="`${ratio('{{ $have }}', '{{ $of }}') * 100} 100`" :stroke-opacity="ratio('{{ $have }}', '{{ $of }}') > 0 ? 1 : 0" style="transition: stroke-dasharray 1.6s ease; filter: drop-shadow(0 0 6px rgba(46,229,157,.8))" />
                                    </svg>
                                    <div>
                                        <p class="cc-mono cc-value text-3xl font-bold text-white" x-text="Math.round(ratio('{{ $have }}', '{{ $of }}') * 100) + '%'">—</p>
                                        <p class="text-sm text-[var(--dim)]">{{ $label }}</p>
                                        <p class="cc-mono text-xs text-[var(--glow)]"><span x-text="fmt(coverage.{{ $have }})"></span> / <span x-text="fmt(coverage.{{ $of }})"></span></p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    {{-- Weakest areas --}}
                    <section class="cc-panel p-5" aria-label="Areas needing attention">
                        <p class="cc-label text-[var(--amber)]!">⚠ Needs attention · fewest members</p>
                        <ul class="cc-mono mt-3 space-y-1.5 text-sm">
                            <template x-for="a in weakest" :key="a.id">
                                <li class="flex items-center justify-between gap-3 rounded-md border border-[var(--amber)]/20 bg-[var(--amber)]/5 px-3 py-1.5">
                                    <span class="truncate uppercase" x-text="a.name"></span>
                                    <span class="shrink-0 text-[var(--amber)]" x-text="fmt(a.total)"></span>
                                </li>
                            </template>
                        </ul>
                    </section>
                </div>
            </div>

            <div class="mt-3 grid gap-3 xl:grid-cols-12">
                {{-- Trend --}}
                <section class="cc-panel p-5 xl:col-span-7" aria-label="Sign-ups, last 30 days">
                    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                        <p class="cc-label">Momentum · sign-ups, last 30 days <span class="normal-case tracking-normal">(bulk imports excluded)</span></p>
                        <p class="cc-mono text-xs text-[var(--dim)]">Peak <span class="text-white" x-text="fmt(trendMax)"></span>/day</p>
                    </div>
                    <svg viewBox="0 0 600 180" preserveAspectRatio="none" class="mt-4 h-36 w-full sm:h-48" aria-hidden="true">
                        <defs>
                            <linearGradient id="cc-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2ee59d" stop-opacity=".35" /><stop offset="1" stop-color="#2ee59d" stop-opacity="0" /></linearGradient>
                        </defs>
                        @foreach ([45, 90, 135] as $y)<line x1="0" x2="600" y1="{{ $y }}" y2="{{ $y }}" stroke="rgba(46,229,157,.10)" stroke-dasharray="4 6" />@endforeach
                        <path :d="trendArea" fill="url(#cc-fill)" />
                        <path x-ref="trendPath" :d="trendLine || 'M0,170'" class="cc-line" pathLength="2000" fill="none" stroke="#2ee59d" stroke-width="2.5" vector-effect="non-scaling-stroke" />
                    </svg>
                    <div class="cc-mono mt-1 flex justify-between text-[11px] text-[var(--dim)]"><span x-text="data.trend?.labels?.[0]"></span><span>Today</span></div>
                </section>

                {{-- Leaderboard --}}
                <section class="cc-panel p-5 xl:col-span-5" aria-label="Strongest areas">
                    <p class="cc-label">Strongest {{ $areaNoun }}s</p>
                    <ol class="mt-3 space-y-2">
                        <template x-for="(a, i) in strongest" :key="a.id">
                            <li class="cc-mono text-sm">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="truncate"><span class="mr-2 text-[var(--dim)]" x-text="String(i + 1).padStart(2, '0')"></span><span class="uppercase" x-text="a.name"></span></span>
                                    <span class="shrink-0 text-white" x-text="fmt(a.total)"></span>
                                </div>
                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-[var(--glow)]/10">
                                    <div class="h-full rounded-full bg-gradient-to-r from-[#1c8f5e] to-[#2ee59d] shadow-[0_0_10px_rgba(46,229,157,.7)] transition-[width] duration-1000" :style="`width:${areaMax ? (a.total / areaMax) * 100 : 0}%`"></div>
                                </div>
                            </li>
                        </template>
                    </ol>
                </section>
            </div>

            <div class="mt-3 grid gap-3 lg:grid-cols-2">
                <section class="cc-panel p-5" aria-label="Latest sign-ups">
                    <p class="cc-label">Incoming · latest sign-ups</p>
                    <ul class="cc-mono mt-3 divide-y divide-[var(--line)] text-sm">
                        <template x-for="(r, i) in data.recent ?? []" :key="i">
                            <li class="flex flex-wrap items-center gap-x-3 gap-y-0.5 py-2">
                                <span class="text-[var(--dim)]" x-text="r.time"></span>
                                <span class="rounded px-1.5 py-0.5 text-[10px] font-bold tracking-wider uppercase" :class="r.via === 'field' ? 'bg-[var(--amber)]/15 text-[var(--amber)]' : 'bg-[var(--glow)]/15 text-[var(--glow)]'" x-text="r.via"></span>
                                <span class="text-white" x-text="r.name"></span>
                                <span class="w-full truncate text-xs text-[var(--dim)] sm:ml-auto sm:w-auto" x-text="r.place"></span>
                            </li>
                        </template>
                        <li x-show="!(data.recent ?? []).length" class="py-2 text-[var(--dim)]">No new sign-ups yet.</li>
                    </ul>
                </section>
                <section class="cc-panel p-5" aria-label="Field reports today">
                    <p class="cc-label">Field reports · today</p>
                    <ul class="cc-mono mt-3 divide-y divide-[var(--line)] text-sm">
                        <template x-for="(a, i) in data.activities ?? []" :key="i">
                            <li class="flex flex-wrap items-center gap-x-3 gap-y-0.5 py-2">
                                <span class="text-[var(--dim)]" x-text="a.time"></span>
                                <span class="text-white" x-text="a.who"></span>
                                <span class="text-[var(--glow)]" x-text="a.label"></span>
                                <span class="w-full truncate text-xs text-[var(--dim)] sm:ml-auto sm:w-auto" x-text="a.ward"></span>
                            </li>
                        </template>
                        <li x-show="!(data.activities ?? []).length" class="py-2 text-[var(--dim)]">No field reports yet today. <a href="{{ route('admin.live') }}" class="text-[var(--glow)] underline">Open the live map</a></li>
                    </ul>
                </section>
            </div>

            {{-- Call desk --}}
            <section id="call-desk" class="cc-panel mt-3 scroll-mt-4 p-5" aria-label="Call desk" x-data="callDesk(@js(['url' => $config['contacts'], 'kinds' => $contactKinds]))" @cc-lga.window="pickLga($event.detail)">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="cc-label">☎ Call desk · direct line to the field</p>
                        <p class="mt-1 hidden text-xs text-[var(--dim)] sm:block">Tap <span class="text-[var(--glow)]">Call</span> to ring from this device (on a computer it opens your phone or calling app) or <span class="text-[var(--glow)]">WhatsApp</span> to message. Click an {{ $areaNoun }} on the map to see its people.</p>
                    </div>
                    <div class="cc-mono flex w-full flex-col gap-2 text-sm sm:w-auto sm:flex-row sm:items-center">
                        @if ($state)
                            <select x-model="lga" @change="search()" class="w-full rounded-lg sm:w-auto border border-[var(--line)] bg-black/40 px-3 py-2 text-[var(--text)] focus:border-[var(--glow)] focus:outline-none" aria-label="LGA">
                                <option value="">All LGAs</option>
                                @foreach (collect($map['areas'])->sortBy('name') as $area)
                                    <option value="{{ $area['id'] }}">{{ $area['name'] }}</option>
                                @endforeach
                            </select>
                        @endif
                        <input type="search" x-model="q" @input.debounce.350ms="search()" placeholder="Name, phone or member no." class="w-full rounded-lg sm:w-64 border border-[var(--line)] bg-black/40 px-3 py-2 text-[var(--text)] placeholder:text-[var(--dim)] focus:border-[var(--glow)] focus:outline-none" aria-label="Search contacts">
                    </div>
                </div>

                <div class="cc-mono -mx-5 mt-4 flex gap-2 overflow-x-auto px-5 pb-1 text-xs sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0" role="tablist">
                    <button type="button" @click="setKind('')" :class="kind === '' ? 'border-[var(--glow)] bg-[var(--glow)]/15 text-white' : 'border-[var(--line)] text-[var(--dim)] hover:text-white'" class="shrink-0 rounded-full border px-3 py-1.5 tracking-wider whitespace-nowrap uppercase">All <span class="text-[var(--glow)]" x-text="fmt(total)"></span></button>
                    <template x-for="[key, label] in Object.entries(kinds)" :key="key">
                        <button type="button" @click="setKind(key)" :class="kind === key ? 'border-[var(--glow)] bg-[var(--glow)]/15 text-white' : 'border-[var(--line)] text-[var(--dim)] hover:text-white'" class="shrink-0 rounded-full border px-3 py-1.5 tracking-wider whitespace-nowrap uppercase">
                            <span x-text="label + 's'"></span> <span class="text-[var(--glow)]" x-text="fmt(counts[key] ?? 0)"></span>
                        </button>
                    </template>
                </div>

                <ul class="mt-4 grid gap-2 md:grid-cols-2 2xl:grid-cols-3">
                    <template x-for="c in contacts" :key="c.id">
                        <li class="flex flex-wrap items-center gap-3 rounded-xl border border-[var(--line)] bg-black/25 p-3 transition-colors hover:border-[var(--glow)]/60 sm:flex-nowrap">
                            <div class="min-w-0 basis-full sm:basis-auto sm:flex-1">
                                <p class="flex items-center gap-2">
                                    <a :href="c.profile ?? '#'" :class="c.profile ? 'hover:underline' : 'pointer-events-none'" class="truncate font-semibold text-white" x-text="c.name"></a>
                                    <span x-show="c.on_shift" class="cc-mono inline-flex shrink-0 items-center gap-1 rounded bg-[var(--glow)]/15 px-1.5 py-0.5 text-[10px] font-bold text-[var(--glow)]"><span class="h-1.5 w-1.5 animate-pulse rounded-full bg-[var(--glow)]"></span>ON SHIFT</span>
                                </p>
                                <p class="cc-mono mt-0.5 truncate text-[11px] tracking-wider uppercase" :class="c.kind === 'member' ? 'text-[var(--dim)]' : 'text-[var(--amber)]'" x-text="c.label + (c.area ? ' · ' + c.area : '')"></p>
                                <p class="cc-mono mt-1 text-sm text-[var(--text)]" x-text="c.phone_display"></p>
                            </div>
                            <a :href="`tel:${c.phone}`" class="cc-mono inline-flex flex-1 shrink-0 items-center justify-center gap-1.5 rounded-lg bg-[var(--glow)] px-3 py-2.5 sm:flex-none sm:py-2 text-xs font-bold text-[#04140c] shadow-[0_0_14px_rgba(46,229,157,.45)] hover:brightness-110" :aria-label="`Call ${c.name}`">☎ Call</a>
                            <a :href="`https://wa.me/${c.whatsapp}`" target="_blank" rel="noopener" class="cc-mono inline-flex flex-1 shrink-0 items-center justify-center rounded-lg border border-[var(--glow)]/50 px-3 py-2.5 sm:flex-none sm:py-2 text-xs font-bold text-[var(--glow)] hover:bg-[var(--glow)]/10" :aria-label="`WhatsApp ${c.name}`">WhatsApp</a>
                        </li>
                    </template>
                </ul>
                <p x-show="!loading && !contacts.length" class="cc-mono mt-4 text-sm text-[var(--dim)]">No one matches.</p>
                <p x-show="error" x-cloak class="cc-mono mt-4 text-sm text-[var(--red)]">Could not load contacts. Try again.</p>
                <div class="mt-4 flex justify-center" x-show="hasMore">
                    <button type="button" @click="more()" :disabled="loading" class="cc-mono rounded-full border border-[var(--line)] px-4 py-2 text-xs tracking-wider text-[var(--text)] uppercase hover:border-[var(--glow)]">Load more</button>
                </div>
            </section>
        </div>

        {{-- Ticker --}}
        <div class="cc-mono fixed inset-x-0 bottom-0 z-30 flex items-center overflow-hidden border-t border-[var(--line)] bg-[#020805]/95 text-sm" aria-hidden="true">
            <span class="z-10 shrink-0 bg-[var(--red)] px-3 py-2 font-bold tracking-[0.2em] text-white">FLASH</span>
            <div class="overflow-hidden whitespace-nowrap">
                <div class="cc-ticker inline-flex">
                    <template x-for="n in 2" :key="n">
                        <span class="inline-flex">
                            <template x-for="(t, i) in ticker" :key="i"><span class="px-6 py-2 text-[var(--text)]"><span class="text-[var(--glow)]">◆</span> <span x-text="t"></span></span></template>
                        </span>
                    </template>
                </div>
            </div>
        </div>
    </div>
</x-layouts.base>
