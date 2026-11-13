<x-layouts.split title="Join the movement" stats>
    <x-slot:top>Already a member? <a href="{{ route('login') }}" class="font-semibold text-action hover:underline">Sign in</a></x-slot:top>

    <x-slot:aside>
        <h1 class="font-headline text-[34px] leading-[1.02] font-extrabold tracking-[-0.025em] sm:text-[42px] lg:text-[54px]">
            Nigeria's youth, <br class="hidden lg:block">organised <br class="hidden lg:block"><span class="text-mint">ward <br class="hidden lg:block">by ward.</span>
        </h1>
        <p class="mt-4 hidden max-w-[390px] text-[19px] leading-relaxed text-white/80 sm:block lg:mt-6">Sign up once and get a digital membership card, updates from your state, and a direct line to your ward team.</p>
        <x-card-preview class="mt-12 hidden lg:block" name="Adaeze Okafor" state="Lagos" ward="Ikeja · Ward 04" number="RHYE-LA-004821" />
    </x-slot:aside>

    <div class="lg:-ml-[50px] lg:w-[calc(100%+100px)]">
        <p class="eyebrow text-action">Join the movement</p>
        <h2 class="mt-3 font-headline text-[36px] leading-[1.05] font-extrabold tracking-[-0.03em] sm:text-[48px]">How would you like to take part?</h2>
        <p class="mt-4 text-lg leading-relaxed text-neutral-600">It takes about two minutes. You can upgrade from Member to Canvasser later.</p>

        <div class="mt-9 space-y-4">
            @foreach ([
                ['member', 'Member', 'Stay informed and counted.', 'card', null, ['Digital membership card with QR code', 'Event updates and news for your state', 'Invite friends with your member number']],
                ['canvasser', 'Canvasser', 'Become a member and work in your ward.', 'shield', 'Reviewed', ['Everything a Member gets', 'Door-to-door, social media, events and more', 'Your state coordinator reviews every application']],
            ] as [$as, $label, $text, $icon, $tag, $points])
                <a href="{{ route('join', array_filter(['as' => $as, 'ref' => $ref, 'state' => $stateCode])) }}" @class([
                    'group block rounded-2xl border-2 bg-white p-5 transition sm:p-7 focus-visible:ring-4 focus-visible:ring-action/25 focus-visible:outline-none',
                    'border-action' => $loop->first,
                    'border-line hover:border-action/70' => ! $loop->first,
                ])>
                    <span class="flex items-center gap-4 sm:gap-5">
                        <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-[#eaf3ec] text-action"><x-icon :name="$icon" class="h-6 w-6" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-2.5">
                                <span class="font-headline text-[24px] leading-tight font-bold"><span class="sr-only">Join as a </span>{{ $label }}</span>
                                @if ($tag)<span class="rounded-full bg-amber-soft px-2.5 py-0.5 text-[13px] font-semibold text-amber-ink">{{ $tag }}</span>@endif
                            </span>
                            <span class="mt-0.5 block text-[16px] text-neutral-600">{{ $text }}</span>
                        </span>
                        <span @class([
                            'flex h-12 w-12 shrink-0 items-center justify-center rounded-full transition group-hover:translate-x-0.5',
                            'bg-action text-white' => $loop->first,
                            'bg-surface text-ink group-hover:bg-action group-hover:text-white' => ! $loop->first,
                        ])><x-icon name="arrow-right" class="h-5 w-5" /></span>
                    </span>
                    <span class="mt-5 block border-t border-line pt-5">
                        @foreach ($points as $point)
                            <span class="flex items-center gap-3 py-1 text-[16px]"><x-icon name="check" class="h-4 w-4 text-action" /> {{ $point }}</span>
                        @endforeach
                    </span>
                </a>
            @endforeach
        </div>

        <p class="mt-7 flex flex-wrap gap-x-7 gap-y-2 text-[15px] text-neutral-600">
            <span class="flex items-center gap-2"><x-icon name="clock" class="h-4 w-4" /> About 2 minutes</span>
            <span class="flex items-center gap-2"><x-icon name="lock" class="h-4 w-4" /> NDPA 2023 protected</span>
            <span class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4" /> Free to join</span>
        </p>
    </div>
</x-layouts.split>
