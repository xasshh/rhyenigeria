@php use App\Support\NigerianPhone; @endphp
<x-layouts.app title="Coordinators" heading="State coordinators">
    <div class="mt-5 grid gap-4 lg:grid-cols-[1fr_380px]">
        <section class="panel overflow-hidden">
            <ul class="divide-y divide-line">
                @forelse ($coordinators as $c)
                    <li class="flex flex-wrap items-center gap-3 p-4">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold">{{ $c->name }} @unless ($c->is_active)<span class="badge bg-red-50 text-red-700">Deactivated</span>@endunless</p>
                            <p class="text-sm break-all text-neutral-600">{{ $c->email }} · {{ NigerianPhone::format($c->phone) }}</p>
                            <p class="text-xs text-neutral-500">{{ $c->ward?->name ?? $c->lga?->name ?? $c->state?->name ?? 'No area' }}{{ $c->lga_id ? ' (LGA/ward-level: API only)' : '' }}</p>
                        </div>
                        <form method="POST" action="{{ route('admin.coordinators.update', $c) }}" class="flex items-center gap-2">
                            @csrf @method('PATCH')
                            <select name="state_id" class="field w-40 py-1.5 text-sm" aria-label="State for {{ $c->name }}" onchange="this.form.submit()">
                                <option value="" disabled @selected(! $c->state_id)>Choose state</option>
                                @foreach ($states as $s)<option value="{{ $s->id }}" @selected($c->state_id === $s->id && ! $c->lga_id)>{{ $s->name }}</option>@endforeach
                            </select>
                        </form>
                        <form method="POST" action="{{ route('admin.coordinators.update', $c) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="is_active" value="{{ $c->is_active ? 0 : 1 }}">
                            <button class="btn-secondary btn-sm {{ $c->is_active ? 'text-red-700' : '' }}">{{ $c->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                        </form>
                    </li>
                @empty
                    <li class="p-8 text-center text-neutral-500">No coordinators yet.</li>
                @endforelse
            </ul>
        </section>

        <form method="POST" action="{{ route('admin.coordinators.store') }}" class="panel space-y-4 self-start p-5">
            @csrf
            <h2 class="font-display text-lg font-bold">Add a coordinator</h2>
            <div><label class="label" for="c-name">Full name</label><input id="c-name" name="name" class="field" required value="{{ old('name') }}">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="c-email">Email (they sign in with this)</label><input id="c-email" name="email" type="email" class="field" required value="{{ old('email') }}">@error('email')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="c-phone">Phone</label><input id="c-phone" name="phone" type="tel" class="field" value="{{ old('phone') }}">@error('phone')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="c-state">State</label>
                <select id="c-state" name="state_id" class="field" required><option value="">Choose state</option>@foreach ($states as $s)<option value="{{ $s->id }}" @selected(old('state_id') == $s->id)>{{ $s->name }}</option>@endforeach</select>
                @error('state_id')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="c-pass">Temporary password</label><input id="c-pass" name="password" type="password" class="field" required autocomplete="new-password" placeholder="At least 10 characters">@error('password')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="c-pass2">Type it again</label><input id="c-pass2" name="password_confirmation" type="password" class="field" required autocomplete="new-password"></div>
            <p class="hint">Share the password privately. They can change it with “Forgot password” once email is set up.</p>
            <button class="btn-primary w-full">Create coordinator</button>
        </form>
    </div>
</x-layouts.app>
