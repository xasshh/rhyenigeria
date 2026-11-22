<x-layouts.public title="State WhatsApp groups" description="Join your state's {{ config('site.short') }} WhatsApp group: choose your state, meet your coordinator, register, and get the link on your dashboard." current="whatsapp">
    <section class="relative isolate overflow-hidden bg-forest text-white">
        <x-fingerprint class="absolute -top-24 -right-24 -z-10 w-[420px] text-accent/[0.2]" />
        <div class="mx-auto max-w-[1440px] px-5 py-14 sm:px-8 lg:px-[66px] lg:py-20">
            <p class="eyebrow text-mint">Stay connected</p>
            <h1 class="mt-3 font-headline text-[40px] leading-[1.02] font-extrabold tracking-[-0.03em] sm:text-[60px]">Join your state WhatsApp group</h1>
            <p class="mt-4 max-w-[680px] font-body text-lg leading-relaxed text-white/80 sm:text-xl">Choose your state and meet your coordinator. Register (or sign in) and the group link is waiting on your dashboard.</p>
        </div>
    </section>

    <section class="mx-auto max-w-[1100px] px-5 py-12 font-body sm:px-8 lg:py-16"
             x-data="{ states: @js($states), code: @js($selected ?? ''), get state() { return this.states.find((s) => s.code === this.code) || null } }">

        @if ($member && $memberGroup)
            <div class="mb-8 flex flex-wrap items-center gap-4 rounded-2xl border border-[#25d366]/40 bg-[#25d366]/10 p-5">
                <x-icon name="whatsapp" class="h-8 w-8 text-[#128c4a]" />
                <p class="min-w-0 flex-1 text-[17px] text-forest">You're signed in as a member in <strong>{{ $member->state?->name }}</strong>. Your group is ready.</p>
                <a href="{{ $memberGroup }}" target="_blank" rel="noopener" class="inline-flex min-h-12 items-center gap-2 rounded-xl bg-[#25d366] px-5 font-bold text-white hover:bg-[#1fb457]">Open the {{ $member->state?->name }} group</a>
            </div>
        @endif

        {{-- Step 1 --}}
        <div class="flex items-center gap-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-action font-bold text-white">1</span>
            <label for="wa-state" class="font-headline text-[24px] font-extrabold text-forest">Choose your state</label>
        </div>
        <select id="wa-state" x-model="code" class="field-lg mt-4 max-w-md">
            <option value="">Select a state</option>
            @foreach ($states as $s)
                <option value="{{ $s['code'] }}" @selected($selected === $s['code'])>{{ $s['name'] }}</option>
            @endforeach
        </select>

        {{-- Step 2 --}}
        <template x-if="state">
            <div class="mt-10 grid gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <div>
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-action font-bold text-white">2</span>
                        <h2 class="font-headline text-[24px] font-extrabold text-forest">Meet your coordinator</h2>
                    </div>
                    <div class="mt-4 flex items-center gap-5 rounded-[20px] border border-line bg-white p-5 shadow-[0_20px_40px_-30px_rgba(15,42,28,0.4)]">
                        <template x-if="state.photo">
                            <img :src="state.photo" :alt="'Photo of ' + (state.coordinator || 'the ' + state.name + ' coordinator')" class="h-28 w-28 shrink-0 rounded-2xl object-cover object-top ring-4 ring-action/15 sm:h-36 sm:w-36">
                        </template>
                        <template x-if="!state.photo">
                            <div class="flex h-28 w-28 shrink-0 items-center justify-center rounded-2xl bg-action/10 text-action sm:h-36 sm:w-36"><x-icon name="users" class="h-10 w-10" /></div>
                        </template>
                        <div class="min-w-0">
                            <p class="text-[13px] font-semibold tracking-[0.12em] text-action uppercase" x-text="state.name + ' State Coordinator'"></p>
                            <p class="mt-1 font-headline text-[24px] leading-tight font-extrabold text-forest" x-text="state.coordinator || 'To be announced'"></p>
                            <p class="mt-2 text-[15px] text-neutral-600" x-text="state.hasGroup ? 'Runs the ' + state.name + ' WhatsApp group.' : 'The ' + state.name + ' group is being set up. Register now and the link will appear on your dashboard when it is ready.'"></p>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-action font-bold text-white">3</span>
                        <h2 class="font-headline text-[24px] font-extrabold text-forest">Register, then join</h2>
                    </div>
                    <div class="mt-4 rounded-[20px] border border-line bg-surface p-5">
                        <p class="text-[16px] text-neutral-700">The group link is only shared with registered members. It takes about two minutes, and you get your digital membership card too.</p>
                        @if ($member)
                            <a href="{{ route('membership.home') }}#whatsapp" class="mt-4 inline-flex min-h-[52px] w-full items-center justify-center gap-2 rounded-xl bg-accent px-6 text-[17px] font-bold text-forest hover:bg-[#d9a404]">Go to my dashboard <x-icon name="arrow-right" class="h-4 w-4" /></a>
                        @else
                            <a :href="state.join" class="mt-4 inline-flex min-h-[52px] w-full items-center justify-center gap-2 rounded-xl bg-accent px-6 text-[17px] font-bold text-forest hover:bg-[#d9a404]"><span x-text="'Register in ' + state.name"></span> <x-icon name="arrow-right" class="h-4 w-4" /></a>
                            <p class="mt-3 text-center text-[15px] text-neutral-600">Already a member? <a href="{{ route('login') }}" class="font-bold text-action hover:underline">Sign in</a> and open your dashboard.</p>
                        @endif
                    </div>
                </div>
            </div>
        </template>
    </section>
</x-layouts.public>
