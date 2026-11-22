@php
    use App\Http\Controllers\Web\Staff\TeamController;
    use App\Support\NigerianPhone;
    $credentials = session('credentials');
    $isAdmin = auth()->user()->isSuperAdmin();
@endphp
<x-layouts.app title="Field team" heading="Field team">
    <p class="mt-1 text-sm text-neutral-600">LGA coordinators, ward coordinators and polling unit agents. Add them here and share their sign-in details; they choose their own password the first time they sign in.</p>

    @if ($credentials)
        <section class="panel mt-4 border-2 border-accent p-5" x-data="{ copied: false }">
            <h2 class="font-display text-lg font-bold">Sign-in details for {{ $credentials['name'] }}</h2>
            <p class="mt-1 text-sm text-neutral-600">Shown only this once. Send them privately.</p>
            <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                <dt class="text-neutral-500">Phone</dt><dd class="font-semibold">{{ $credentials['phone'] }}</dd>
                <dt class="text-neutral-500">Password</dt><dd class="font-mono text-lg font-bold tracking-wider">{{ $credentials['password'] }}</dd>
            </dl>
            @if ($credentials['sms'])<p class="mt-2 text-sm font-semibold">{{ $credentials['sms'] }}</p>@endif
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ $credentials['whatsapp'] }}" target="_blank" rel="noopener" class="btn-primary"><x-icon name="chat" /> Send on WhatsApp</a>
                <button type="button" class="btn-secondary" x-on:click="navigator.clipboard.writeText(@js($credentials['message'])).then(() => copied = true)"><x-icon name="link" /> <span x-text="copied ? 'Copied' : 'Copy message'">Copy message</span></button>
            </div>
        </section>
    @endif

    <div class="mt-4 flex flex-wrap items-center gap-3">
        <form method="GET" class="flex flex-wrap gap-2">
            @if ($isAdmin)
                <select name="state_id" class="field py-2 text-sm" aria-label="State" onchange="this.form.submit()">
                    <option value="">All states</option>
                    @foreach ($states as $s)<option value="{{ $s->id }}" @selected($stateId === $s->id)>{{ $s->name }}</option>@endforeach
                </select>
            @endif
            <select name="type" class="field py-2 text-sm" aria-label="Role" onchange="this.form.submit()">
                <option value="">Everyone</option>
                @foreach ($types as $value => $label)<option value="{{ $value }}" @selected($type === $value)>{{ $label }}s</option>@endforeach
            </select>
        </form>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_380px]">
        <section class="panel self-start overflow-hidden">
            <ul class="divide-y divide-line">
                @forelse ($team as $person)
                    @php
                        $kind = TeamController::typeOf($person);
                        $manageable = $person->isCoordinator() || $person->added_by_user_id !== null;
                        $unit = $person->memberProfile?->pollingUnitLabel();
                    @endphp
                    <li class="flex flex-wrap items-center gap-3 p-4">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold">{{ $person->name }}
                                <span class="badge bg-brand/10 text-brand-dark">{{ $types[$kind] }}</span>
                                @unless ($person->is_active)<span class="badge bg-red-50 text-red-700">Deactivated</span>@endunless
                                @if ($person->must_change_password)<span class="badge bg-amber-50 text-amber-800">Not signed in yet</span>@endif
                            </p>
                            <p class="text-sm text-neutral-600">{{ NigerianPhone::format($person->phone) }}{{ $person->email ? ' · '.$person->email : '' }}</p>
                            <p class="text-xs text-neutral-500">{{ collect([$unit, $person->ward?->name ? $person->ward->name.' ward' : null, $person->lga?->name, $isAdmin ? $person->state?->name : null])->filter()->implode(', ') }}</p>
                        </div>
                        @if ($manageable)
                            <div class="flex flex-wrap gap-2">
                                <form method="POST" action="{{ staff_route('team.password', $person) }}" onsubmit="return confirm(@js('Give '.$person->firstName().' a new temporary password? Their old one stops working.'))">
                                    @csrf
                                    <button class="btn-secondary btn-sm"><x-icon name="key" class="h-4 w-4" /> New password</button>
                                </form>
                                <form method="POST" action="{{ staff_route('team.update', $person) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="is_active" value="{{ $person->is_active ? 0 : 1 }}">
                                    <button class="btn-secondary btn-sm {{ $person->is_active ? 'text-red-700' : '' }}">{{ $person->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                                </form>
                            </div>
                        @endif
                    </li>
                @empty
                    <li class="p-8 text-center text-neutral-500">Nobody on the field team yet.</li>
                @endforelse
            </ul>
            <div class="p-4">{{ $team->links() }}</div>
        </section>

        @if ($stateId)
            <form method="POST" action="{{ staff_route('team.store') }}" class="panel space-y-4 self-start p-5"
                x-data="{
                    type: @js(old('type', 'ward_coordinator')), lga: @js((string) old('lga_id', '')), ward: @js((string) old('ward_id', '')), unit: @js((string) old('polling_unit_id', '')),
                    wards: [], units: [], loading: false,
                    async get(url) { const r = await fetch(url, { headers: { Accept: 'application/json' } }); return r.ok ? ((await r.json()).data || []) : []; },
                    async loadWards(keep = false) { if (!keep) { this.ward = ''; this.unit = ''; } this.wards = []; this.units = []; if (!this.lga) return; this.loading = true; this.wards = await this.get(@js(route('geo.wards', '__ID__')).replace('__ID__', this.lga)); this.loading = false; },
                    async loadUnits(keep = false) { if (!keep) this.unit = ''; this.units = []; if (!this.ward) return; this.loading = true; this.units = await this.get(@js(route('geo.polling-units', '__ID__')).replace('__ID__', this.ward)); this.loading = false; },
                    async init() { if (this.lga) { const w = this.ward, u = this.unit; await this.loadWards(true); this.ward = w; if (w) { await this.loadUnits(true); this.unit = u; } } },
                }">
                @csrf
                @if ($isAdmin)<input type="hidden" name="state_id" value="{{ $stateId }}">@endif
                <h2 class="font-display text-lg font-bold">Add to the team</h2>
                <div>
                    <label class="label" for="t-type">Role</label>
                    <select id="t-type" name="type" class="field" x-model="type">
                        @foreach ($types as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                    @error('type')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div><label class="label" for="t-name">Full name</label><input id="t-name" name="name" class="field" required value="{{ old('name') }}">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="t-phone">Phone (they sign in with this)</label><input id="t-phone" name="phone" type="tel" class="field" required value="{{ old('phone') }}" placeholder="0803 000 0000">@error('phone')<p class="error">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="t-email">Email <span class="font-normal text-neutral-500">(optional)</span></label><input id="t-email" name="email" type="email" class="field" value="{{ old('email') }}">@error('email')<p class="error">{{ $message }}</p>@enderror</div>
                <div>
                    <label class="label" for="t-lga">LGA</label>
                    <select id="t-lga" name="lga_id" class="field" required x-model="lga" x-on:change="loadWards()">
                        <option value="">Choose</option>
                        @foreach ($lgas as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach
                    </select>
                    @error('lga_id')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div x-show="type !== 'lga_coordinator'">
                    <label class="label" for="t-ward">Ward</label>
                    <select id="t-ward" name="ward_id" class="field" x-model="ward" x-on:change="loadUnits()" :disabled="type === 'lga_coordinator' || !wards.length">
                        <option value="" x-text="loading ? 'Loading…' : (lga && !wards.length ? 'No wards listed for this LGA' : 'Choose')"></option>
                        <template x-for="w in wards" :key="w.id"><option :value="String(w.id)" x-text="w.name" :selected="String(w.id) === ward"></option></template>
                    </select>
                    @error('ward_id')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div x-show="type === 'polling_unit_agent'">
                    <label class="label" for="t-unit">Polling unit</label>
                    <select id="t-unit" name="polling_unit_id" class="field" x-show="units.length" x-model="unit" :disabled="type !== 'polling_unit_agent'">
                        <option value="">Choose</option>
                        <template x-for="u in units" :key="u.id"><option :value="String(u.id)" x-text="u.code + ' · ' + u.name" :selected="String(u.id) === unit"></option></template>
                        <option value="other">Not listed</option>
                    </select>
                    <input name="polling_unit_name" class="field" :class="units.length ? 'mt-2' : ''" maxlength="120" placeholder="Type the polling unit" value="{{ old('polling_unit_name') }}"
                        x-show="!units.length || unit === 'other'" :disabled="type !== 'polling_unit_agent'">
                    @error('polling_unit_id')<p class="error">{{ $message }}</p>@enderror
                    @error('polling_unit_name')<p class="error">{{ $message }}</p>@enderror
                    <p class="hint">Polling unit agents get the field work page, including registering people door to door.</p>
                </div>
                @if ($canSms)
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="send_sms" value="1" @checked(old('send_sms'))> Also send the details by SMS</label>
                @endif
                <button class="btn-primary w-full"><x-icon name="user-plus" /> Add and get sign-in details</button>
            </form>
        @else
            <p class="panel self-start p-5 text-sm text-neutral-600">Choose a state above to add people to its field team.</p>
        @endif
    </div>
</x-layouts.app>
