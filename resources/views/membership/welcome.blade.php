@php
    $applying = $profile->canvasser_status?->value === 'pending';
    $wardLine = collect([$user->lga?->name, $user->ward?->name])->filter()->implode(' · ') ?: '—';
    $place = array_values(array_filter([
        trim(collect([$user->ward?->name, $user->lga?->name])->filter()->implode(', ')),
        $user->state ? $user->state->name.' State' : null,
    ]));
    $cardConfig = [
        'name' => $user->name, 'number' => $profile->member_number, 'place' => $place,
        'joined' => $profile->created_at->setTimezone(config('membership.timezone'))->format('j M Y'),
        'role' => $profile->roleLabel(), 'logo' => asset('images/logo-renewed-hope.png'), 'slogan' => config('membership.slogan'),
    ];
@endphp
<x-layouts.base title="Welcome" noindex>
    <div class="relative isolate min-h-dvh overflow-hidden bg-forest text-white">
        <x-fingerprint class="absolute -bottom-40 -left-40 -z-10 w-[640px] text-white/[0.06]" />

        <header class="flex items-center justify-between px-5 py-5 sm:px-8 lg:px-14 lg:py-7">
            <a href="{{ route('home') }}" class="rounded-2xl bg-white px-4 py-2.5"><img src="{{ asset('images/logo-rhye.png') }}" alt="RHYE — Renewed Hope Youth Engagement" class="h-9 w-auto" width="600" height="272"></a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-[17px] font-medium text-white/90 hover:text-white">Sign out</button></form>
        </header>

        <main class="mx-auto grid max-w-[1190px] items-center gap-12 px-5 pt-8 pb-16 sm:px-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,597px)] lg:gap-20 lg:pt-20">
            <div>
                <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5 text-[13px] font-semibold tracking-[0.14em] text-mint uppercase"><x-icon name="check" class="h-3.5 w-3.5" /> You're in</p>
                <h1 class="mt-4 font-headline text-[40px] leading-[1.02] font-extrabold tracking-[-0.03em] sm:text-[52px] lg:text-[60px]">
                    @if ($applying)
                        You're a member. <br class="hidden sm:block">Application sent.
                    @else
                        Welcome to the movement, {{ $user->firstName() }}.
                    @endif
                </h1>
                <p class="mt-5 max-w-[510px] text-[19px] leading-relaxed text-white/80">
                    {{ $applying
                        ? "Your card works right away. We'll let you know as soon as your state coordinator reviews your canvasser application."
                        : 'Your digital membership card is ready. Show it at events or save it to your phone.' }}
                </p>

                <x-card-preview class="mt-10 max-w-[482px]" :name="$user->name" :badge="$applying ? 'Canvasser · in review' : $profile->roleLabel().' · Active'" :tone="$applying ? 'canvasser' : 'member'"
                    :state="$user->state?->name ?? '—'" :ward="$wardLine" :number="$profile->member_number" :qr="$qrSvg" />

                <div class="mt-8 flex flex-wrap gap-3">
                    <button type="button" class="btn min-h-[56px] rounded-xl bg-leaf px-7 text-[17px] text-forest hover:bg-mint" x-data="memberCard(@js($cardConfig))" x-on:click="download" :disabled="busy">
                        <x-icon name="download" class="h-5 w-5" /> <span x-text="busy ? 'Preparing…' : 'Download card'">Download card</span>
                    </button>
                    <button type="button" class="btn min-h-[56px] rounded-xl border border-white/25 px-7 text-[17px] text-white hover:bg-white/10" x-data="copyText(@js($inviteUrl))" x-on:click="copy">
                        <x-icon name="link" class="h-5 w-5" /> <span x-text="copied ? 'Link copied' : 'Copy invite link'">Copy invite link</span>
                    </button>
                </div>
            </div>

            <section class="rounded-[28px] bg-white p-6 text-ink sm:p-9">
                @if ($applying)
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-headline text-[28px] font-bold tracking-[-0.02em]">Canvasser application</h2>
                        <span class="rounded-full bg-amber-soft px-3 py-1 text-sm font-semibold text-amber-ink">In review</span>
                    </div>
                    @php
                        $roles = collect($profile->canvasser_roles ?? [])->map(fn ($r) => \App\Enums\CanvasserRole::tryFrom($r)?->label())->filter()->map(fn ($l) => lcfirst($l))->implode(', ');
                        $timeline = [
                            ['done', 'Member account created', 'Your card is active now.'],
                            ['done', 'Application sent', $roles ? ucfirst($roles).'.' : 'Your state coordinator has it.'],
                            ['wait', ($user->state?->name ? $user->state->name.' state' : 'State').' coordinator review', "They'll call or WhatsApp you on ".\App\Support\NigerianPhone::mask($profile->whatsapp_phone ?? $user->phone).'.'],
                            ['todo', 'Ward assignment', "You'll get your ward team and first activity."],
                        ];
                    @endphp
                    <ol class="mt-7 space-y-6">
                        @foreach ($timeline as [$status, $title, $text])
                            <li class="flex gap-4">
                                <span @class(['flex h-11 w-11 shrink-0 items-center justify-center rounded-full', 'bg-action text-white' => $status === 'done', 'bg-amber-soft text-[#c98f00] ring-4 ring-amber-soft/60' => $status === 'wait', 'bg-surface text-neutral-500' => $status === 'todo'])>
                                    <x-icon :name="['done' => 'check', 'wait' => 'clock', 'todo' => 'pin'][$status]" class="h-5 w-5" />
                                </span>
                                <span><span class="block text-[18px] font-semibold">{{ $title }}</span><span class="mt-0.5 block text-[16px] text-neutral-600">{{ $text }}</span></span>
                            </li>
                        @endforeach
                    </ol>
                    <p class="mt-7 flex items-start gap-3 rounded-2xl bg-surface p-5 text-[16px] text-neutral-700"><x-icon name="help" class="mt-0.5 text-action" /> While you wait, you can still attend events and invite friends as a member.</p>
                    <a href="{{ route('membership.home') }}" class="btn-action mt-7 w-full text-[17px]">Go to my dashboard <x-icon name="arrow-right" class="h-4 w-4" /></a>
                @else
                    <h2 class="font-headline text-[28px] font-bold tracking-[-0.02em]">What's next</h2>
                    <ul class="mt-7 space-y-6">
                        <li class="flex gap-4">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-action text-white"><x-icon name="check" class="h-5 w-5" /></span>
                            <span><span class="block text-[18px] font-semibold">Account created</span><span class="mt-0.5 block text-[16px] text-neutral-600">Sign in any time with your email.</span></span>
                        </li>
                        <li class="flex gap-4">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-surface text-neutral-600"><x-icon name="chat" class="h-5 w-5" /></span>
                            <span>
                                <span class="block text-[18px] font-semibold">Join your {{ $whatsappGroup ? 'state' : 'ward' }} WhatsApp</span>
                                @if ($whatsappGroup)
                                    <a href="{{ $whatsappGroup }}" target="_blank" rel="noopener" class="mt-0.5 inline-flex items-center gap-1.5 text-[16px] font-semibold text-action hover:underline"><x-icon name="whatsapp" class="h-4 w-4" /> Open the {{ $user->state?->name }} group</a>
                                @else
                                    <span class="mt-0.5 block text-[16px] text-neutral-600">Your {{ $wardLine !== '—' ? $wardLine : 'ward' }} coordinator shares the link here once your ward is live.</span>
                                @endif
                            </span>
                        </li>
                        @if ($profile->needsVin())
                            <li class="flex gap-4">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-surface text-neutral-600"><x-icon name="card" class="h-5 w-5" /></span>
                                <span><a href="{{ route('membership.card') }}#voter" class="block text-[18px] font-semibold hover:text-action">Add your VIN</a><span class="mt-0.5 block text-[16px] text-neutral-600">Optional — helps us confirm you're ready to vote.</span></span>
                            </li>
                        @endif
                        <li class="flex gap-4">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-surface text-neutral-600"><x-icon name="users" class="h-5 w-5" /></span>
                            <span>
                                <span class="block text-[18px] font-semibold">Invite 5 friends</span>
                                <span class="mt-0.5 block text-[16px] text-neutral-600">Share your link. Each friend who joins is counted under your member number.</span>
                                <a href="{{ $shareLinks['WhatsApp'] }}" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-1.5 text-[15px] font-semibold text-[#128c4a] hover:underline"><x-icon name="whatsapp" class="h-4 w-4" /> Share on WhatsApp</a>
                            </span>
                        </li>
                    </ul>
                    <div class="mt-7 border-t border-line pt-7">
                        <a href="{{ route('membership.home') }}" class="btn-action w-full text-[17px]">Go to my dashboard <x-icon name="arrow-right" class="h-4 w-4" /></a>
                        @if ($profile->canvasser_status === null)
                            <a href="{{ route('membership.home') }}#canvass" class="mt-5 flex items-center justify-center gap-2 text-[16px] font-semibold text-action hover:underline"><x-icon name="shield" class="h-4 w-4" /> Want to do more? Apply as a canvasser</a>
                        @endif
                    </div>
                @endif
            </section>
        </main>
    </div>
</x-layouts.base>
