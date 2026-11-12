@php use App\Support\Mask; @endphp
<x-layouts.app title="My card" heading="My card">
    <div class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,400px)_1fr]">
        <div class="space-y-4">
            <x-membership-card :user="$user" :profile="$profile" :qr-svg="$qrSvg" />
            <p class="text-center text-xs text-neutral-500">Scanning the QR code shows only your first name, last initial, state and the month you joined.</p>
            <x-share-panel :share-links="$shareLinks" :invite-url="$inviteUrl" :whatsapp-group="$whatsappGroup" />
        </div>

        <div class="space-y-5">
            <section id="voter" class="panel p-5">
                <h2 class="font-display text-lg font-bold">Voter details</h2>
                @if ($profile->needsVin())
                    <p class="mt-1 rounded-lg bg-accent/10 px-3 py-2 text-sm text-[#7a5b00]">Add your VIN so it counts.</p>
                @endif
                <form method="POST" action="{{ route('membership.identity.update') }}" class="mt-4 space-y-4">
                    @csrf @method('PUT')
                    <fieldset>
                        <legend class="label">Do you have your PVC?</legend>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="choice"><input type="radio" name="has_pvc" value="1" class="sr-only" @checked(old('has_pvc', $profile->has_pvc ? '1' : '0') === '1')> Yes, I have it</label>
                            <label class="choice"><input type="radio" name="has_pvc" value="0" class="sr-only" @checked(old('has_pvc', $profile->has_pvc ? '1' : '0') === '0')> Not yet</label>
                        </div>
                    </fieldset>
                    <div>
                        <label class="label" for="vin">VIN</label>
                        <input id="vin" name="vin" type="text" class="field font-mono uppercase placeholder:font-body placeholder:normal-case" autocomplete="off" maxlength="25" placeholder="{{ $profile->hasVin() ? 'Saved: '.Mask::identifier($profile->vin).' (type to replace)' : '19 letters and numbers on your PVC' }}">
                        @error('vin')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="nin">NIN</label>
                        <input id="nin" name="nin" type="text" class="field font-mono placeholder:font-body" inputmode="numeric" autocomplete="off" maxlength="14" placeholder="{{ $profile->nin ? 'Saved: '.Mask::identifier($profile->nin).' (type to replace)' : '11 digits (optional)' }}">
                        @error('nin')<p class="error">{{ $message }}</p>@enderror
                        <p class="hint">Encrypted when stored and never shown in full, even to you.</p>
                    </div>
                    <button class="btn-primary">Save voter details</button>
                </form>
            </section>

            <section class="panel p-5">
                <h2 class="font-display text-lg font-bold">My details</h2>
                <dl class="mt-3 grid gap-x-4 gap-y-2 text-sm sm:grid-cols-2">
                    <div><dt class="text-neutral-500">Email</dt><dd class="font-medium break-all">{{ $user->email }}</dd></div>
                    <div><dt class="text-neutral-500">Phone</dt><dd class="font-medium">{{ \App\Support\NigerianPhone::format($user->phone) }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-neutral-500">Location</dt><dd class="font-medium">{{ collect([$user->ward?->name, $user->lga?->name, $user->state?->name])->filter()->implode(', ') }}</dd></div>
                </dl>
                <p class="hint">To change your email, phone or location, ask your state coordinator.</p>
                <form method="POST" action="{{ route('membership.details.update') }}" class="mt-5 space-y-4 border-t border-line pt-5">
                    @csrf @method('PUT')
                    <div>
                        <label class="label" for="name">Full name</label>
                        <input id="name" name="name" class="field" value="{{ old('name', $user->name) }}" required>
                        @error('name')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="gender">Gender</label>
                            <select id="gender" name="gender" class="field">
                                @foreach ($genders as $g)<option value="{{ $g->value }}" @selected(old('gender', $profile->gender?->value) === $g->value)>{{ $g->label() }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label" for="age_range">Age range</label>
                            <select id="age_range" name="age_range" class="field">
                                @foreach ($ageRanges as $a)<option value="{{ $a->value }}" @selected(old('age_range', $profile->age_range?->value) === $a->value)>{{ $a->label() }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="label" for="occupation">Occupation</label>
                        <input id="occupation" name="occupation" class="field" value="{{ old('occupation', $profile->occupation) }}">
                    </div>
                    @if ($pollingUnits->isNotEmpty())
                        <div>
                            <label class="label" for="polling_unit_id">Polling unit</label>
                            <select id="polling_unit_id" name="polling_unit_id" class="field">
                                <option value="">Not sure</option>
                                @foreach ($pollingUnits as $unit)<option value="{{ $unit->id }}" @selected((int) old('polling_unit_id', $profile->polling_unit_id) === $unit->id)>{{ $unit->code }} · {{ $unit->name }}</option>@endforeach
                            </select>
                            @error('polling_unit_id')<p class="error">{{ $message }}</p>@enderror
                        </div>
                    @endif
                    <button class="btn-primary">Save details</button>
                </form>
            </section>

            <section class="panel p-5" x-data="{ open: {{ $errors->has('current_password') || $errors->has('password') ? 'true' : 'false' }} }">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-lg font-bold">Password</h2>
                    <button type="button" class="text-sm font-semibold text-brand" x-on:click="open = !open" x-text="open ? 'Cancel' : 'Change'">Change</button>
                </div>
                <form method="POST" action="{{ route('membership.password.update') }}" class="mt-4 space-y-4" x-show="open" x-cloak>
                    @csrf @method('PUT')
                    <div>
                        <label class="label" for="current_password">Current password</label>
                        <input id="current_password" name="current_password" type="password" class="field" autocomplete="current-password">
                        @error('current_password')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="password">New password</label>
                            <input id="password" name="password" type="password" class="field" autocomplete="new-password">
                            @error('password')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="label" for="password_confirmation">Type it again</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" class="field" autocomplete="new-password">
                        </div>
                    </div>
                    <button class="btn-primary">Change password</button>
                </form>
            </section>
        </div>
    </div>
</x-layouts.app>
