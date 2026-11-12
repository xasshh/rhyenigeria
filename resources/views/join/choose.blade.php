<x-layouts.auth title="Join the movement">
    <h1 class="font-display text-[28px] leading-tight font-bold tracking-tight">Join the movement</h1>
    <p class="mt-1.5 text-neutral-600">It takes about two minutes. How would you like to take part?</p>

    <div class="mt-7 grid gap-3">
        @foreach ([
            ['member', 'Join as a Member', 'Get your digital membership card, event updates and news for your state.', 'card'],
            ['canvasser', 'Join as a Canvasser', 'Become a member and apply to work in your ward: door-to-door, social media, events and more. Your state coordinator reviews every application.', 'badge'],
        ] as [$as, $label, $text, $icon])
            <a href="{{ route('join', array_filter(['as' => $as, 'ref' => $ref])) }}" class="group panel flex items-start gap-4 p-5 transition hover:border-brand hover:shadow-md hover:shadow-brand/10 focus-visible:ring-2 focus-visible:ring-brand/40 focus-visible:outline-none">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-brand/10 text-brand"><x-icon :name="$icon" class="h-6 w-6" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block font-display text-lg font-bold">{{ $label }}</span>
                    <span class="mt-1 block text-sm leading-relaxed text-neutral-600">{{ $text }}</span>
                </span>
                <x-icon name="arrow-right" class="mt-3 text-neutral-400 transition group-hover:translate-x-0.5 group-hover:text-brand" />
            </a>
        @endforeach
    </div>

    <p class="mt-8 text-center text-sm text-neutral-600">Already a member? <a href="{{ route('login') }}" class="font-semibold text-brand">Sign in</a></p>
</x-layouts.auth>
