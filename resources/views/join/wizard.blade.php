@php
    $config = [
        'canvasser' => $canvasser,
        'ref' => $ref,
        'state' => $preselectedStateId,
        'states' => $states->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values(),
        'csrf' => csrf_token(),
        'urls' => [
            'check' => route('join.check', '__STEP__'),
            'store' => route('join.store'),
            'lgas' => route('geo.lgas', '__ID__'),
            'wards' => route('geo.wards', '__ID__'),
            'units' => route('geo.polling-units', '__ID__'),
        ],
    ];
    $roleIcons = [
        'door_to_door' => 'door', 'social_media' => 'megaphone', 'event_mobilisation' => 'users', 'driving_logistics' => 'car',
        'womens_groups' => 'sparkle', 'youth_groups' => 'grid', 'religious_community_leaders' => 'columns', 'polling_unit_agent' => 'flag',
    ];
@endphp
<x-layouts.split :title="$canvasser ? 'Join as a Canvasser' : 'Join as a Member'" :alpine="'joinWizard('.Js::from($config).')'">
    <x-slot:top>Already a member? <a href="{{ route('login') }}" class="font-semibold text-action hover:underline">Sign in</a></x-slot:top>

    <x-slot:aside>
        <p class="font-headline text-[30px] leading-[1.05] font-extrabold tracking-[-0.025em] sm:text-[36px] lg:text-[42px]">Your card builds <br class="hidden lg:block">as you go.</p>
        <p class="mt-3 hidden max-w-[380px] text-[17px] leading-relaxed text-white/80 sm:block lg:mt-5" x-text="asideText">Fill in each step and watch your membership card come together. It's ready the moment you finish.</p>
        <x-card-preview class="mt-10 hidden lg:block" :bind="['name' => 'card.name', 'badge' => 'card.badge', 'tone' => 'card.tone', 'state' => 'card.state', 'ward' => 'card.ward']" />
    </x-slot:aside>

    <x-slot:asideFooter>
        <ol class="space-y-5" aria-label="Steps">
            <template x-for="(s, i) in stepList" :key="i">
                <li class="flex items-center gap-4" :aria-current="step === i ? 'step' : null">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-[15px] font-semibold"
                          :class="i < step ? 'bg-leaf text-forest' : (i === step ? 'bg-white text-forest ring-4 ring-action' : 'ring-1 ring-white/40 text-white/80')">
                        <svg x-show="i < step" class="h-4 w-4" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 6.5 4.6 9 10 3.5"/></svg>
                        <span x-show="i >= step" x-text="i + 1"></span>
                    </span>
                    <span>
                        <span class="block text-[18px] font-semibold" :class="i <= step ? 'text-white' : 'text-white/85'" x-text="s[0]"></span>
                        <span class="block text-[15px] text-white/60" x-text="s[1]"></span>
                    </span>
                </li>
            </template>
        </ol>
    </x-slot:asideFooter>

    <div x-cloak>
        {{-- Progress --}}
        <div class="flex items-baseline justify-between gap-3 text-[15px]">
            <p class="font-semibold text-action-dark">Step <span x-text="step + 1">1</span> of 4 · <span x-text="stepList[step][0]">Account</span></p>
            <p class="text-neutral-600">Joining as a <span x-text="canvasser ? 'Canvasser' : 'Member'"></span></p>
        </div>
        <div class="mt-3 grid grid-cols-4 gap-2" aria-hidden="true">
            <template x-for="i in 4"><span class="h-1.5 rounded-full transition-colors" :class="i - 1 <= step ? 'bg-action' : 'bg-[#dfe5e0]'"></span></template>
        </div>

        <form class="mt-8" x-on:submit.prevent="next" novalidate>
            <p x-show="general" x-text="general" class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert"></p>

            {{-- Honeypot: hidden from people, tempting to bots. --}}
            <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off" x-model="form.website"></label>
            </div>

            {{-- Step 1: Account --}}
            <div x-show="step === 0" data-step="0" class="space-y-6">
                <div>
                    <h1 class="font-headline text-[36px] leading-[1.05] font-extrabold tracking-[-0.03em] sm:text-[46px]">Create your account</h1>
                    <p class="mt-3 text-lg text-neutral-600">You'll be connected to your ward team.</p>
                </div>
                <fieldset>
                    <legend class="label text-base font-semibold">Joining as</legend>
                    <div class="grid grid-cols-2 gap-1 rounded-2xl bg-[#f1f3f1] p-1.5">
                        @foreach ([['member', 'Member', 'card'], ['canvasser', 'Canvasser', 'shield']] as [$value, $label, $icon])
                            <label class="flex min-h-12 cursor-pointer items-center justify-center gap-2.5 rounded-xl text-[17px] text-neutral-600 transition has-[:checked]:bg-white has-[:checked]:font-semibold has-[:checked]:text-action-dark has-[:checked]:shadow-sm has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-action/30">
                                <input type="radio" class="sr-only" name="joining_as" value="{{ $value }}" :checked="canvasser === {{ $value === 'canvasser' ? 'true' : 'false' }}" x-on:change="setCanvasser({{ $value === 'canvasser' ? 'true' : 'false' }})">
                                <x-icon :name="$icon" class="h-[18px] w-[18px]" /> {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div>
                    <label class="label text-base font-semibold" for="email">Email address</label>
                    <input id="email" type="email" class="field-lg" autocomplete="email" inputmode="email" placeholder="you@example.com" x-model.trim="form.email" :aria-invalid="!!err('email')">
                    <p class="hint text-sm">You'll sign in with this. We also use it if you forget your password.</p>
                    <p class="error" x-show="err('email')" x-text="err('email')"></p>
                </div>
                <div>
                    <label class="label text-base font-semibold" for="phone">Phone number</label>
                    <div class="flex gap-2.5">
                        <span class="flex shrink-0 items-center gap-2 rounded-xl border border-[#d6ddd8] bg-[#f1f3f1] px-4 font-semibold" aria-hidden="true"><x-ng-flag /> +234</span>
                        <input id="phone" type="tel" class="field-lg" autocomplete="tel-national" inputmode="tel" placeholder="803 000 0000" x-model.trim="form.phone" :aria-invalid="!!err('phone')" aria-describedby="phone-hint">
                    </div>
                    <p class="hint text-sm" id="phone-hint">Your coordinator may call or WhatsApp you on it.</p>
                    <p class="error" x-show="err('phone')" x-text="err('phone')"></p>
                </div>
                <div class="grid gap-6 sm:grid-cols-2 sm:gap-5">
                    <div>
                        <label class="label text-base font-semibold" for="password">Create a password</label>
                        <div class="relative">
                            <input id="password" :type="showPassword ? 'text' : 'password'" type="password" class="field-lg pr-12" autocomplete="new-password" placeholder="At least 8 characters" x-model="form.password" :aria-invalid="!!err('password')">
                            <button type="button" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-neutral-500 hover:text-ink" x-on:click="showPassword = !showPassword" aria-label="Show passwords"><x-icon name="eye" /></button>
                        </div>
                        <p class="error" x-show="err('password')" x-text="err('password')"></p>
                    </div>
                    <div>
                        <label class="label text-base font-semibold" for="password_confirmation">Type it again</label>
                        <div class="relative">
                            <input id="password_confirmation" :type="showPassword ? 'text' : 'password'" type="password" class="field-lg pr-12" autocomplete="new-password" x-model="form.password_confirmation">
                            <button type="button" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-neutral-500 hover:text-ink" x-on:click="showPassword = !showPassword" aria-label="Show passwords"><x-icon name="eye" /></button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Step 2: Details --}}
            <div x-show="step === 1" data-step="1" class="space-y-6">
                <div>
                    <h1 class="font-headline text-[36px] leading-[1.05] font-extrabold tracking-[-0.03em] sm:text-[46px]">Tell us about you</h1>
                    <p class="mt-3 text-lg text-neutral-600">This goes on your card and helps us plan events for your age group.</p>
                </div>
                <div>
                    <label class="label text-base font-semibold" for="name">Full name</label>
                    <input id="name" type="text" class="field-lg" autocomplete="name" placeholder="e.g. Aisha Bello" x-model="form.name" :aria-invalid="!!err('name')">
                    <p class="hint text-sm">As it appears on your voter's card, if you have one.</p>
                    <p class="error" x-show="err('name')" x-text="err('name')"></p>
                </div>
                <fieldset>
                    <legend class="label text-base font-semibold">Gender</legend>
                    <div class="flex flex-wrap gap-2.5">
                        @foreach ($genders as $gender)
                            <label class="pill"><input type="radio" class="sr-only" name="gender" value="{{ $gender->value }}" x-model="form.gender"><span class="pill-dot"></span> {{ $gender->label() }}</label>
                        @endforeach
                    </div>
                    <p class="error" x-show="err('gender')" x-text="err('gender')"></p>
                </fieldset>
                <fieldset>
                    <legend class="label text-base font-semibold">Age range</legend>
                    <div class="flex flex-wrap gap-2.5">
                        @foreach ($ageRanges as $range)
                            <label class="pill"><input type="radio" class="sr-only" name="age_range" value="{{ $range->value }}" x-model="form.age_range"><span class="pill-dot"></span> {{ $range->label() }}</label>
                        @endforeach
                    </div>
                    <p class="error" x-show="err('age_range')" x-text="err('age_range')"></p>
                </fieldset>
                <div>
                    <label class="label text-base font-semibold" for="occupation">Occupation <span class="font-normal text-neutral-500">(optional)</span></label>
                    <input id="occupation" type="text" class="field-lg" placeholder="e.g. Student, trader, nurse" x-model="form.occupation">
                </div>
                <div>
                    <label class="label text-base font-semibold" for="state_id">State</label>
                    <select id="state_id" class="field-lg" x-model="form.state_id" x-on:change="loadLgas()" :aria-invalid="!!err('state_id')">
                        <option value="">Choose your state</option>
                        @foreach ($states as $state)
                            <option value="{{ $state->id }}">{{ $state->name }}</option>
                        @endforeach
                    </select>
                    <p class="error" x-show="err('state_id')" x-text="err('state_id')"></p>
                </div>
            </div>

            {{-- Step 3: Location --}}
            <div x-show="step === 2" data-step="2" class="space-y-6">
                <div>
                    <h1 class="font-headline text-[36px] leading-[1.05] font-extrabold tracking-[-0.03em] sm:text-[46px]">Where do you vote?</h1>
                    <p class="mt-3 text-lg leading-relaxed text-neutral-600">We use this to connect you to your ward team. Skip anything you're unsure of.</p>
                </div>
                <div class="grid gap-6 sm:grid-cols-2 sm:gap-5">
                    <div>
                        <label class="label text-base font-semibold" for="lga_id">Local Government Area</label>
                        <select id="lga_id" class="field-lg" x-model="form.lga_id" x-on:change="loadWards()" :disabled="!form.state_id || loading.lgas" :aria-invalid="!!err('lga_id')">
                            <option value="" x-text="loading.lgas ? 'Loading…' : 'Choose your LGA'"></option>
                            <template x-for="lga in lgas" :key="lga.id"><option :value="String(lga.id)" x-text="lga.name" :selected="String(lga.id) === String(form.lga_id)"></option></template>
                        </select>
                        <p class="error" x-show="err('lga_id')" x-text="err('lga_id')"></p>
                    </div>
                    <div>
                        <label class="label text-base font-semibold" for="ward_id">Ward</label>
                        <select id="ward_id" class="field-lg" x-model="form.ward_id" x-on:change="loadUnits()" :disabled="!form.lga_id || loading.wards || !wards.length" :aria-invalid="!!err('ward_id')">
                            <option value="" x-text="loading.wards ? 'Loading…' : (form.lga_id && !wards.length ? 'Coming soon' : 'Choose your ward')"></option>
                            <template x-for="ward in wards" :key="ward.id"><option :value="String(ward.id)" x-text="ward.name" :selected="String(ward.id) === String(form.ward_id)"></option></template>
                        </select>
                        <p class="error" x-show="err('ward_id')" x-text="err('ward_id')"></p>
                    </div>
                </div>
                <div>
                    <label class="label text-base font-semibold" for="polling_unit_id">Polling unit <span class="font-normal text-neutral-500">(optional)</span></label>
                    <select id="polling_unit_id" class="field-lg" x-model="form.polling_unit_id" :disabled="loading.units || !units.length" :aria-invalid="!!err('polling_unit_id')">
                        <option value="" x-text="loading.units ? 'Loading…' : 'Not sure yet'"></option>
                        <template x-for="unit in units" :key="unit.id"><option :value="String(unit.id)" x-text="unit.code + ' · ' + unit.name" :selected="String(unit.id) === String(form.polling_unit_id)"></option></template>
                    </select>
                    <p class="hint text-sm">Where you vote. Choose “Not sure yet” if you don't know.</p>
                    <p class="error" x-show="err('polling_unit_id')" x-text="err('polling_unit_id')"></p>
                </div>

                <fieldset>
                    <legend class="label text-base font-semibold">Do you have your PVC (voter's card)?</legend>
                    <div class="flex flex-wrap gap-2.5">
                        <label class="pill"><input type="radio" class="sr-only" name="has_pvc" :checked="form.has_pvc === true" x-on:change="form.has_pvc = true"><span class="pill-dot"></span> Yes, I have it</label>
                        <label class="pill"><input type="radio" class="sr-only" name="has_pvc" :checked="form.has_pvc === false" x-on:change="form.has_pvc = false; form.vin = ''"><span class="pill-dot"></span> Not yet</label>
                    </div>
                    <p class="error" x-show="err('has_pvc')" x-text="err('has_pvc')"></p>
                </fieldset>
                <div class="grid gap-6 sm:grid-cols-2 sm:gap-5">
                    <div>
                        <label class="label text-base font-semibold" for="vin">VIN <span class="font-normal text-neutral-500">(optional)</span></label>
                        <input id="vin" type="text" class="field-lg font-mono uppercase placeholder:font-body placeholder:normal-case" autocomplete="off" maxlength="25" placeholder="19 characters" x-model.trim="form.vin" :disabled="form.has_pvc === false" :aria-invalid="!!err('vin')">
                        <p class="hint text-sm">You can add it later.</p>
                        <p class="error" x-show="err('vin')" x-text="err('vin')"></p>
                    </div>
                    <div>
                        <label class="label text-base font-semibold" for="nin">NIN <span class="font-normal text-neutral-500">(optional)</span></label>
                        <input id="nin" type="text" class="field-lg font-mono placeholder:font-body" inputmode="numeric" autocomplete="off" maxlength="14" placeholder="11 digits" x-model.trim="form.nin" :aria-invalid="!!err('nin')">
                        <p class="hint text-sm">Encrypted and never shown in full.</p>
                        <p class="error" x-show="err('nin')" x-text="err('nin')"></p>
                    </div>
                </div>

                <div class="rounded-2xl border border-dashed border-[#cdd6cf] bg-[#f6f8f6] p-5 sm:p-6">
                    <div class="flex items-start gap-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white text-action"><x-icon name="pin" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[17px] font-semibold">Share my current location <span class="font-normal text-neutral-500">(optional)</span></p>
                            <p class="mt-1 text-[15px] leading-relaxed text-neutral-600">Helps your coordinator plan ward activities. We only ask your phone when you tap.</p>
                            <p class="mt-2 text-sm" x-show="geo.message" x-text="geo.message" :class="geo.status === 'done' ? 'text-action-dark' : 'text-neutral-600'"></p>
                        </div>
                    </div>
                    <button type="button" class="btn-ghost mt-4 min-h-12 px-5" x-on:click="locate" x-show="geo.status !== 'done'" :disabled="geo.status === 'busy'"><x-icon name="pin" class="h-4 w-4" /> Share location</button>
                    <button type="button" class="btn-ghost mt-4 min-h-12 px-5" x-on:click="clearLocation" x-show="geo.status === 'done'">Remove location</button>
                </div>
            </div>

            {{-- Step 4: Volunteer (canvassers) / Finish (members) --}}
            <div x-show="step === 3" data-step="3" class="space-y-6">
                <div>
                    <h1 class="font-headline text-[36px] leading-[1.05] font-extrabold tracking-[-0.03em] sm:text-[46px]" x-text="canvasser ? 'How would you like to help?' : 'Almost done'">{{ $canvasser ? 'How would you like to help?' : 'Almost done' }}</h1>
                    <p class="mt-3 text-lg leading-relaxed text-neutral-600" x-text="canvasser ? 'Pick as many as you like. Your coordinator will match you to ward activities.' : 'Tell us who invited you, agree to how we look after your data, and your card is ready.'"></p>
                </div>

                <template x-if="canvasser">
                    <div class="space-y-6">
                        <fieldset>
                            <legend class="label text-base font-semibold">I can help with</legend>
                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach ($canvasserRoles as $role)
                                    <label class="flex min-h-[68px] cursor-pointer items-center gap-3.5 rounded-xl border border-[#d6ddd8] bg-white px-4 py-3 text-[16px] select-none hover:border-action/60 has-[:checked]:border-action has-[:checked]:bg-[#eaf3ec] has-[:checked]:font-semibold has-[:checked]:text-action-dark has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-action/30">
                                        <input type="checkbox" class="h-5 w-5 shrink-0 rounded accent-action" value="{{ $role->value }}" x-model="form.canvasser_roles">
                                        <x-icon :name="$roleIcons[$role->value] ?? 'check'" class="h-5 w-5 text-action" />
                                        <span>{{ str_replace('&', 'and', $role->label()) }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="error" x-show="err('canvasser_roles')" x-text="err('canvasser_roles')"></p>
                        </fieldset>
                        <div>
                            <label class="label text-base font-semibold" for="whatsapp_phone">WhatsApp number <span class="font-normal text-neutral-500">(optional)</span></label>
                            <div class="flex gap-2.5">
                                <span class="flex shrink-0 items-center gap-2 rounded-xl border border-[#d6ddd8] bg-[#f1f3f1] px-4 font-semibold" aria-hidden="true"><x-ng-flag /> +234</span>
                                <input id="whatsapp_phone" type="tel" class="field-lg" inputmode="tel" placeholder="803 000 0000" :value="sameWhatsapp ? form.phone : form.whatsapp_phone" x-on:input="form.whatsapp_phone = $event.target.value" :disabled="sameWhatsapp" :aria-invalid="!!err('whatsapp_phone')">
                            </div>
                            <label class="mt-3 flex items-center gap-3 text-[16px]"><input type="checkbox" class="h-5 w-5 rounded accent-action" x-model="sameWhatsapp" x-on:change="sameWhatsapp && (form.whatsapp_phone = '')"> Same as my phone number</label>
                            <p class="error" x-show="err('whatsapp_phone')" x-text="err('whatsapp_phone')"></p>
                        </div>
                    </div>
                </template>

                <div>
                    <label class="label text-base font-semibold" for="referrer">Who invited you? <span class="font-normal text-neutral-500">(optional)</span></label>
                    <input id="referrer" type="text" class="field-lg font-mono uppercase placeholder:font-body placeholder:normal-case" autocomplete="off" placeholder="Member number, e.g. RHYE-LA-000123" x-model.trim="form.referrer" :aria-invalid="!!err('referrer')">
                    <p class="hint text-sm">Leave blank if no one did.</p>
                    <p class="error" x-show="err('referrer')" x-text="err('referrer')"></p>
                </div>

                <label class="flex cursor-pointer items-start gap-3.5 rounded-2xl bg-[#f1f4f1] p-5">
                    <input type="checkbox" class="mt-1 h-5 w-5 shrink-0 rounded accent-action" x-model="form.consent" :aria-invalid="!!err('consent')">
                    <span class="text-[16px] leading-relaxed text-neutral-700">
                        I agree that Renewed Hope Youth Engagement may store my details to coordinate the movement under the Nigeria Data Protection Act 2023. My data will never be sold and I can ask for it to be deleted at any time.
                        <a href="{{ route('privacy') }}" target="_blank" class="font-semibold text-action underline-offset-2 hover:underline">Read the privacy notice</a>
                    </span>
                </label>
                <p class="error -mt-3" x-show="err('consent')" x-text="err('consent')"></p>

                <p x-show="canvasser" class="flex items-start gap-3 rounded-2xl bg-amber-soft p-5 text-[16px] leading-relaxed text-amber-ink">
                    <x-icon name="shield" class="mt-0.5" />
                    <span>You'll become a member straight away. Your canvasser application goes to your <strong x-text="(card.stateName ? card.stateName + ' ' : '') + 'state coordinator'">state coordinator</strong> for review.</span>
                </p>
            </div>

            <div class="mt-8 flex gap-3">
                <button type="button" class="btn-ghost shrink-0 px-6 text-[17px]" x-show="step > 0" x-on:click="back"><x-icon name="arrow-left" class="h-4 w-4" /> Back</button>
                <button type="submit" class="btn-action flex-1 text-[17px]" :disabled="busy">
                    <svg x-show="busy" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3"/></svg>
                    <span x-text="step === 3 ? (canvasser ? 'Join and apply' : 'Join the movement') : 'Continue'">Continue</span>
                    <x-icon name="arrow-right" class="h-4 w-4" x-show="!busy" />
                </button>
            </div>
        </form>
    </div>
    <noscript><p class="mt-6 rounded-2xl border border-line p-5 text-sm">Please turn on JavaScript in your browser to join.</p></noscript>
</x-layouts.split>
