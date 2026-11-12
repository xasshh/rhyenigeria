@php
    $electionYear = \App\Models\Setting::electionDate()?->setTimezone(config('membership.timezone'))->format('Y');
    $messages = [
        ['kicker' => "I'm a proud", 'headline' => 'Renewed Hope Youth member'],
        ['kicker' => 'Youth for PBAT', 'headline' => 'Renewed Hope, Nation Rebuilt'],
        ['kicker' => 'Renewed Hope Youth Engagement', 'headline' => 'Every ward. Every youth.'],
    ];
    $place = collect([$user->lga?->name, $user->state?->name])->filter()->implode(', ');
@endphp
<x-layouts.app title="Make your campaign DP">
    <section class="-mx-4 -mt-5 bg-gradient-to-br from-brand-dark to-brand px-4 py-8 text-white sm:-mx-6 sm:px-6 lg:-mx-10 lg:-mt-9 lg:px-10 lg:py-10">
        <p class="text-xs font-semibold tracking-[0.16em] text-accent uppercase">Show your support</p>
        <h1 class="mt-2 font-display text-[28px] leading-tight font-bold tracking-tight sm:text-4xl">Make your campaign DP</h1>
        <p class="mt-2 max-w-xl text-white/85">Put your photo in an official Renewed Hope Youth Engagement frame, ready for your WhatsApp profile picture and status.</p>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_minmax(0,460px)]"
        x-data="dpMaker(@js(['messages' => $messages, 'name' => trim($user->name.($place ? ' · '.$place : '')), 'logo' => asset('images/logo-renewed-hope.png'), 'year' => $electionYear, 'shareText' => 'Join me in the Renewed Hope Youth Engagement movement: '.route('join', ['ref' => $user->memberProfile->referral_code])]))">
        <div class="order-2 space-y-4 lg:order-1">
            <section class="panel p-5">
                <h2 class="font-display font-bold">1. Add your photo</h2>
                <p class="mt-1 text-sm text-neutral-600">A clear, smiling photo of your face and shoulders works best.</p>
                <label class="btn-primary mt-4 cursor-pointer">
                    <x-icon name="image" /> <span x-text="photo ? 'Change photo' : 'Choose photo'">Choose photo</span>
                    <input type="file" accept="image/*" class="sr-only" x-on:change="pick">
                </label>
            </section>
            <section class="panel p-5">
                <h2 class="font-display font-bold">2. Choose your message</h2>
                <div class="mt-3 space-y-2">
                    <template x-for="(m, i) in messages" :key="i">
                        <label class="block cursor-pointer rounded-xl border px-4 py-3 transition" :class="choice === i ? 'border-brand bg-brand/5 ring-1 ring-brand' : 'border-line hover:border-brand/50'">
                            <input type="radio" class="sr-only" :value="i" x-model.number="choice">
                            <span class="block text-[11px] font-semibold tracking-[0.14em] text-brand uppercase" x-text="m.kicker"></span>
                            <span class="block font-bold uppercase" x-text="m.headline"></span>
                        </label>
                    </template>
                </div>
                <label class="label mt-4" for="dp-name">Your name (optional)</label>
                <input id="dp-name" class="field" x-model="name" placeholder="e.g. Zainab Abdullahi · Gwale, Kano" maxlength="60">
            </section>
            <p class="flex items-center gap-2 text-sm text-neutral-500"><x-icon name="lock" class="h-4 w-4" /> Your photo stays on your phone. It is never uploaded to our servers.</p>
        </div>

        <div class="order-1 lg:sticky lg:top-6 lg:order-2 lg:self-start">
            <canvas x-ref="canvas" width="1080" height="1080" class="aspect-square w-full rounded-2xl shadow-xl shadow-brand/20" aria-label="Preview of your campaign picture"></canvas>
            <div class="mt-3 grid grid-cols-2 gap-2">
                <button type="button" class="btn-secondary" x-on:click="download" :disabled="busy"><x-icon name="download" /> Download</button>
                <button type="button" class="btn-primary" x-on:click="share" :disabled="busy"><x-icon name="share" /> Share</button>
            </div>
        </div>
    </div>
</x-layouts.app>
