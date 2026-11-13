<x-layouts.app title="Live map" map>
    <div x-data="liveMap(@js($config))">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm text-neutral-500">Canvassers in the field today</p>
                <h1 class="font-display text-2xl font-bold tracking-tight sm:text-[28px]">Live map{{ $stateName ? ' · '.$stateName : '' }}</h1>
            </div>
            @if ($states->isNotEmpty())
                <form method="GET" class="flex items-center gap-2">
                    <label class="sr-only" for="state_id">State</label>
                    <select id="state_id" name="state_id" class="field py-2 text-sm" onchange="this.form.submit()">
                        <option value="">All states</option>
                        @foreach ($states as $s)
                            <option value="{{ $s->id }}" @selected($stateId === $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach (['on_shift' => 'On shift now', 'active' => 'Active (last '.$config['activeMinutes'].' min)', 'activities' => 'Activities today', 'households' => 'Households reached'] as $key => $label)
                <div class="panel p-4">
                    <p class="text-xs font-medium tracking-wide text-neutral-500 uppercase">{{ $label }}</p>
                    <p class="mt-1 font-display text-2xl font-bold tabular-nums" x-text="(data.totals['{{ $key }}'] ?? 0).toLocaleString()">0</p>
                </div>
            @endforeach
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
            <section class="panel relative overflow-hidden">
                <div x-ref="map" class="h-[60vh] min-h-[380px] w-full lg:h-[68vh]"></div>
                <p x-show="loading" class="absolute inset-x-0 bottom-10 mx-auto w-max rounded-full bg-white px-3 py-1 text-sm shadow">Loading…</p>
                <p x-show="error" x-text="error" x-cloak class="absolute inset-x-0 bottom-10 mx-auto w-max rounded-full bg-red-50 px-3 py-1 text-sm text-red-800 shadow"></p>
                <div class="pointer-events-none absolute top-3 left-3 flex max-w-[calc(100%-4.5rem)] flex-wrap gap-x-3 gap-y-1 rounded-xl bg-white/95 px-3 py-2 text-[11px] shadow sm:text-xs">
                    <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full" style="background:#2e7d3f"></span> Active</span>
                    <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full" style="background:#f2b705"></span> On shift, quiet</span>
                    <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full" style="background:#9aa69e"></span> Off shift</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background:#2563eb"></span> Door-to-door</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background:#c026d3"></span> Check-in</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background:#ea580c"></span> Report</span>
                </div>
            </section>

            <aside class="panel flex max-h-[68vh] flex-col overflow-hidden">
                <div class="flex items-center justify-between gap-2 border-b border-line p-3">
                    <div class="inline-flex rounded-lg border border-line p-0.5 text-sm">
                        <button type="button" class="rounded-md px-3 py-1" :class="filter === 'on_shift' ? 'bg-brand text-white' : 'text-neutral-600'" x-on:click="filter = 'on_shift'">On shift</button>
                        <button type="button" class="rounded-md px-3 py-1" :class="filter === 'all' ? 'bg-brand text-white' : 'text-neutral-600'" x-on:click="filter = 'all'">All canvassers</button>
                    </div>
                    <span class="text-xs text-neutral-500" x-text="updatedAt ? 'Updated ' + updatedAt.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : ''"></span>
                </div>
                <ul class="flex-1 divide-y divide-line overflow-y-auto">
                    <template x-for="c in list" :key="c.id">
                        <li>
                            <button type="button" class="w-full p-3 text-left hover:bg-surface" :class="selected === c.id ? 'bg-brand/10' : ''" x-on:click="select(c.id)">
                                <span class="flex items-center gap-2">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="`background:${colour(c.status)}`"></span>
                                    <span class="truncate font-semibold" x-text="c.name"></span>
                                </span>
                                <span class="mt-0.5 block text-xs text-neutral-500" x-text="[c.seen_ward || c.area, seen(c)].filter(Boolean).join(' · ')"></span>
                                <span class="mt-1 block text-xs text-neutral-700" x-text="`${c.distance_km} km walked · ${c.activities} logged` + (c.households ? ` · ${c.households} households` : '')"></span>
                                <template x-for="f in c.flags"><span class="mt-1 mr-1 inline-block rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-700" x-text="f"></span></template>
                            </button>
                            <div class="px-3 pb-3 text-xs" x-show="selected === c.id">
                                <a :href="memberUrl(c.id)" class="font-semibold text-brand hover:underline">Open profile</a>
                                <span class="text-neutral-500"> · today's route is shown on the map</span>
                            </div>
                        </li>
                    </template>
                    <li class="p-4 text-sm text-neutral-500" x-show="!loading && !list.length" x-text="filter === 'on_shift' ? 'Nobody is on shift right now.' : 'No approved canvassers yet.'"></li>
                </ul>
            </aside>
        </div>

        <section class="panel mt-4 overflow-hidden">
            <h2 class="px-5 pt-5 font-display font-bold">Activity today</h2>
            <ul class="mt-3 max-h-96 divide-y divide-line overflow-y-auto">
                <template x-for="a in data.activities" :key="a.id">
                    <li class="flex items-start gap-3 px-5 py-3 text-sm">
                        <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full" :style="`background:${kindColour(a.kind)}`"></span>
                        <span class="w-14 shrink-0 text-neutral-500" x-text="a.time"></span>
                        <span class="min-w-0 flex-1">
                            <span class="font-semibold" x-text="a.label"></span>
                            <span class="text-neutral-600" x-text="' · ' + a.canvasser + (a.ward ? ', ' + a.ward : '')"></span>
                            <span class="block text-neutral-700" x-text="a.kind === 'door_to_door' ? `${a.households} households · ${a.outcome}` + (a.note ? ` · ${a.note}` : '') : (a.kind === 'event_check_in' ? a.event : a.note)"></span>
                            <a x-show="a.photo" :href="a.photo" target="_blank" rel="noopener" class="text-xs font-semibold text-brand hover:underline">View photo</a>
                        </span>
                    </li>
                </template>
                <li class="px-5 py-4 text-sm text-neutral-500" x-show="!data.activities.length">Nothing logged yet today.</li>
            </ul>
        </section>

        <p class="mt-4 text-xs text-neutral-500">Canvassers share their location only during shifts they start themselves, after agreeing to it. Opening a route is recorded in the audit log. Map data © OpenStreetMap contributors.</p>
    </div>
</x-layouts.app>
