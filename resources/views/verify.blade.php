<x-layouts.auth title="Verify membership" width="max-w-md" noindex>
    <div class="panel p-6 text-center sm:p-8">
        @if ($valid)
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-brand/10 text-brand"><x-icon name="check" class="h-8 w-8" /></span>
            <p class="mt-4 text-sm font-semibold tracking-wide text-brand uppercase">Verified member</p>
            <h1 class="mt-1 font-display text-2xl font-bold">{{ $name }}</h1>
            <dl class="mt-5 grid grid-cols-2 gap-3 text-left">
                <div class="rounded-xl bg-surface p-3"><dt class="text-xs text-neutral-500">State</dt><dd class="font-semibold">{{ $state ?? '—' }}</dd></div>
                <div class="rounded-xl bg-surface p-3"><dt class="text-xs text-neutral-500">Member since</dt><dd class="font-semibold">{{ $since->setTimezone(config('membership.timezone'))->format('M Y') }}</dd></div>
            </dl>
            <p class="mt-5 text-sm text-neutral-600">This person is an active member of Renewed Hope Youth Engagement.</p>
        @else
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-red-50 text-red-600"><x-icon name="x" class="h-8 w-8" /></span>
            <h1 class="mt-4 font-display text-2xl font-bold">Not a valid membership card</h1>
            <p class="mt-2 text-sm text-neutral-600">We could not find an active member for this code. The card may be out of date, or the code may have been copied wrongly.</p>
        @endif
    </div>
    <p class="mt-6 text-center text-sm text-neutral-600"><a href="{{ route('join') }}" class="font-semibold text-brand">Join the movement</a></p>
</x-layouts.auth>
