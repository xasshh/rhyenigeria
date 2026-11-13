<x-layouts.app title="National overview" heading="National overview" charts>
    <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="Total members" :value="$summary['total']" tone="brand" />
        <x-stat label="Today" :value="$summary['today']" />
        <x-stat label="This week" :value="$summary['week']" />
        <x-stat label="This month" :value="$summary['month']" />
        <x-stat label="Canvassers approved" :value="$summary['canvassers_approved']" />
        <x-stat label="Canvassers pending" :value="$summary['canvassers_pending']" :tone="$summary['canvassers_pending'] ? 'warn' : 'default'" />
        <x-stat label="Canvassers rejected" :value="$summary['canvassers_rejected']" />
        <x-stat label="PVC holders" :value="$summary['pvc']" :hint="number_format($summary['vin']).' with VIN'" />
    </div>

    <section class="panel mt-4 p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="font-display font-bold">Members across Nigeria</h2>
            <p class="text-sm text-neutral-500">Darker states have more members. Click a state to open its dashboard. Counts refresh every few minutes.</p>
        </div>
        <x-area-map class="mx-auto mt-4 max-w-4xl" :map="$map" :url="route('map.states')" key="code" link-key="id"
            :href="route('admin.states.show', '__KEY__')" action="Open :name"
            label="Map of Nigeria's 36 states and the FCT, coloured by number of members" />
    </section>

    <section class="panel mt-4 p-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-display font-bold">Registrations over time</h2>
            <div class="inline-flex rounded-lg border border-line p-0.5 text-sm">
                @foreach ([30, 60, 180] as $d)
                    <a href="{{ route('admin.dashboard', ['days' => $d]) }}" @class(['rounded-md px-3 py-1', 'bg-brand text-white' => count($trend['labels']) === $d, 'text-neutral-600' => count($trend['labels']) !== $d])>{{ $d }} days</a>
                @endforeach
            </div>
        </div>
        <div class="mt-3 h-64" data-chart='@json(['type' => 'line', 'labels' => $trend['labels'], 'values' => $trend['values']])'><canvas></canvas></div>
    </section>

    <div class="mt-4 grid gap-4 md:grid-cols-3">
        @foreach ([
            'Gender' => [collect(\App\Enums\Gender::cases())->map->label()->all(), collect(\App\Enums\Gender::cases())->map(fn ($g) => $gender[$g->value] ?? 0)->all()],
            'Age range' => [collect(\App\Enums\AgeRange::cases())->map->label()->all(), collect(\App\Enums\AgeRange::cases())->map(fn ($a) => $ages[$a->value] ?? 0)->all()],
            'PVC' => [['Has PVC', 'Not yet'], [$pvc[1] ?? $pvc['1'] ?? 0, $pvc[0] ?? $pvc['0'] ?? 0]],
        ] as $title => [$labels, $values])
            <section class="panel p-5">
                <h2 class="font-display font-bold">{{ $title }}</h2>
                <div class="mt-3 h-52" data-chart='@json(['type' => 'doughnut', 'labels' => $labels, 'values' => $values])'><canvas></canvas></div>
            </section>
        @endforeach
    </div>

    <section class="panel mt-4 overflow-hidden" x-data="{ key: 'total', dir: -1, rows: @js($states->values()),
        get sorted() { return [...this.rows].sort((a, b) => (a[this.key] > b[this.key] ? 1 : a[this.key] < b[this.key] ? -1 : 0) * this.dir) },
        sort(k) { this.dir = this.key === k ? -this.dir : (k === 'name' ? 1 : -1); this.key = k } }">
        <div class="flex items-center justify-between px-5 pt-5">
            <h2 class="font-display font-bold">By state</h2>
            <a href="{{ route('admin.members.export') }}" class="btn-secondary btn-sm"><x-icon name="download" class="h-4 w-4" /> Export all</a>
        </div>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full min-w-[560px] text-sm">
                <thead class="bg-surface text-left text-xs text-neutral-500 uppercase">
                    <tr>
                        @foreach (['name' => 'State', 'total' => 'Members', 'today' => 'Today', 'pvc' => 'PVC', 'canvassers' => 'Canvassers', 'pending' => 'Pending'] as $k => $label)
                            <th class="px-4 py-2.5 font-medium {{ $k === 'name' ? 'pl-5' : 'text-right' }}">
                                <button type="button" class="inline-flex items-center gap-1 uppercase" x-on:click="sort('{{ $k }}')">{{ $label }} <span x-show="key === '{{ $k }}'" x-text="dir > 0 ? '↑' : '↓'"></span></button>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <template x-for="row in sorted" :key="row.id">
                        <tr class="hover:bg-surface">
                            <td class="py-2.5 pr-4 pl-5"><a class="font-medium text-brand-dark hover:underline" :href="'{{ url('admin/states') }}/' + row.id" x-text="row.name"></a></td>
                            <td class="px-4 py-2.5 text-right font-semibold tabular-nums" x-text="row.total.toLocaleString()"></td>
                            <td class="px-4 py-2.5 text-right tabular-nums" x-text="row.today.toLocaleString()"></td>
                            <td class="px-4 py-2.5 text-right tabular-nums" x-text="row.pvc.toLocaleString()"></td>
                            <td class="px-4 py-2.5 text-right tabular-nums" x-text="row.canvassers.toLocaleString()"></td>
                            <td class="px-4 py-2.5 text-right tabular-nums" :class="row.pending ? 'text-amber-600 font-semibold' : ''" x-text="row.pending.toLocaleString()"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
            @if ($states->isEmpty())<p class="px-5 py-8 text-center text-neutral-500">No states imported yet. See “Importing geography” in the README.</p>@endif
        </div>
    </section>
</x-layouts.app>
