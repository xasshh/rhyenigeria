@php
    $labels = ['Account', 'Details', 'Location', $canvasser ? 'Volunteer' : 'Finish'];
    $config = [
        'canvasser' => $canvasser,
        'ref' => $ref,
        'csrf' => csrf_token(),
        'urls' => [
            'check' => route('join.check', '__STEP__'),
            'store' => route('join.store'),
            'lgas' => route('geo.lgas', '__ID__'),
            'wards' => route('geo.wards', '__ID__'),
            'units' => route('geo.polling-units', '__ID__'),
        ],
    ];
@endphp
<x-layouts.auth :title="$canvasser ? 'Join as a Canvasser' : 'Join as a Member'">
    <div x-data="joinWizard(@js($config))" x-cloak>
        <h1 class="font-display text-[28px] leading-tight font-bold tracking-tight">{{ $canvasser ? 'Join as a Canvasser' : 'Join the movement' }}</h1>
        <p class="mt-1.5 text-neutral-600">Takes about two minutes. You'll be connected to your ward team.</p>

        {{-- Progress --}}
        <ol class="mt-6 grid grid-cols-4 gap-2" aria-label="Progress">
            @foreach ($labels as $i => $label)
                <li>
                    <span class="block h-1.5 rounded-full transition-colors" :class="step >= {{ $i }} ? 'bg-brand' : 'bg-line'"></span>
                    <span class="mt-1.5 block text-xs font-medium" :class="step >= {{ $i }} ? 'text-brand-dark' : 'text-neutral-500'" :aria-current="step === {{ $i }} ? 'step' : null">{{ $label }}</span>
                </li>
            @endforeach
        </ol>

        <form class="panel mt-5 p-5 sm:p-6" x-on:submit.prevent="next" novalidate>
            <p x-show="general" x-text="general" class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert"></p>

            {{-- Honeypot: hidden from people, tempting to bots. --}}
            <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off" x-model="form.website"></label>
            </div>

            {{-- Step 1: Account --}}
            <div x-show="step === 0" data-step="0" class="space-y-5">
                <div>
                    <label class="label" for="email">Email address</label>
                    <input id="email" type="email" class="field" autocomplete="email" inputmode="email" placeholder="you@example.com" x-model.trim="form.email" :aria-invalid="!!err('email')">
                    <p class="hint">You'll sign in with this email. We also use it if you forget your password.</p>
                    <p class="error" x-show="err('email')" x-text="err('email')"></p>
                </div>
                <div>
                    <label class="label" for="phone">Phone number</label>
                    <input id="phone" type="tel" class="field" autocomplete="tel-national" inputmode="tel" placeholder="0803 000 0000" x-model.trim="form.phone" :aria-invalid="!!err('phone')">
                    <p class="hint">A Nigerian mobile number. Your coordinator may call or WhatsApp you on it.</p>
                    <p class="error" x-show="err('phone')" x-text="err('phone')"></p>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="label" for="password">Create a password</label>
                        <input id="password" type="password" class="field" autocomplete="new-password" placeholder="At least 8 characters" x-model="form.password" :aria-invalid="!!err('password')">
                        <p class="error" x-show="err('password')" x-text="err('password')"></p>
                    </div>
                    <div>
                        <label class="label" for="password_confirmation">Type it again</label>
                        <input id="password_confirmation" type="password" class="field" autocomplete="new-password" x-model="form.password_confirmation">
                    </div>
                </div>
            </div>

            {{-- Step 2: Details --}}
            <div x-show="step === 1" data-step="1" class="space-y-5">
                <div>
                    <label class="label" for="name">Full name</label>
                    <input id="name" type="text" class="field" autocomplete="name" placeholder="e.g. Aisha Bello" x-model="form.name" :aria-invalid="!!err('name')">
                    <p class="error" x-show="err('name')" x-text="err('name')"></p>
                </div>
                <fieldset>
                    <legend class="label">Gender</legend>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($genders as $gender)
                            <label class="choice"><input type="radio" class="sr-only" value="{{ $gender->value }}" x-model="form.gender"> {{ $gender->label() }}</label>
                        @endforeach
                    </div>
                    <p class="error" x-show="err('gender')" x-text="err('gender')"></p>
                </fieldset>
                <fieldset>
                    <legend class="label">Age range</legend>
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-5">
                        @foreach ($ageRanges as $range)
                            <label class="choice"><input type="radio" class="sr-only" value="{{ $range->value }}" x-model="form.age_range"> {{ $range->label() }}</label>
                        @endforeach
                    </div>
                    <p class="error" x-show="err('age_range')" x-text="err('age_range')"></p>
                </fieldset>
                <div>
                    <label class="label" for="occupation">Occupation <span class="font-normal text-neutral-500">(optional)</span></label>
                    <input id="occupation" type="text" class="field" placeholder="e.g. Trader, teacher, student" x-model="form.occupation">
                </div>
            </div>

            {{-- Step 3: Location --}}
            <div x-show="step === 2" data-step="2" class="space-y-5">
                <div>
                    <label class="label" for="state_id">State</label>
                    <select id="state_id" class="field" x-model="form.state_id" x-on:change="loadLgas()" :aria-invalid="!!err('state_id')">
                        <option value="">Choose your state</option>
                        @foreach ($states as $state)
                            <option value="{{ $state->id }}">{{ $state->name }}</option>
                        @endforeach
                    </select>
                    <p class="error" x-show="err('state_id')" x-text="err('state_id')"></p>
                </div>
                <div>
                    <label class="label" for="lga_id">Local Government Area</label>
                    <select id="lga_id" class="field" x-model="form.lga_id" x-on:change="loadWards()" :disabled="!form.state_id || loading.lgas" :aria-invalid="!!err('lga_id')">
                        <option value="" x-text="loading.lgas ? 'Loading…' : 'Choose your LGA'"></option>
                        <template x-for="lga in lgas" :key="lga.id"><option :value="String(lga.id)" x-text="lga.name" :selected="String(lga.id) === String(form.lga_id)"></option></template>
                    </select>
                    <p class="error" x-show="err('lga_id')" x-text="err('lga_id')"></p>
                </div>
                <div>
                    <label class="label" for="ward_id">Ward <span class="font-normal text-neutral-500" x-show="form.lga_id && !loading.wards && !wards.length">(not available yet)</span></label>
                    <select id="ward_id" class="field" x-model="form.ward_id" x-on:change="loadUnits()" :disabled="!form.lga_id || loading.wards || !wards.length" :aria-invalid="!!err('ward_id')">
                        <option value="" x-text="loading.wards ? 'Loading…' : 'Choose your ward'"></option>
                        <template x-for="ward in wards" :key="ward.id"><option :value="String(ward.id)" x-text="ward.name" :selected="String(ward.id) === String(form.ward_id)"></option></template>
                    </select>
                    <p class="hint" x-text="wardLabel"></p>
                    <p class="error" x-show="err('ward_id')" x-text="err('ward_id')"></p>
                </div>
                <div x-show="form.ward_id && (units.length || loading.units)">
                    <label class="label" for="polling_unit_id">Polling unit <span class="font-normal text-neutral-500">(optional)</span></label>
                    <select id="polling_unit_id" class="field" x-model="form.polling_unit_id" :disabled="loading.units" :aria-invalid="!!err('polling_unit_id')">
                        <option value="" x-text="loading.units ? 'Loading…' : 'Not sure'"></option>
                        <template x-for="unit in units" :key="unit.id"><option :value="String(unit.id)" x-text="unit.code + ' · ' + unit.name" :selected="String(unit.id) === String(form.polling_unit_id)"></option></template>
                    </select>
                    <p class="hint">Where you vote. Choose "Not sure" if you don't know yet.</p>
                    <p class="error" x-show="err('polling_unit_id')" x-text="err('polling_unit_id')"></p>
                </div>

                <fieldset>
                    <legend class="label">Do you have your PVC (voter's card)?</legend>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="choice"><input type="radio" class="sr-only" name="has_pvc" :checked="form.has_pvc === true" x-on:change="form.has_pvc = true"> Yes, I have it</label>
                        <label class="choice"><input type="radio" class="sr-only" name="has_pvc" :checked="form.has_pvc === false" x-on:change="form.has_pvc = false; form.vin = ''"> Not yet</label>
                    </div>
                    <p class="error" x-show="err('has_pvc')" x-text="err('has_pvc')"></p>
                </fieldset>
                <div x-show="form.has_pvc === true">
                    <label class="label" for="vin">VIN <span class="font-normal text-neutral-500">(optional, you can add it later)</span></label>
                    <input id="vin" type="text" class="field font-mono uppercase placeholder:font-body placeholder:normal-case" autocomplete="off" maxlength="25" placeholder="19 letters and numbers on your PVC" x-model.trim="form.vin" :aria-invalid="!!err('vin')">
                    <p class="error" x-show="err('vin')" x-text="err('vin')"></p>
                </div>
                <div>
                    <label class="label" for="nin">NIN <span class="font-normal text-neutral-500">(optional)</span></label>
                    <input id="nin" type="text" class="field font-mono placeholder:font-body" inputmode="numeric" autocomplete="off" maxlength="14" placeholder="11 digits" x-model.trim="form.nin" :aria-invalid="!!err('nin')">
                    <p class="hint">Stored securely (encrypted) and never shown in full. We don't verify it yet.</p>
                    <p class="error" x-show="err('nin')" x-text="err('nin')"></p>
                </div>

                <div class="rounded-xl border border-dashed border-line p-4">
                    <div class="flex items-start gap-3">
                        <x-icon name="pin" class="mt-0.5 text-brand" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium">Share my current location <span class="font-normal text-neutral-500">(optional)</span></p>
                            <p class="mt-0.5 text-xs text-neutral-500">Helps your coordinator plan ward activities. We only ask your phone when you tap the button.</p>
                            <p class="mt-2 text-sm" x-show="geo.message" x-text="geo.message" :class="geo.status === 'done' ? 'text-brand-dark' : 'text-neutral-600'"></p>
                        </div>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <button type="button" class="btn-secondary btn-sm" x-on:click="locate" x-show="geo.status !== 'done'" :disabled="geo.status === 'busy'">Share location</button>
                        <button type="button" class="btn-secondary btn-sm" x-on:click="clearLocation" x-show="geo.status === 'done'">Remove location</button>
                    </div>
                </div>
            </div>

            {{-- Step 4: Finish / Volunteer --}}
            <div x-show="step === 3" data-step="3" class="space-y-5">
                @if ($canvasser)
                    <fieldset>
                        <legend class="label">How would you like to help?</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($canvasserRoles as $role)
                                <label class="chip group"><input type="checkbox" class="sr-only" value="{{ $role->value }}" x-model="form.canvasser_roles"> <x-icon name="check" class="hidden h-4 w-4 group-has-[:checked]:block" /> {{ $role->label() }}</label>
                            @endforeach
                        </div>
                        <p class="error" x-show="err('canvasser_roles')" x-text="err('canvasser_roles')"></p>
                    </fieldset>
                    <div>
                        <label class="label" for="whatsapp_phone">WhatsApp number</label>
                        <input id="whatsapp_phone" type="tel" class="field" inputmode="tel" :placeholder="form.phone || '0803 000 0000'" x-model.trim="form.whatsapp_phone" :aria-invalid="!!err('whatsapp_phone')">
                        <p class="hint">Leave blank to use your phone number.</p>
                        <p class="error" x-show="err('whatsapp_phone')" x-text="err('whatsapp_phone')"></p>
                    </div>
                @endif
                <div>
                    <label class="label" for="referrer">Who invited you? <span class="font-normal text-neutral-500">(optional)</span></label>
                    <input id="referrer" type="text" class="field font-mono uppercase placeholder:normal-case" autocomplete="off" placeholder="RHYE-LA-004512" x-model.trim="form.referrer" :aria-invalid="!!err('referrer')">
                    <p class="hint">Enter the member number on their card, or leave blank.</p>
                    <p class="error" x-show="err('referrer')" x-text="err('referrer')"></p>
                </div>
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-brand/25 bg-brand/5 p-4">
                    <input type="checkbox" class="mt-1 h-5 w-5 shrink-0 rounded accent-brand" x-model="form.consent" :aria-invalid="!!err('consent')">
                    <span class="text-sm leading-relaxed text-neutral-700">
                        <span class="flex items-center gap-1.5 font-semibold text-ink"><x-icon name="lock" class="h-4 w-4" /> Data consent</span>
                        I agree that Renewed Hope Youth Engagement may store my details to coordinate the movement, in line with the Nigeria Data Protection Act 2023. My data will never be sold, and I can ask for it to be deleted at any time.
                        <a href="{{ route('privacy') }}" target="_blank" class="font-semibold text-brand underline">Read the privacy notice</a>
                    </span>
                </label>
                <p class="error -mt-3" x-show="err('consent')" x-text="err('consent')"></p>
                @if ($canvasser)
                    <p class="text-xs text-neutral-500">You'll be a member straight away. Your canvasser application goes to your state coordinator for review.</p>
                @endif
            </div>

            <div class="mt-7 flex gap-2">
                <button type="button" class="btn-secondary w-14 shrink-0 px-0" x-show="step > 0" x-on:click="back" aria-label="Back"><x-icon name="arrow-left" /></button>
                <button type="submit" class="btn-primary flex-1" :disabled="busy">
                    <svg x-show="busy" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3"/></svg>
                    <span x-text="step === 3 ? 'Complete registration' : 'Continue'">Continue</span>
                </button>
            </div>
        </form>

        <p class="mt-6 text-center text-sm text-neutral-600">Already a member? <a href="{{ route('login') }}" class="font-semibold text-brand">Sign in</a></p>
    </div>
    <noscript><p class="panel mt-6 p-5 text-sm">Please turn on JavaScript in your browser to join.</p></noscript>
</x-layouts.auth>
