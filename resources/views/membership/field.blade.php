@php
    $tz = config('membership.timezone');
    $config = [
        'csrf' => csrf_token(),
        'onShift' => $shift !== null,
        'startedAt' => $shift?->started_at->toIso8601ZuluString(),
        'activities' => $today->map(fn ($a) => [
            'uuid' => $a->uuid, 'kind' => $a->kind->value, 'label' => $a->kind->label(),
            'summary' => match ($a->kind->value) {
                'door_to_door' => $a->households.' '.str('household')->plural($a->households).' · '.$a->outcome?->label(),
                'event_check_in' => $a->event?->title ?? 'Event',
                default => str((string) $a->note)->limit(60)->toString(),
            },
            'time' => $a->recorded_at->setTimezone($tz)->format('g:ia'),
        ])->values(),
        'urls' => [
            'start' => route('membership.field.shift.start'),
            'end' => route('membership.field.shift.end'),
            'locations' => route('membership.field.locations'),
            'activities' => route('membership.field.activities.store'),
            'register' => route('membership.field.members.store'),
            'wards' => route('geo.wards', '__ID__'),
            'units' => route('geo.polling-units', '__ID__'),
        ],
        'registeredToday' => $registeredToday,
    ];
@endphp
<x-layouts.app title="Field work">
    <p class="text-sm text-neutral-500">Canvasser{{ $user->state ? ', '.$user->state->name : '' }}</p>
    <h1 class="font-display text-2xl font-bold tracking-tight sm:text-[28px]">Field work</h1>

    @unless ($consented)
        {{-- The location notice. Nothing is recorded until this is accepted and a shift is started. --}}
        <section class="panel mt-5 p-5 sm:p-6">
            <h2 class="font-display text-lg font-bold">Before you start: location sharing</h2>
            <p class="mt-2 text-neutral-700">When you canvass, your state coordinator can see where you are working so they can plan, support and keep you safe. Here is exactly how it works:</p>
            <ul class="mt-4 space-y-3 text-[15px] text-neutral-700">
                @foreach ([
                    ['clock', '<strong>Only during a shift.</strong> Your location is recorded only after you tap <em>Start shift</em>, and stops when you tap <em>End shift</em>, close this page or withdraw. Never at any other time.'],
                    ['pin', '<strong>What is recorded:</strong> your phone\'s GPS position about once a minute while you move, its accuracy and the time, plus the visits, check-ins and reports you choose to log.'],
                    ['users', '<strong>Who sees it:</strong> your state coordinator and the national team. Nobody else, and it is never sold or published.'],
                    ['lock', '<strong>How long:</strong> positions are deleted after '.config('tracking.retention_days').' days. The activities you log are kept as campaign records.'],
                    ['x', '<strong>Your choice:</strong> you can withdraw at any time on this page. Location sharing stops at once, and you stay a canvasser.'],
                ] as [$icon, $text])
                    <li class="flex gap-3"><x-icon :name="$icon" class="mt-0.5 text-brand" /><span>{!! $text !!}</span></li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('membership.field.consent.accept') }}" class="mt-5 space-y-4">
                @csrf
                <label class="flex items-start gap-3 rounded-xl border border-brand/25 bg-brand/5 p-4">
                    <input type="checkbox" name="accepted" value="1" class="mt-1 h-5 w-5 shrink-0 rounded accent-brand" required>
                    <span class="text-sm text-neutral-700">I agree to share my location with Renewed Hope Youth Engagement during the shifts I start, as described above and in the <a href="{{ route('privacy') }}#location" class="font-semibold text-brand underline" target="_blank">privacy notice</a>.</span>
                </label>
                @error('accepted')<p class="error">{{ $message }}</p>@enderror
                <button class="btn-primary">I agree</button>
            </form>
        </section>
    @else
        <div x-data="fieldTracker(@js($config))" class="mt-5 space-y-4">
            {{-- Shift --}}
            <section class="overflow-hidden rounded-2xl text-white" :class="onShift ? 'bg-action' : 'bg-forest'">
                <div class="p-5 sm:p-6">
                    <div class="flex items-center gap-3">
                        <span class="relative flex h-3 w-3">
                            <span x-show="onShift" class="absolute inline-flex h-full w-full animate-ping rounded-full bg-accent opacity-75"></span>
                            <span class="relative inline-flex h-3 w-3 rounded-full" :class="onShift ? 'bg-accent' : 'bg-white/40'"></span>
                        </span>
                        <p class="font-display text-lg font-bold" x-text="onShift ? 'Location sharing is ON' : 'Location sharing is off'">{{ $shift ? 'Location sharing is ON' : 'Location sharing is off' }}</p>
                    </div>
                    <p class="mt-2 text-sm text-white/85" x-show="onShift" x-cloak>
                        On shift for <span x-text="shiftLength"></span>.
                        <span x-show="gps.status === 'ok'">GPS accurate to about <span x-text="gps.accuracy"></span> m.</span>
                        <span x-show="queue.length">· <span x-text="queue.length"></span> waiting to send</span>
                    </p>
                    <p class="mt-2 text-sm text-white/85" x-show="!onShift">Tap Start shift when you begin canvassing. Your coordinator sees where you work only while a shift is running.</p>
                    <p class="mt-2 text-sm font-semibold text-accent" x-show="gps.message" x-text="gps.message"></p>

                    <div class="mt-5">
                        <button type="button" class="btn min-h-[52px] w-full rounded-xl bg-accent text-forest hover:bg-[#d9a404] sm:w-auto sm:px-8" x-show="!onShift" x-on:click="start" :disabled="busy">Start shift</button>
                        <button type="button" class="btn min-h-[52px] w-full rounded-xl border border-white/50 text-white hover:bg-white/10 sm:w-auto sm:px-8" x-show="onShift" x-cloak x-on:click="end" :disabled="busy">End shift</button>
                    </div>
                    <p class="mt-4 text-xs text-white/70" x-show="onShift" x-cloak>Keep this page open while you work: your phone stops sharing when the page is closed or the screen locks. Ending the shift stops sharing immediately.</p>
                </div>
            </section>

            {{-- Log an activity --}}
            <section class="panel p-5 sm:p-6" x-show="onShift" x-cloak>
                <h2 class="font-display text-lg font-bold">Log what you did</h2>
                <p class="mt-1 text-sm text-neutral-600">Each entry is stamped with your location and the time.</p>

                <div class="mt-4 flex flex-wrap gap-2" role="radiogroup" aria-label="Kind of activity">
                    @foreach (['door_to_door' => ['Door-to-door', 'door'], 'event_check_in' => ['Event check-in', 'calendar'], 'report' => ['Report', 'news']] as $value => [$label, $icon])
                        <label class="chip"><input type="radio" class="sr-only" value="{{ $value }}" x-model="form.kind"> <x-icon :name="$icon" class="h-4 w-4" /> {{ $label }}</label>
                    @endforeach
                </div>

                <form class="mt-5 space-y-4" x-on:submit.prevent="logActivity">
                    <div x-show="form.kind === 'door_to_door'" class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="households">Households reached</label>
                            <input id="households" type="number" min="1" max="500" inputmode="numeric" class="field" x-model="form.households" :aria-invalid="!!err('households')">
                            <p class="error" x-show="err('households')" x-text="err('households')"></p>
                        </div>
                        <div>
                            <label class="label" for="outcome">How did it go?</label>
                            <select id="outcome" class="field" x-model="form.outcome" :aria-invalid="!!err('outcome')">
                                <option value="">Choose</option>
                                @foreach ($outcomes as $outcome)
                                    <option value="{{ $outcome->value }}">{{ $outcome->label() }}</option>
                                @endforeach
                            </select>
                            <p class="error" x-show="err('outcome')" x-text="err('outcome')"></p>
                        </div>
                    </div>
                    <div x-show="form.kind === 'event_check_in'">
                        <label class="label" for="event_id">Which event are you at?</label>
                        <select id="event_id" class="field" x-model="form.event_id" :aria-invalid="!!err('event_id')">
                            <option value="">Choose the event</option>
                            @foreach ($events as $event)
                                <option value="{{ $event->id }}">{{ $event->title }} · {{ $event->starts_at->setTimezone($tz)->format('D j M, g:ia') }}</option>
                            @endforeach
                        </select>
                        @if ($events->isEmpty())<p class="hint">No events are posted for your state right now.</p>@endif
                        <p class="error" x-show="err('event_id')" x-text="err('event_id')"></p>
                    </div>
                    <div>
                        <label class="label" for="note"><span x-text="form.kind === 'report' ? 'What did you do?' : 'Note'"></span> <span class="font-normal text-neutral-500" x-show="form.kind !== 'report'">(optional)</span></label>
                        <textarea id="note" rows="3" maxlength="2000" class="field" x-model="form.note" :aria-invalid="!!err('note')"></textarea>
                        <p class="error" x-show="err('note')" x-text="err('note')"></p>
                    </div>
                    <div>
                        <label class="label" for="photo">Photo <span class="font-normal text-neutral-500">(optional)</span></label>
                        <input id="photo" x-ref="photo" type="file" accept="image/*" capture="environment" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand/10 file:px-3 file:py-2 file:font-semibold file:text-brand-dark" x-on:change="pickPhoto">
                        <p class="error" x-show="err('photo')" x-text="err('photo')"></p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <button class="btn-primary" :disabled="busy">Save</button>
                        <p class="text-sm text-neutral-600" x-text="formMessage" role="status"></p>
                    </div>
                </form>
            </section>

            {{-- Register someone in person --}}
            <section class="panel p-5 sm:p-6" x-show="onShift" x-cloak>
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="font-display text-lg font-bold">Register someone</h2>
                    <p class="text-sm text-neutral-600"><span class="font-semibold tabular-nums" x-text="registeredToday"></span> registered by you today</p>
                </div>
                <p class="mt-1 text-sm text-neutral-600">For people without a smartphone. They get their membership number now and their sign-in details by SMS later.</p>

                <form class="mt-5 space-y-4" x-on:submit.prevent="registerMember">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="reg-name">Full name</label>
                            <input id="reg-name" x-ref="regName" class="field" autocomplete="off" maxlength="120" x-model="reg.name" :aria-invalid="!!regErr('name')">
                            <p class="error" x-show="regErr('name')" x-text="regErr('name')"></p>
                        </div>
                        <div>
                            <label class="label" for="reg-phone">Phone number</label>
                            <input id="reg-phone" type="tel" inputmode="tel" class="field" autocomplete="off" placeholder="0803 000 0000" x-model="reg.phone" :aria-invalid="!!regErr('phone')">
                            <p class="error" x-show="regErr('phone')" x-text="regErr('phone')"></p>
                        </div>
                        <div>
                            <label class="label" for="reg-gender">Gender</label>
                            <select id="reg-gender" class="field" x-model="reg.gender" :aria-invalid="!!regErr('gender')">
                                <option value="">Choose</option>
                                @foreach ($genders as $g)<option value="{{ $g->value }}">{{ $g->label() }}</option>@endforeach
                            </select>
                            <p class="error" x-show="regErr('gender')" x-text="regErr('gender')"></p>
                        </div>
                        <div>
                            <label class="label" for="reg-age">Age range</label>
                            <select id="reg-age" class="field" x-model="reg.age_range" :aria-invalid="!!regErr('age_range')">
                                <option value="">Choose</option>
                                @foreach ($ageRanges as $a)<option value="{{ $a->value }}">{{ $a->label() }}</option>@endforeach
                            </select>
                            <p class="error" x-show="regErr('age_range')" x-text="regErr('age_range')"></p>
                        </div>
                        <div>
                            <label class="label" for="reg-lga">LGA</label>
                            <select id="reg-lga" class="field" x-model="reg.lga_id" x-on:change="regLoadWards" :aria-invalid="!!regErr('lga_id')">
                                <option value="">Choose</option>
                                @foreach ($lgas as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach
                            </select>
                            <p class="error" x-show="regErr('lga_id')" x-text="regErr('lga_id')"></p>
                        </div>
                        <div>
                            <label class="label" for="reg-ward">Ward</label>
                            <select id="reg-ward" class="field" x-model="reg.ward_id" x-on:change="regLoadUnits" :disabled="!regWards.length" :aria-invalid="!!regErr('ward_id')">
                                <option value="" x-text="regLoading ? 'Loading…' : (reg.lga_id && !regWards.length ? 'No wards listed' : 'Choose')"></option>
                                <template x-for="w in regWards" :key="w.id"><option :value="String(w.id)" x-text="w.name"></option></template>
                            </select>
                            <p class="error" x-show="regErr('ward_id')" x-text="regErr('ward_id')"></p>
                        </div>
                    </div>
                    <div>
                        <label class="label" :for="regUnits.length ? 'reg-unit' : 'reg-unit-name'">Polling unit <span class="font-normal text-neutral-500">(optional)</span></label>
                        <select id="reg-unit" class="field" x-show="regUnits.length" x-model="reg.polling_unit_id">
                            <option value="">Not sure</option>
                            <template x-for="u in regUnits" :key="u.id"><option :value="String(u.id)" x-text="u.code + ' · ' + u.name"></option></template>
                            <option value="other">Not listed</option>
                        </select>
                        <input id="reg-unit-name" class="field" :class="regUnits.length ? 'mt-2' : ''" maxlength="120" placeholder="Type the polling unit" x-model="reg.polling_unit_name" x-show="!regUnits.length || reg.polling_unit_id === 'other'">
                        <p class="error" x-show="regErr('polling_unit_id') || regErr('polling_unit_name')" x-text="regErr('polling_unit_id') || regErr('polling_unit_name')"></p>
                    </div>
                    <fieldset>
                        <legend class="label">Do they have their PVC?</legend>
                        <div class="flex gap-2">
                            <label class="chip"><input type="radio" class="sr-only" name="reg_pvc" :checked="reg.has_pvc === true" x-on:change="reg.has_pvc = true"> Yes</label>
                            <label class="chip"><input type="radio" class="sr-only" name="reg_pvc" :checked="reg.has_pvc === false" x-on:change="reg.has_pvc = false"> No</label>
                        </div>
                        <p class="error" x-show="regErr('has_pvc')" x-text="regErr('has_pvc') && 'Do they have their PVC?'"></p>
                    </fieldset>
                    <div class="rounded-xl bg-surface p-4 text-sm">
                        <p class="font-semibold">Read this to them:</p>
                        <p class="mt-1 text-neutral-700">“Renewed Hope Youth Engagement will store your details to coordinate the movement, under the Nigeria Data Protection Act 2023. Your data will never be sold, and you can ask for it to be deleted at any time.”</p>
                        <label class="mt-3 flex items-start gap-2">
                            <input type="checkbox" class="mt-1" x-model="reg.consent">
                            <span>I read this to them and they agreed to join.</span>
                        </label>
                        <p class="error" x-show="regErr('consent')" x-text="regErr('consent')"></p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <button class="btn-primary" :disabled="regBusy"><x-icon name="user-plus" /> Register</button>
                        <p class="text-sm text-neutral-700" x-text="regMessage" role="status"></p>
                    </div>
                </form>

                <ul class="mt-4 divide-y divide-line text-sm" x-show="registered.length">
                    <template x-for="m in registered" :key="m.member_number">
                        <li class="flex justify-between gap-2 py-2"><span x-text="m.name"></span><span class="font-mono text-neutral-600" x-text="m.member_number"></span></li>
                    </template>
                </ul>
            </section>

            {{-- Today --}}
            <section class="panel p-5 sm:p-6">
                <h2 class="font-display text-lg font-bold">Today</h2>
                <ul class="mt-3 divide-y divide-line" x-show="activities.length">
                    <template x-for="a in activities" :key="a.uuid">
                        <li class="flex items-start gap-3 py-3 text-sm">
                            <span class="w-14 shrink-0 text-neutral-500" x-text="a.time"></span>
                            <span class="min-w-0"><span class="block font-semibold" x-text="a.label"></span><span class="block text-neutral-600" x-text="a.summary"></span></span>
                        </li>
                    </template>
                </ul>
                <p class="mt-3 text-sm text-neutral-500" x-show="!activities.length">Nothing logged yet today.</p>
            </section>

            <form method="POST" action="{{ route('membership.field.consent.withdraw') }}" x-data x-on:submit="if (!confirm('Stop sharing your location and withdraw your consent?')) $event.preventDefault()">
                @csrf @method('DELETE')
                <button class="text-sm font-medium text-red-700 hover:underline">Withdraw my consent to location sharing</button>
            </form>
        </div>
    @endunless
</x-layouts.app>
