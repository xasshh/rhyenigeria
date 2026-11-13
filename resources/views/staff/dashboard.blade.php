<x-layouts.app :title="$areaName ?? 'Dashboard'" charts>
    @if ($state)
        <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand"><x-icon name="arrow-left" class="h-4 w-4" /> National overview</a>
    @endif
    <p class="mt-1 text-sm text-neutral-500">{{ $state ? 'State' : 'Your area' }}</p>
    <h1 class="font-display text-2xl font-bold tracking-tight sm:text-[28px]">{{ $areaName ?? 'No area assigned' }}</h1>

    @if ($unassigned)
        <p class="panel mt-5 p-5 text-neutral-700">Your account has no state assigned yet, so there is nothing to show. Ask a super admin to assign your state.</p>
    @else
        <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-stat label="Members" :value="$summary['total']" tone="brand" />
            <x-stat label="New today" :value="$summary['today']" :hint="number_format($summary['week']).' this week'" />
            <x-stat label="PVC holders" :value="$summary['pvc']" :hint="number_format($summary['vin']).' with VIN'" />
            <x-stat label="Canvassers" :value="$summary['canvassers_approved']" :hint="number_format($summary['canvassers_pending']).' pending'" :tone="$summary['canvassers_pending'] ? 'warn' : 'default'" />
        </div>

        @if ($summary['canvassers_pending'] && ! $state)
            <a href="{{ staff_route('canvassers.index') }}" class="mt-4 flex items-center gap-3 rounded-2xl border border-accent/40 bg-accent/10 p-4 text-sm text-[#7a5b00]">
                <x-icon name="badge" /> <span class="flex-1"><strong>{{ $summary['canvassers_pending'] }}</strong> canvasser {{ str('application')->plural($summary['canvassers_pending']) }} waiting for review</span> <x-icon name="arrow-right" class="h-4 w-4" />
            </a>
        @endif

        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            <section class="panel p-5 lg:col-span-2">
                <h2 class="font-display font-bold">Sign-ups, last 30 days</h2>
                <div class="mt-3 h-56" data-chart='@json(['type' => 'bar', 'labels' => $trend['labels'], 'values' => $trend['values']])'><canvas></canvas></div>
            </section>
            <section class="panel p-5">
                <h2 class="font-display font-bold">Gender & age</h2>
                <div class="mt-3 space-y-2 text-sm">
                    @foreach (\App\Enums\Gender::cases() as $g)
                        @include('partials.bar', ['label' => $g->label(), 'n' => $gender[$g->value] ?? 0, 'of' => $summary['total']])
                    @endforeach
                    <hr class="border-line">
                    @foreach (\App\Enums\AgeRange::cases() as $a)
                        @include('partials.bar', ['label' => $a->label(), 'n' => $ages[$a->value] ?? 0, 'of' => $summary['total']])
                    @endforeach
                </div>
            </section>
        </div>

        @if ($lgaMap)
            <section class="panel mt-4 p-5">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="font-display font-bold">{{ $state?->name ?? auth()->user()->state?->name }} by LGA</h2>
                    <p class="text-sm text-neutral-500">Darker LGAs have more members. Click one to see its members.</p>
                </div>
                <x-area-map class="mx-auto mt-4 max-w-3xl" :map="$lgaMap" key="id"
                    :url="staff_route('map.lgas', array_filter(['state_id' => $state?->id]))"
                    :href="staff_route('members.index', array_filter(['lga_id' => '__KEY__', 'state_id' => $state?->id]))"
                    action="See members in :name" :label="'Map of '.($state?->name ?? auth()->user()->state?->name).' by LGA, coloured by number of members'" />
            </section>
        @endif

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            @foreach (['LGA' => [$byLga, 'lga_id'], 'Ward (top 50)' => [$byWard, 'ward_id']] as $title => [$rows, $param])
                <section class="panel overflow-hidden">
                    <h2 class="px-5 pt-5 font-display font-bold">By {{ $title }}</h2>
                    <div class="mt-3 max-h-96 overflow-auto">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 bg-white text-left text-xs text-neutral-500 uppercase"><tr><th class="px-5 py-2 font-medium">{{ str($title)->before(' ') }}</th><th class="px-2 py-2 text-right font-medium">Members</th><th class="px-2 py-2 text-right font-medium">PVC</th><th class="px-5 py-2 text-right font-medium">Canv.</th></tr></thead>
                            <tbody class="divide-y divide-line">
                                @forelse ($rows as $row)
                                    <tr class="hover:bg-surface">
                                        <td class="px-5 py-2">
                                            @if ($row->id)<a class="font-medium text-brand-dark hover:underline" href="{{ staff_route('members.index', array_filter([$param => $row->id, 'state_id' => $state?->id])) }}">{{ $row->name }}</a>@else<span class="text-neutral-500">Not given</span>@endif
                                        </td>
                                        <td class="px-2 py-2 text-right tabular-nums">{{ number_format($row->total) }}</td>
                                        <td class="px-2 py-2 text-right tabular-nums">{{ number_format($row->pvc) }}</td>
                                        <td class="px-5 py-2 text-right tabular-nums">{{ number_format($row->canvassers) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-5 py-6 text-center text-neutral-500">No members yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</x-layouts.app>
