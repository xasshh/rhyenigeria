@php use App\Support\NigerianPhone; $isAdmin = auth()->user()->isSuperAdmin(); @endphp
<x-layouts.app title="Members" heading="Members">
    <form method="GET" class="panel mt-4 p-4" x-data>
        <div class="flex gap-2">
            <div class="relative flex-1">
                <x-icon name="search" class="pointer-events-none absolute top-3.5 left-3 h-4 w-4 text-neutral-400" />
                <input name="q" value="{{ $filters['q'] ?? '' }}" class="field pl-9" placeholder="Name, email, phone or member number">
            </div>
            <button class="btn-primary">Search</button>
        </div>
        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
            @if ($isAdmin)
                <select name="state_id" class="field py-2 text-sm" x-on:change="$el.form.lga_id && ($el.form.lga_id.value = ''); $el.form.ward_id && ($el.form.ward_id.value = ''); $el.form.submit()">
                    <option value="">All states</option>
                    @foreach ($states as $s)<option value="{{ $s->id }}" @selected(($filters['state_id'] ?? null) == $s->id)>{{ $s->name }}</option>@endforeach
                </select>
            @endif
            <select name="lga_id" class="field py-2 text-sm" x-on:change="$el.form.ward_id && ($el.form.ward_id.value = ''); $el.form.submit()" @disabled($lgas->isEmpty())>
                <option value="">All LGAs</option>
                @foreach ($lgas as $l)<option value="{{ $l->id }}" @selected(($filters['lga_id'] ?? null) == $l->id)>{{ $l->name }}</option>@endforeach
            </select>
            <select name="ward_id" class="field py-2 text-sm" x-on:change="$el.form.submit()" @disabled($wards->isEmpty())>
                <option value="">All wards</option>
                @foreach ($wards as $w)<option value="{{ $w->id }}" @selected(($filters['ward_id'] ?? null) == $w->id)>{{ $w->name }}</option>@endforeach
            </select>
            <select name="gender" class="field py-2 text-sm" x-on:change="$el.form.submit()">
                <option value="">Any gender</option>
                @foreach ($genders as $g)<option value="{{ $g->value }}" @selected(($filters['gender'] ?? null) === $g->value)>{{ $g->label() }}</option>@endforeach
            </select>
            <select name="age_range" class="field py-2 text-sm" x-on:change="$el.form.submit()">
                <option value="">Any age</option>
                @foreach ($ageRanges as $a)<option value="{{ $a->value }}" @selected(($filters['age_range'] ?? null) === $a->value)>{{ $a->label() }}</option>@endforeach
            </select>
            <select name="pvc" class="field py-2 text-sm" x-on:change="$el.form.submit()">
                <option value="">PVC: any</option>
                @foreach (['yes' => 'Has PVC', 'no' => 'No PVC yet', 'vin' => 'VIN added', 'no_vin' => 'No VIN'] as $v => $l)<option value="{{ $v }}" @selected(($filters['pvc'] ?? null) === $v)>{{ $l }}</option>@endforeach
            </select>
            <select name="canvasser" class="field py-2 text-sm" x-on:change="$el.form.submit()">
                <option value="">Canvasser: any</option>
                <option value="none" @selected(($filters['canvasser'] ?? null) === 'none')>Not applied</option>
                @foreach ($canvasserStatuses as $c)<option value="{{ $c->value }}" @selected(($filters['canvasser'] ?? null) === $c->value)>{{ $c->label() }}</option>@endforeach
            </select>
        </div>
    </form>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-neutral-600">{{ number_format($members->total()) }} {{ str('member')->plural($members->total()) }}</p>
        <form method="GET" action="{{ staff_route('members.export') }}" class="flex items-center gap-3">
            @foreach (array_filter($filters, fn ($v) => $v !== null && $v !== '') as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            @if ($isAdmin)
                <label class="flex items-center gap-1.5 text-sm text-neutral-700"><input type="checkbox" name="include_identifiers" value="1" class="h-4 w-4 accent-brand"> Include NIN & VIN</label>
            @endif
            <button class="btn-secondary btn-sm"><x-icon name="download" class="h-4 w-4" /> Export CSV</button>
        </form>
    </div>

    <div class="panel mt-3 overflow-hidden">
        <ul class="divide-y divide-line">
            @forelse ($members as $m)
                @php $p = $m->memberProfile; @endphp
                <li>
                    <a href="{{ staff_route('members.show', $m) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-surface sm:px-5">
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-x-2 font-semibold">
                                {{ $m->name }}
                                @if ($p->canvasser_status)<span @class(['badge', 'bg-brand text-white' => $p->canvasser_status->value === 'approved', 'bg-accent/20 text-[#7a5b00]' => $p->canvasser_status->value === 'pending', 'bg-neutral-100 text-neutral-600' => $p->canvasser_status->value === 'rejected'])>Canvasser · {{ $p->canvasser_status->label() }}</span>@endif
                            </p>
                            <p class="truncate text-sm text-neutral-600">{{ collect([$m->ward?->name, $m->lga?->name, $isAdmin ? $m->state?->name : null])->filter()->implode(', ') }}</p>
                            <p class="text-xs text-neutral-500"><span class="font-mono">{{ $p->member_number }}</span> · {{ NigerianPhone::mask($m->phone) }} · {{ $p->gender?->label() }}, {{ $p->age_range?->label() }} · {{ $p->hasVin() ? 'VIN added' : ($p->has_pvc ? 'PVC, no VIN' : 'No PVC') }}</p>
                        </div>
                        <span class="hidden text-xs text-neutral-500 sm:block">{{ $p->created_at->setTimezone(config('membership.timezone'))->format('j M') }}</span>
                        <x-icon name="arrow-right" class="h-4 w-4 text-neutral-400" />
                    </a>
                </li>
            @empty
                <li class="px-5 py-10 text-center text-neutral-500">No members match these filters.</li>
            @endforelse
        </ul>
    </div>
    <div class="mt-4">{{ $members->links() }}</div>
</x-layouts.app>
