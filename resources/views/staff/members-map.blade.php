<x-layouts.app title="Members map" map>
    <div x-data="membersMap(@js($config))">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm text-neutral-500">Where members pinned themselves</p>
                <h1 class="font-display text-2xl font-bold tracking-tight sm:text-[28px]">Members map{{ $stateName ? ' · '.$stateName : '' }}</h1>
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

        <div class="mt-4 grid grid-cols-3 gap-3">
            <x-stat label="Members" :value="$total" />
            <x-stat label="Pinned" :value="$pinned" tone="brand" />
            <x-stat label="Not pinned yet" :value="$total - $pinned" :hint="$total ? round(100 * $pinned / $total).'% pinned' : null" />
        </div>

        <section class="panel relative mt-4 overflow-hidden">
            <div x-ref="map" class="h-[62vh] min-h-[380px] w-full lg:h-[70vh]"></div>
            <p x-show="loading" class="absolute inset-x-0 bottom-10 mx-auto w-max rounded-full bg-white px-3 py-1 text-sm shadow">Loading…</p>
            <p x-show="error" x-text="error" x-cloak class="absolute inset-x-0 bottom-10 mx-auto w-max max-w-[90%] rounded-full bg-red-50 px-3 py-1 text-sm text-red-800 shadow"></p>
            <p x-show="truncated" x-cloak class="absolute inset-x-0 bottom-10 mx-auto w-max max-w-[90%] rounded-full bg-amber-soft px-3 py-1 text-sm text-amber-ink shadow">Showing the {{ number_format(\App\Http\Controllers\Web\Staff\MemberMapController::MAX_PINS) }} most recent pins. Pick a state to see all of its members.</p>
            <div class="absolute top-3 left-3 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl bg-white/95 px-3 py-2 text-xs shadow">
                <label class="flex cursor-pointer items-center gap-1.5"><input type="checkbox" class="accent-brand" x-model="show.member"> <span class="h-3 w-3 rounded-full" :style="`background:${colour('member')}`"></span> Members</label>
                <label class="flex cursor-pointer items-center gap-1.5"><input type="checkbox" class="accent-brand" x-model="show.canvasser"> <span class="h-3 w-3 rounded-full" :style="`background:${colour('canvasser')}`"></span> Canvassers</label>
                <span class="text-neutral-500" x-show="!loading" x-text="shown.toLocaleString() + ' shown'"></span>
            </div>
        </section>

        <p class="mt-4 text-xs text-neutral-500">Each pin is the place a member shared once, when they joined or later from their dashboard. It is not tracked and does not move; members can change or remove it at any time. Opening this map is recorded in the audit log. Map data © OpenStreetMap contributors.</p>
    </div>
</x-layouts.app>
