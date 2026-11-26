@php
    /** @var \Carbon\CarbonImmutable $electionDate */
    $countdown = \App\Support\Countdown::parts($electionDate);
    $scope = $state ? $state->name.' State' : 'Nigeria';
    $agents = site_term('Canvassers');
@endphp
<x-layouts.base title="Election Command Centre" noindex :map="true">
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
            .cc-hud { background: linear-gradient(to bottom, rgba(3,11,7,.85), transparent); }
            .cc-crosshair::before, .cc-crosshair::after { content: ''; position: absolute; background: rgba(46,229,157,.35); }
            .cc-crosshair::before { left: 50%; top: calc(50% - 14px); width: 1px; height: 28px; }
            .cc-crosshair::after { top: 50%; left: calc(50% - 14px); height: 1px; width: 28px; }
            .cc .maplibregl-ctrl-group { background: rgba(4,20,12,.9); border: 1px solid var(--line); }
            .cc .maplibregl-ctrl-group button + button { border-top-color: var(--line); }
            .cc .maplibregl-ctrl-group button .maplibregl-ctrl-icon { filter: invert(1) hue-rotate(180deg) brightness(1.3); }
            .cc .maplibregl-ctrl-attrib { background: rgba(3,11,7,.7); color: var(--dim); }
            .cc .maplibregl-ctrl-attrib a { color: var(--dim); }
            .cc-pop .maplibregl-popup-content { background: #04140c; color: var(--text); border: 1px solid rgba(46,229,157,.6); border-radius: 10px; box-shadow: 0 0 24px rgba(46,229,157,.3); font-family: ui-monospace, Menlo, monospace; font-size: 12px; padding: 10px 12px; }
            .cc-pop .maplibregl-popup-tip { border-top-color: #04140c !important; border-bottom-color: #04140c !important; }
            .cc-pop .maplibregl-popup-close-button { color: var(--dim); font-size: 16px; }
            .cc-pop-title { color: #fff; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: .06em; }
            .cc-pop-sub { color: var(--dim); margin-top: 2px; }
            .cc-pop-btn { display: inline-block; margin-top: 8px; background: var(--glow); color: #04140c; font-weight: 700; padding: 4px 10px; border-radius: 6px; }
            .cc-line { stroke-dasharray: 2000; stroke-dashoffset: 2000; animation: cc-draw 2.4s ease-out forwards; filter: drop-shadow(0 0 6px rgba(46,229,157,.8)); }
            .cc-ticker { animation: cc-marquee 60s linear infinite; }
            .cc-caret::after { content: '▌'; animation: cc-blink 1s steps(1) infinite; color: var(--glow); margin-left: 2px; }
            .cc-boot-line { opacity: 0; animation: cc-fade .3s ease-out forwards; }
            @keyframes cc-drift { to { background-position: 48px 48px; } }
            @keyframes cc-sweep { from { top: -20%; } to { top: 110%; } }
            @keyframes cc-bump { 0% { color: #fff; text-shadow: 0 0 28px rgba(46,229,157,1); } 100% { } }
            @keyframes cc-pulse { 70% { box-shadow: 0 0 0 10px rgba(255,77,79,0); } 100% { box-shadow: 0 0 0 0 rgba(255,77,79,0); } }
            @keyframes cc-spin { to { transform: rotate(360deg); } }
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

    <div id="cc-root" class="cc fixed inset-0 overflow-x-hidden overflow-y-auto" x-data="commandCentre(@js($config))">
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
                    <span class="hidden rounded-full border border-[var(--amber)]/40 px-3 py-1 text-[var(--amber)] lg:inline">CLEARANCE · {{ strtoupper($config['clearance']) }}</span>
                    <button type="button" @click="fullscreen()" class="hidden rounded-full sm:inline-block border border-[var(--line)] px-3 py-1 text-[var(--text)] hover:border-[var(--glow)] hover:text-white">⛶ Full screen</button>
                    <a href="{{ auth()->user()->homeRoute() }}" class="rounded-full border border-[var(--line)] px-3 py-1 text-[var(--dim)] hover:text-white">Exit</a>
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
                {{-- Live situation map --}}
                <section class="cc-panel overflow-hidden p-0 xl:col-span-7" aria-label="Live situation map" x-data="commandMap(@js($config))">
                    <div class="relative h-[440px] sm:h-[560px] xl:h-full xl:min-h-[640px]">
                        <div class="absolute inset-0"><div x-ref="canvas" class="h-full w-full"></div></div>
                        <div class="cc-radar pointer-events-none absolute top-1/2 left-1/2 aspect-square w-[130%] -translate-x-1/2 -translate-y-1/2 opacity-40" aria-hidden="true"></div>
                        <div class="cc-crosshair pointer-events-none absolute inset-0" aria-hidden="true"></div>

                        {{-- HUD --}}
                        <div class="cc-hud pointer-events-none absolute inset-x-0 top-0 p-3 sm:p-4">
                            <p class="cc-label flex items-center gap-2"><span class="cc-live inline-block h-2 w-2 rounded-full bg-[var(--red)]"></span>Live situation map · {{ $config['scope'] }}</p>
                            <p class="cc-mono mt-1 hidden text-[10px] tracking-wider text-[var(--glow)] sm:block" x-text="hud"></p>
                            <p class="cc-mono mt-1 text-[11px] text-[var(--dim)]">
                                <span class="text-[var(--glow)]" x-text="fmt(live.agents.length)"></span> agents placed ·
                                <span class="text-[var(--glow)]" x-text="fmt(live.totals?.active ?? 0)"></span> active ·
                                <span class="text-[var(--glow)]" x-text="fmt(live.activities.length)"></span> reports today ·
                                <span class="text-[var(--glow)]" x-text="fmt(heatCount)"></span> members pinned ·
                                <span class="text-[var(--red)]" x-text="fmt(incidentCount)"></span> open incidents
                            </p>
                        </div>

                        {{-- Layer switches --}}
                        <div class="cc-mono absolute bottom-3 left-3 right-14 flex flex-wrap gap-1.5 text-[10px] sm:text-[11px]" x-show="ready" x-cloak>
                            <button type="button" @click="toggle3d()" :class="three ? 'border-[var(--glow)] bg-[var(--glow)]/20 text-white' : 'border-[var(--line)] bg-black/60 text-[var(--dim)]'" class="rounded-full border px-2.5 py-1 tracking-wider uppercase backdrop-blur">3D</button>
                            <button type="button" @click="togglePatrol()" :class="patrol ? 'border-[var(--red)] bg-[var(--red)]/25 text-white' : 'border-[var(--line)] bg-black/60 text-[var(--dim)]'" class="rounded-full border px-2.5 py-1 tracking-wider uppercase backdrop-blur"><span x-text="patrol ? '■ Patrolling' : '▶ Patrol'"></span></button>
                            @foreach (['incidents' => ['Incidents', '#ff4d4f'], 'agents' => ['Agents', '#2ee59d'], 'reports' => ['Field reports', '#fb923c'], 'heat' => ['Members heat', '#22d3ee'], 'pulses' => ['Sign-ups today', '#f2b705']] as $layer => [$label, $dot])
                                <button type="button" @click="toggleLayer('{{ $layer }}')" :class="layers.{{ $layer }} ? 'border-[var(--glow)]/60 bg-black/60 text-white' : 'border-[var(--line)] bg-black/60 text-[var(--dim)] line-through'" class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 tracking-wider uppercase backdrop-blur"><span class="h-2 w-2 rounded-full" style="background: {{ $dot }}"></span>{{ $label }}</button>
                            @endforeach
                            <button type="button" @click="home()" class="rounded-full border border-[var(--line)] bg-black/60 px-2.5 py-1 tracking-wider text-[var(--dim)] uppercase backdrop-blur hover:text-white">⌂ Reset</button>
                        </div>

                        {{-- Hover read-out --}}
                        <div x-show="tip && !picked" x-cloak class="cc-mono pointer-events-none absolute z-10 w-max rounded-lg border border-[var(--glow)]/60 bg-[#04140c]/95 px-3 py-2 text-xs shadow-[0_0_20px_rgba(46,229,157,.35)]"
                             :style="tip && `left:${tip.x}px; top:${tip.y}px; transform: translate(-50%, calc(-100% - 14px))`">
                            <p class="text-sm font-bold tracking-wider text-white uppercase" x-text="tip?.name"></p>
                            <p class="text-[var(--glow)]"><span x-text="fmt(tip?.total)"></span> members</p>
                            <p class="text-[var(--amber)]">+<span x-text="fmt(tip?.today)"></span> today · <span x-text="fmt(tip?.agents)"></span> agents</p>
                        </div>

                        {{-- A picked area up close --}}
                        <div x-show="picked" x-cloak x-transition class="cc-mono absolute top-16 right-3 left-3 z-10 max-h-[70%] overflow-y-auto rounded-xl border border-[var(--glow)]/60 bg-[#04140c]/92 p-4 text-xs shadow-[0_0_30px_rgba(46,229,157,.3)] backdrop-blur sm:left-auto sm:w-72 sm:top-20">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="cc-label">Target · {{ $areaNoun }}</p>
                                    <p class="text-base font-bold tracking-wider text-white uppercase" x-text="picked?.name"></p>
                                </div>
                                <button type="button" @click="stopPatrol(); closePick()" class="text-lg leading-none text-[var(--dim)] hover:text-white" aria-label="Close">×</button>
                            </div>
                            <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                                <div class="rounded-md border border-[var(--line)] py-1.5"><p class="text-base font-bold text-white" x-text="fmt(picked?.total)"></p><p class="text-[9px] text-[var(--dim)] uppercase">Members</p></div>
                                <div class="rounded-md border border-[var(--line)] py-1.5"><p class="text-base font-bold text-[var(--amber)]" x-text="'+' + fmt(picked?.today)"></p><p class="text-[9px] text-[var(--dim)] uppercase">Today</p></div>
                                <div class="rounded-md border border-[var(--line)] py-1.5"><p class="text-base font-bold text-[var(--glow)]" x-text="fmt(picked?.agents)"></p><p class="text-[9px] text-[var(--dim)] uppercase">Agents</p></div>
                            </div>
                            <p class="cc-label mt-3" x-show="detail?.parts?.length">Top <span x-text="detail?.noun + 's'"></span></p>
                            <ul class="mt-1.5 space-y-1">
                                <template x-for="w in detail?.parts ?? []" :key="w.name">
                                    <li class="flex items-center justify-between gap-2"><span class="truncate uppercase" x-text="w.name"></span><span class="shrink-0"><span class="text-white" x-text="fmt(w.total)"></span><span class="ml-1 text-[var(--amber)]" x-show="w.today" x-text="'+' + w.today"></span></span></li>
                                </template>
                            </ul>
                            <p x-show="picked && !detail" class="mt-2 text-[var(--dim)]">Scanning…</p>
                            <button type="button" @click="callHere()" class="mt-3 w-full rounded-md bg-[var(--glow)] px-3 py-2 font-bold text-[#04140c]">☎ Call people here</button>
                        </div>

                        <div x-show="failed" x-cloak class="cc-mono absolute inset-0 flex items-center justify-center p-6 text-center text-sm text-[var(--dim)]">The live map could not start on this device (it needs WebGL). The figures on the rest of the page are still live.</div>
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

            {{-- Incidents reported from the field --}}
            <section id="incidents" class="cc-panel mt-3 scroll-mt-4 p-5" aria-label="Incidents" x-data="incidentDesk(@js(['url' => $config['incidents'], 'showUrl' => $config['incidentShow'], 'csrf' => csrf_token()]))">
                {{-- A new report just came in --}}
                <template x-teleport="#cc-root">
                    <div class="fixed inset-x-3 top-24 z-50 mx-auto max-w-xl space-y-2" x-show="alerts.length" x-cloak>
                    <template x-for="a in alerts" :key="a.id">
                        <button type="button" @click="open(a.id)" class="flex w-full items-center gap-3 rounded-xl border p-3 text-left shadow-[0_0_40px_rgba(255,77,94,.45)] backdrop-blur" :class="a.urgency === 'critical' ? 'border-[var(--red)] bg-[#1a0507]/95' : 'border-[var(--amber)] bg-[#1a1405]/95'">
                            <span class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-lg" :class="a.urgency === 'critical' ? 'bg-[var(--red)] text-white' : 'bg-[var(--amber)] text-black'"><span class="absolute inset-0 animate-ping rounded-full opacity-50" :class="a.urgency === 'critical' ? 'bg-[var(--red)]' : 'bg-[var(--amber)]'"></span>⚠</span>
                            <span class="min-w-0 flex-1">
                                <span class="cc-mono block text-[11px] font-bold tracking-[0.2em] uppercase" :class="a.urgency === 'critical' ? 'text-[var(--red)]' : 'text-[var(--amber)]'" x-text="'New incident · ' + a.urgency_label"></span>
                                <span class="block truncate font-semibold text-white" x-text="a.category_label"></span>
                                <span class="cc-mono block truncate text-xs text-[var(--text)]" x-text="a.place + ' · ' + a.by"></span>
                            </span>
                            <span class="cc-mono shrink-0 text-xs text-[var(--glow)]">Open →</span>
                        </button>
                    </template>
                </div>
                </template>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="cc-label flex items-center gap-2"><span class="inline-block h-2 w-2 rounded-full bg-[var(--red)]" :class="counts.new ? 'cc-live' : ''"></span>⚠ Incidents · live from the field</p>
                        <p class="cc-mono mt-1 text-xs text-[var(--dim)]"><span class="text-[var(--red)]" x-text="counts.new"></span> new · <span class="text-white" x-text="counts.open"></span> open · <span class="text-[var(--red)]" x-text="counts.critical"></span> urgent · <span x-text="counts.today"></span> reported today</p>
                    </div>
                    <div class="cc-mono flex gap-1.5 text-[11px]">
                        <button type="button" @click="setFilter('open')" :class="filter === 'open' ? 'border-[var(--glow)] bg-[var(--glow)]/15 text-white' : 'border-[var(--line)] text-[var(--dim)]'" class="rounded-full border px-3 py-1 tracking-wider uppercase">Open</button>
                        <button type="button" @click="setFilter('all')" :class="filter === 'all' ? 'border-[var(--glow)] bg-[var(--glow)]/15 text-white' : 'border-[var(--line)] text-[var(--dim)]'" class="rounded-full border px-3 py-1 tracking-wider uppercase">All</button>
                    </div>
                </div>

                <ul class="mt-4 grid gap-2 md:grid-cols-2 2xl:grid-cols-3">
                    <template x-for="i in incidents" :key="i.id">
                        <li>
                            <button type="button" @click="open(i.id)" class="w-full rounded-xl border bg-black/25 p-3 text-left transition-colors hover:border-[var(--glow)]/70" :class="i.status === 'new' ? (i.urgency === 'critical' ? 'border-[var(--red)] shadow-[0_0_18px_rgba(255,77,79,.35)]' : 'border-[var(--amber)]/70') : 'border-[var(--line)]'">
                                <p class="cc-mono flex flex-wrap items-center gap-1.5 text-[10px] tracking-wider uppercase">
                                    <span class="rounded px-1.5 py-0.5 font-bold" :class="urgencyClass(i)" x-text="i.urgency_label"></span>
                                    <span x-show="i.status === 'new'" class="rounded bg-[var(--red)]/20 px-1.5 py-0.5 font-bold text-[var(--red)]">New</span>
                                    <span x-show="i.status !== 'new'" class="text-[var(--dim)]" x-text="i.status_label"></span>
                                    <span class="ml-auto text-[var(--dim)]" x-text="(i.day ? i.day + ' ' : '') + i.time"></span>
                                </p>
                                <p class="mt-1.5 font-semibold text-white" x-text="i.category_label"></p>
                                <p class="mt-0.5 line-clamp-2 text-sm text-[var(--text)]" x-text="i.excerpt"></p>
                                <p class="cc-mono mt-1.5 truncate text-[11px] text-[var(--dim)]">
                                    <span x-text="i.place"></span> · <span x-text="i.by"></span>
                                    <span x-show="i.photo_count" class="text-[var(--glow)]" x-text="' · ▣ ' + i.photo_count"></span>
                                    <span x-show="i.update_count" x-text="' · ' + i.update_count + ' updates'"></span>
                                </p>
                            </button>
                        </li>
                    </template>
                </ul>
                <p x-show="!incidents.length" class="cc-mono mt-3 text-sm text-[var(--dim)]" x-text="filter === 'open' ? 'No open incidents. All quiet.' : 'No incidents reported yet.'"></p>
                <p x-show="error" x-cloak x-text="error" class="cc-mono mt-3 text-sm text-[var(--red)]"></p>

                {{-- One incident up close --}}
                <template x-teleport="#cc-root">
                    <div x-show="detail" x-cloak x-transition.opacity class="fixed inset-0 z-[60] overflow-y-auto bg-black/80 p-3 backdrop-blur-sm sm:p-6" @click.self="close()" role="dialog" aria-modal="true" aria-label="Incident">
                    <template x-if="detail">
                        <div class="cc-panel mx-auto max-w-5xl bg-[#04140c]! p-4 sm:p-6">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="cc-mono flex flex-wrap items-center gap-1.5 text-[10px] tracking-wider uppercase">
                                        <span class="rounded px-1.5 py-0.5 font-bold" :class="urgencyClass(detail)" x-text="detail.urgency_label"></span>
                                        <span class="text-[var(--dim)]" x-text="'Incident #' + detail.id + ' · ' + detail.status_label + ' · ' + (detail.day ? detail.day + ' ' : '') + detail.time"></span>
                                    </p>
                                    <h3 class="mt-1.5 text-xl font-bold text-white sm:text-2xl" x-text="detail.category_label"></h3>
                                    <p class="cc-mono mt-1 text-sm text-[var(--glow)]" x-text="detail.place"></p>
                                </div>
                                <button type="button" @click="close()" class="cc-mono shrink-0 rounded-lg border border-[var(--line)] px-3 py-1.5 text-sm text-[var(--dim)] hover:text-white" aria-label="Close">✕</button>
                            </div>

                            <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
                                <div>
                                    <template x-if="detail.photos.length">
                                        <div>
                                            <a :href="detail.photos[photo]" target="_blank" class="block overflow-hidden rounded-xl border border-[var(--line)] bg-black">
                                                <img :src="detail.photos[photo]" alt="Photo from the scene" class="max-h-[60vh] w-full object-contain">
                                            </a>
                                            <div class="mt-2 flex gap-2 overflow-x-auto" x-show="detail.photos.length > 1">
                                                <template x-for="(src, n) in detail.photos" :key="src">
                                                    <button type="button" @click="photo = n" class="shrink-0 overflow-hidden rounded-lg border-2" :class="photo === n ? 'border-[var(--glow)]' : 'border-transparent opacity-70'"><img :src="src" alt="" class="h-16 w-16 object-cover"></button>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                    <p x-show="!detail.photos.length" class="cc-mono rounded-xl border border-dashed border-[var(--line)] p-6 text-center text-sm text-[var(--dim)]">No photos sent with this report.</p>
                                    <p class="cc-label mt-5">What the reporter says</p>
                                    <p class="mt-2 text-[15px] leading-relaxed whitespace-pre-line text-white" x-text="detail.description"></p>

                                    <p class="cc-label mt-6" x-show="detail.timeline.length">Updates</p>
                                    <ol class="mt-2 space-y-2">
                                        <template x-for="u in detail.timeline" :key="u.id">
                                            <li class="rounded-lg border p-3 text-sm" :class="u.from_command ? 'border-[var(--glow)]/40 bg-[var(--glow)]/5' : 'border-[var(--amber)]/40 bg-[var(--amber)]/5'">
                                                <p class="cc-mono text-[11px] text-[var(--dim)]" x-text="(u.from_command ? 'Command centre · ' + (u.by ?? '') : 'Reporter') + ' · ' + u.time"></p>
                                                <p class="mt-1 whitespace-pre-line text-white" x-text="u.body"></p>
                                                <div class="mt-2 flex flex-wrap gap-2" x-show="u.photos.length">
                                                    <template x-for="src in u.photos" :key="src"><a :href="src" target="_blank"><img :src="src" alt="" class="h-20 w-20 rounded-lg object-cover"></a></template>
                                                </div>
                                            </li>
                                        </template>
                                    </ol>
                                </div>

                                <div class="space-y-4">
                                    <div class="rounded-xl border border-[var(--line)] bg-black/30 p-4">
                                        <p class="cc-label">Reported by</p>
                                        <p class="mt-1.5 text-lg font-semibold text-white"><a :href="detail.reporter.profile ?? '#'" :class="detail.reporter.profile ? 'hover:underline' : 'pointer-events-none'" x-text="detail.reporter.name"></a></p>
                                        <p class="cc-mono text-xs tracking-wider text-[var(--amber)] uppercase" x-text="detail.reporter.role + (detail.reporter.home ? ' · ' + detail.reporter.home : '')"></p>
                                        <p class="cc-mono mt-1 text-sm text-[var(--text)]" x-text="detail.reporter.phone_display + (detail.reporter.member_number ? ' · ' + detail.reporter.member_number : '')"></p>
                                        <div class="mt-3 grid grid-cols-2 gap-2" x-show="detail.reporter.phone">
                                            <a :href="'tel:' + detail.reporter.phone" @click="callReporter()" class="cc-mono rounded-lg bg-[var(--glow)] py-2.5 text-center text-xs font-bold text-[#04140c]">☎ Call</a>
                                            <a :href="'https://wa.me/' + detail.reporter.whatsapp" target="_blank" rel="noopener" class="cc-mono rounded-lg border border-[var(--glow)]/50 py-2.5 text-center text-xs font-bold text-[var(--glow)]">WhatsApp</a>
                                        </div>
                                    </div>

                                    <div class="rounded-xl border border-[var(--line)] bg-black/30 p-4">
                                        <p class="cc-label">Location</p>
                                        <p class="cc-mono mt-1.5 text-sm text-[var(--text)]" x-text="detail.location ? detail.location.lat.toFixed(5) + ', ' + detail.location.lng.toFixed(5) + (detail.location.accuracy ? ' (±' + detail.location.accuracy + ' m)' : '') : 'No GPS location sent; shown at the middle of the LGA.'"></p>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <button type="button" @click="showOnMap()" class="cc-mono rounded-lg border border-[var(--glow)]/50 px-3 py-2 text-xs font-bold text-[var(--glow)]">◎ Show on the live map</button>
                                            <a x-show="detail.location" :href="detail.location ? `https://www.google.com/maps?q=${detail.location.lat},${detail.location.lng}` : '#'" target="_blank" rel="noopener" class="cc-mono rounded-lg border border-[var(--line)] px-3 py-2 text-xs text-[var(--dim)] hover:text-white">Directions ↗</a>
                                        </div>
                                    </div>

                                    <div class="rounded-xl border border-[var(--line)] bg-black/30 p-4">
                                        <p class="cc-label">Action</p>
                                        <p class="cc-mono mt-1 text-[11px] text-[var(--dim)]" x-show="detail.handler" x-text="'Handled by ' + detail.handler"></p>
                                        <textarea x-model="note" rows="2" maxlength="1000" placeholder="Add a note: who was sent, what was done… (the reporter sees it)" class="mt-2 w-full rounded-lg border border-[var(--line)] bg-black/40 px-3 py-2 text-sm text-[var(--text)] placeholder:text-[var(--dim)] focus:border-[var(--glow)] focus:outline-none"></textarea>
                                        <div class="cc-mono mt-2 grid grid-cols-2 gap-2 text-xs">
                                            <button type="button" @click="act(null)" :disabled="saving || !note.trim()" class="rounded-lg border border-[var(--glow)]/50 py-2 font-bold text-[var(--glow)] disabled:opacity-40">Add note</button>
                                            <template x-if="['new', 'seen'].includes(detail.status)">
                                                <button type="button" @click="act('resolved')" :disabled="saving" class="rounded-lg bg-[var(--glow)] py-2 font-bold text-[#04140c]">✓ Resolved</button>
                                            </template>
                                            <template x-if="['new', 'seen'].includes(detail.status)">
                                                <button type="button" @click="act('dismissed')" :disabled="saving" class="col-span-2 rounded-lg border border-[var(--line)] py-2 text-[var(--dim)] hover:text-white">Close: not a real incident</button>
                                            </template>
                                            <template x-if="!['new', 'seen'].includes(detail.status)">
                                                <button type="button" @click="act('seen')" :disabled="saving" class="rounded-lg border border-[var(--amber)]/60 py-2 font-bold text-[var(--amber)]">Reopen</button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                </template>
            </section>

            {{-- News wire --}}
            <section class="cc-panel mt-3 p-5" aria-label="News wire" x-data="newsWire(@js(['url' => $config['news']]))">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="cc-label flex items-center gap-2"><span class="cc-live inline-block h-2 w-2 rounded-full bg-[var(--red)]"></span>News wire · elections and {{ $state?->name ?? 'Nigeria' }}</p>
                    <div class="cc-mono -mx-5 flex gap-1.5 overflow-x-auto px-5 text-[11px] sm:mx-0 sm:px-0">
                        <button type="button" @click="topic = ''" :class="topic === '' ? 'border-[var(--glow)] bg-[var(--glow)]/15 text-white' : 'border-[var(--line)] text-[var(--dim)]'" class="shrink-0 rounded-full border px-2.5 py-1 tracking-wider uppercase">All</button>
                        <template x-for="t in topics" :key="t">
                            <button type="button" @click="topic = t" :class="topic === t ? 'border-[var(--glow)] bg-[var(--glow)]/15 text-white' : 'border-[var(--line)] text-[var(--dim)]'" class="shrink-0 rounded-full border px-2.5 py-1 tracking-wider whitespace-nowrap uppercase" x-text="t"></button>
                        </template>
                    </div>
                </div>
                <ul class="mt-3 grid gap-x-6 lg:grid-cols-2">
                    <template x-for="n in shown" :key="n.url">
                        <li class="border-b border-[var(--line)] py-2.5">
                            <a :href="n.url" target="_blank" rel="noopener noreferrer" class="group block">
                                <p class="cc-mono flex flex-wrap items-center gap-2 text-[10px] tracking-wider uppercase">
                                    <span x-show="breaking(n)" class="rounded bg-[var(--red)] px-1.5 py-0.5 font-bold text-white">Breaking</span>
                                    <span x-show="n.fresh" class="rounded bg-[var(--glow)] px-1.5 py-0.5 font-bold text-[#04140c]">New</span>
                                    <span class="text-[var(--glow)]" x-text="n.source"></span>
                                    <span class="text-[var(--dim)]" x-text="ago(n) + ' · ' + n.topic"></span>
                                </p>
                                <p class="mt-1 text-sm leading-snug text-white group-hover:text-[var(--glow)] group-hover:underline" x-text="n.title"></p>
                            </a>
                        </li>
                    </template>
                </ul>
                <p x-show="!items.length" class="cc-mono mt-3 text-sm text-[var(--dim)]">Gathering headlines…</p>
                <p class="cc-mono mt-3 text-[10px] text-[var(--dim)]">Headlines from Google News searches, checked every 10 minutes. Stories open on the publisher's site.</p>
            </section>

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
                        <li x-show="!(data.activities ?? []).length" class="py-2 text-[var(--dim)]">No field reports yet today. <a href="{{ staff_route('live') }}" class="text-[var(--glow)] underline">Open the live map</a></li>
                    </ul>
                </section>
            </div>

            {{-- Phone line (Africa's Talking) --}}
            <section id="phone-line" class="cc-panel mt-3 scroll-mt-4 p-5" aria-label="Phone line" x-data="phoneLine(@js($line))" @cc-ring.window="ring($event.detail.payload, $event.detail.name)" @cc-dial.window="dialled($event.detail.payload)">
                {{-- Someone is ringing the line right now --}}
                <template x-teleport="#cc-root">
                    <div x-show="live.length" x-cloak x-transition class="fixed inset-x-3 top-3 z-50 mx-auto max-w-xl rounded-xl border border-[var(--red)] bg-[#1a0507]/95 p-4 shadow-[0_0_40px_rgba(255,77,94,.45)] backdrop-blur" role="alert">
                    <template x-for="c in live" :key="c.id">
                        <div class="flex items-center gap-3">
                            <span class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[var(--red)] text-lg text-white"><span class="absolute inset-0 animate-ping rounded-full bg-[var(--red)]/60"></span>☎</span>
                            <div class="min-w-0">
                                <p class="cc-mono text-[11px] font-bold tracking-[0.2em] text-[var(--red)] uppercase" x-text="c.status === 'connected' ? 'On the line now' : 'Incoming call'"></p>
                                <p class="truncate font-semibold text-white" x-text="c.name || 'Unknown caller'"></p>
                                <p class="cc-mono truncate text-xs text-[var(--text)]" x-text="c.phone_display + (c.area ? ' · ' + c.area : '')"></p>
                            </div>
                        </div>
                    </template>
                </div>
                </template>

                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="cc-label">☏ Phone line <span x-show="waiting" x-cloak class="ml-2 rounded bg-[var(--amber)]/20 px-1.5 py-0.5 text-[var(--amber)]" x-text="waiting + ' to follow up'"></span></p>
                        @if ($line['enabled'])
                            <p class="mt-1 text-xs text-[var(--dim)]">Campaign number <span class="cc-mono text-[var(--glow)]">{{ $line['number'] }}</span>. Press <span class="text-[var(--glow)]">Call</span> next to anyone and the command centre phone@if ($line['deskPhone']) <span class="cc-mono text-[var(--glow)]">({{ $line['deskPhone'] }})</span>@endif rings — pick it up and you are connected. When someone calls the campaign number it rings that same phone and shows here. Every call is recorded.</p>
                        @else
                            <p class="mt-1 text-xs text-[var(--amber)]">The campaign phone line is not connected yet. Until it is, Call uses your own phone and the call is logged here, so mark how it went when you hang up. When the hotline phone rings, log the call with <span class="text-[var(--glow)]">+ Log incoming call</span>.</p>
                        @endif
                    </div>
                    @unless ($line['enabled'])<button type="button" @click="logOpen = !logOpen" class="cc-mono w-full shrink-0 rounded-lg border border-[var(--glow)]/50 px-3 py-2 text-xs font-bold text-[var(--glow)] hover:bg-[var(--glow)]/10 sm:w-auto" :aria-expanded="logOpen">+ Log incoming call</button>@endunless
                </div>

                <form x-show="logOpen" x-cloak x-transition @submit.prevent="logIncoming()" class="mt-4 grid gap-3 rounded-xl border border-[var(--line)] bg-black/25 p-4 sm:grid-cols-[12rem_auto_1fr_auto] sm:items-end">
                    <label class="cc-mono flex flex-col gap-1 text-[11px] tracking-wider text-[var(--dim)] uppercase">
                        Caller's number
                        <input type="tel" x-model="log.phone" required placeholder="0803 000 0000" class="rounded-lg border border-[var(--line)] bg-black/40 px-3 py-2 text-sm tracking-normal text-[var(--text)] normal-case placeholder:text-[var(--dim)] focus:border-[var(--glow)] focus:outline-none">
                    </label>
                    <div class="cc-mono flex gap-1 text-xs" role="radiogroup" aria-label="Was the call answered?">
                        <button type="button" @click="log.answered = true" :class="log.answered ? 'border-[var(--glow)] bg-[var(--glow)]/15 text-white' : 'border-[var(--line)] text-[var(--dim)]'" class="flex-1 rounded-lg border px-3 py-2" :aria-checked="log.answered" role="radio">Answered</button>
                        <button type="button" @click="log.answered = false" :class="!log.answered ? 'border-[var(--amber)] bg-[var(--amber)]/15 text-white' : 'border-[var(--line)] text-[var(--dim)]'" class="flex-1 rounded-lg border px-3 py-2" :aria-checked="!log.answered" role="radio">Missed</button>
                    </div>
                    <label class="cc-mono flex flex-col gap-1 text-[11px] tracking-wider text-[var(--dim)] uppercase">
                        What it was about (optional)
                        <input type="text" x-model="log.note" maxlength="255" placeholder="e.g. Agents in Zaria need materials" class="rounded-lg border border-[var(--line)] bg-black/40 px-3 py-2 text-sm tracking-normal text-[var(--text)] normal-case placeholder:text-[var(--dim)] focus:border-[var(--glow)] focus:outline-none">
                    </label>
                    <button type="submit" class="cc-mono rounded-lg bg-[var(--glow)] px-4 py-2 text-xs font-bold text-[#04140c] hover:brightness-110">Save</button>
                    <p x-show="logError" x-text="logError" class="cc-mono text-sm text-[var(--red)] sm:col-span-4"></p>
                </form>

                <p x-show="notice" x-cloak x-transition class="cc-mono mt-3 rounded-lg border px-3 py-2 text-sm" :class="noticeError ? 'border-[var(--red)]/60 text-[var(--red)]' : 'border-[var(--glow)]/50 text-[var(--glow)]'" x-text="notice" role="status"></p>

                <ul class="mt-4 divide-y divide-[var(--line)] text-sm">
                    <template x-for="c in calls" :key="c.id">
                        <li class="flex flex-wrap items-center gap-x-3 gap-y-2 py-2.5">
                            <span class="cc-mono w-5 shrink-0 text-center text-base" :class="c.direction === 'inbound' ? 'text-[var(--amber)]' : 'text-[var(--glow)]'" :title="c.direction === 'inbound' ? 'Called us' : 'We called'" x-text="c.direction === 'inbound' ? '↙' : '↗'"></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate"><span class="font-semibold text-white" x-text="c.name || c.phone_display"></span> <span class="text-[var(--dim)]" x-show="c.name" x-text="'· ' + c.phone_display"></span></p>
                                <p class="cc-mono truncate text-[11px] text-[var(--dim)]">
                                    <span x-text="(c.day ? c.day + ' ' : '') + c.time"></span><span x-show="c.area" x-text="' · ' + c.area"></span><span x-show="c.operator" x-text="' · by ' + c.operator"></span><span x-show="c.duration" x-text="' · ' + duration(c.duration)"></span><span x-show="c.channel === 'phone'"> · own phone</span>
                                </p>
                                <p x-show="c.note" class="mt-0.5 text-xs text-[var(--text)]" x-text="c.note"></p>
                            </div>
                            <span class="cc-mono shrink-0 rounded px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase" :class="statusClass(c)" x-text="statusLabel(c)"></span>
                            {{-- An own-phone call: how did it go? --}}
                            <div class="cc-mono grid w-full grid-cols-2 gap-1.5 text-[11px] sm:flex sm:w-auto" x-show="c.status === 'dialled'">
                                <button type="button" @click="setOutcome(c, 'reached')" class="rounded-lg bg-[var(--glow)] px-2.5 py-1.5 font-bold text-[#04140c] hover:brightness-110">Reached</button>
                                <button type="button" @click="setOutcome(c, 'no_answer')" class="rounded-lg border border-[var(--line)] px-2.5 py-1.5 text-[var(--text)] hover:border-[var(--amber)]">No answer</button>
                                <button type="button" @click="setOutcome(c, 'wrong_number')" class="rounded-lg border border-[var(--line)] px-2.5 py-1.5 text-[var(--text)] hover:border-[var(--red)]">Wrong number</button>
                                <button type="button" @click="setOutcome(c, 'call_later')" class="rounded-lg border border-[var(--line)] px-2.5 py-1.5 text-[var(--text)] hover:border-[var(--amber)]">Call later</button>
                            </div>
                            <div class="flex w-full items-center gap-2 sm:w-auto" x-show="c.recording || c.needs_call_back">
                                <template x-if="c.recording">
                                    <div class="flex w-full items-center gap-2 sm:w-auto">
                                        <span class="cc-mono shrink-0 text-[11px] font-bold tracking-wider text-[var(--amber)] uppercase" x-text="c.direction === 'inbound' && c.status !== 'completed' ? '🎙 Voice message' : '● Recording'"></span>
                                        <audio :src="c.recording" controls preload="none" class="h-9 w-full min-w-[200px] sm:w-[260px]"></audio>
                                    </div>
                                </template>
                                <button type="button" x-show="enabled && c.needs_call_back" @click="callBack(c)" :disabled="busy" class="cc-mono shrink-0 rounded-lg bg-[var(--glow)] px-3 py-1.5 text-xs font-bold text-[#04140c] hover:brightness-110 disabled:opacity-50">☎ Call back</button>
                                <a x-show="!enabled && c.needs_call_back && c.phone.startsWith('+')" :href="`tel:${c.phone}`" @click="dialled({ call_id: c.id })" class="cc-mono shrink-0 rounded-lg bg-[var(--glow)] px-3 py-1.5 text-xs font-bold text-[#04140c] hover:brightness-110">☎ Call back</a>
                                <button type="button" x-show="c.needs_call_back" @click="markHandled(c)" class="cc-mono shrink-0 rounded-lg border border-[var(--line)] px-2.5 py-1.5 text-[11px] text-[var(--dim)] hover:text-white">Done</button>
                            </div>
                        </li>
                    </template>
                </ul>
                <p x-show="!calls.length" class="cc-mono mt-2 text-sm text-[var(--dim)]">No calls yet.</p>
            </section>

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
                            @if ($line['enabled'])
                                <button type="button" @click="$dispatch('cc-ring', { payload: { user_id: c.id }, name: c.name })" class="cc-mono inline-flex flex-1 shrink-0 items-center justify-center gap-1.5 rounded-lg bg-[var(--glow)] px-3 py-2.5 sm:flex-none sm:py-2 text-xs font-bold text-[#04140c] shadow-[0_0_14px_rgba(46,229,157,.45)] hover:brightness-110" :aria-label="`Call ${c.name} on the campaign line`">☎ Call</button>
                            @else
                                <a :href="`tel:${c.phone}`" @click="$dispatch('cc-dial', { payload: { user_id: c.id } })" class="cc-mono inline-flex flex-1 shrink-0 items-center justify-center gap-1.5 rounded-lg bg-[var(--glow)] px-3 py-2.5 sm:flex-none sm:py-2 text-xs font-bold text-[#04140c] shadow-[0_0_14px_rgba(46,229,157,.45)] hover:brightness-110" :aria-label="`Call ${c.name}`">☎ Call</a>
                            @endif
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
