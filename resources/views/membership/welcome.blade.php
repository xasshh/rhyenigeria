<x-layouts.auth title="Welcome" width="max-w-xl" noindex>
    <div class="text-center">
        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-brand/10 text-brand"><x-icon name="check" class="h-7 w-7" /></span>
        <h1 class="mt-4 font-display text-[26px] leading-tight font-bold tracking-tight sm:text-3xl">Welcome to the movement, {{ $user->firstName() }}!</h1>
        <p class="mt-2 text-neutral-600">Your digital membership card is ready. Show the QR code at events.</p>
    </div>

    <x-membership-card :user="$user" :profile="$profile" :qr-svg="$qrSvg" class="mt-7" />

    @if ($profile->canvasser_status?->value === 'pending')
        <p class="mt-5 rounded-xl border border-brand/25 bg-brand/5 px-4 py-3 text-sm text-brand-dark">Your canvasser application has gone to your state coordinator. We'll update your card once it is approved.</p>
    @endif
    @if ($profile->needsVin())
        <p class="mt-5 rounded-xl border border-accent/40 bg-accent/10 px-4 py-3 text-sm text-[#7a5b00]">Add your VIN under <a href="{{ route('membership.card') }}" class="font-semibold underline">My card</a> so it counts.</p>
    @endif

    <x-share-panel class="mt-5" :share-links="$shareLinks" :invite-url="$inviteUrl" :whatsapp-group="$whatsappGroup" />

    <a href="{{ route('membership.home') }}" class="btn-primary mt-5 w-full">Go to my dashboard</a>
</x-layouts.auth>
