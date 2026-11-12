<x-layouts.app title="Home">
    <p class="text-sm text-neutral-500">{{ $user->state?->name ? $user->state->name.' State' : '' }}</p>
    <h1 class="font-display text-2xl font-bold tracking-tight sm:text-[28px]">Hello, {{ $user->firstName() }}</h1>
    <x-flash class="mt-4" />

    <div class="mt-5 grid gap-4 lg:grid-cols-2">
        {{-- Election countdown --}}
        <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-brand-dark to-brand p-5 text-white">
            <p class="text-xs font-semibold tracking-[0.14em] text-white/75 uppercase">{{ $electionLabel }}</p>
            @if ($electionDate)
                <div x-data="countdown(@js($electionDate->toIso8601String()))" class="mt-3">
                    <div class="grid grid-cols-4 gap-2 text-center">
                        @foreach (['days' => 'Days', 'hours' => 'Hours', 'minutes' => 'Mins', 'seconds' => 'Secs'] as $unit => $label)
                            <div class="rounded-xl bg-white/12 py-2.5">
                                <p class="text-2xl font-bold tabular-nums sm:text-3xl" x-text="{{ $unit === 'days' ? 'days' : 'pad('.$unit.')' }}">–</p>
                                <p class="text-[11px] text-white/75">{{ $label }}</p>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-3 text-sm text-white/85">{{ $electionDate->setTimezone(config('membership.timezone'))->format('l j F Y') }}</p>
                </div>
            @else
                <p class="mt-3 text-lg font-semibold">The date will be announced soon.</p>
            @endif
        </section>

        {{-- Next event --}}
        <section class="panel p-5">
            <p class="text-xs font-semibold tracking-[0.14em] text-neutral-500 uppercase">Next event</p>
            @if ($nextEvent)
                <h2 class="mt-2 font-display text-lg font-bold">{{ $nextEvent->title }}</h2>
                <p class="mt-1 flex items-center gap-1.5 text-sm text-neutral-600"><x-icon name="calendar" class="h-4 w-4" /> {{ $nextEvent->starts_at->setTimezone(config('membership.timezone'))->format('D j M, g:ia') }}</p>
                @if ($nextEvent->venue)<p class="mt-0.5 flex items-center gap-1.5 text-sm text-neutral-600"><x-icon name="pin" class="h-4 w-4" /> {{ $nextEvent->venue }}</p>@endif
                <div x-data="countdown(@js($nextEvent->starts_at->toIso8601String()))" class="mt-3 inline-flex rounded-lg bg-accent/15 px-3 py-1.5 text-sm font-semibold text-[#7a5b00]">
                    <span x-show="!done">Starts in <span x-text="days ? days + 'd ' + hours + 'h' : hours + 'h ' + pad(minutes) + 'm'"></span></span>
                    <span x-show="done">Happening now</span>
                </div>
            @else
                <p class="mt-2 text-neutral-600">No upcoming events yet. We'll post them here.</p>
            @endif
            <a href="{{ route('membership.events') }}" class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-brand">All events & news <x-icon name="arrow-right" class="h-4 w-4" /></a>
        </section>
    </div>

    @if ($profile->needsVin())
        <a href="{{ route('membership.card') }}#voter" class="mt-4 flex items-center gap-3 rounded-2xl border border-accent/40 bg-accent/10 p-4 text-sm text-[#7a5b00]">
            <x-icon name="alert" /> <span class="flex-1">Add your VIN under <strong>My card</strong> so it counts.</span> <x-icon name="arrow-right" class="h-4 w-4" />
        </a>
    @endif

    {{-- Canvassing --}}
    <section class="panel mt-4 p-5">
        @switch($profile->canvasser_status?->value)
            @case('approved')
                <div class="flex items-center gap-3"><span class="badge bg-brand text-white">Canvasser</span><h2 class="font-display font-bold">You're an approved canvasser</h2></div>
                <p class="mt-2 text-sm text-neutral-600">Door-to-door tools, your invite leaderboard and ward targets are coming soon. Your coordinator will be in touch on WhatsApp.</p>
                @break
            @case('pending')
                <div class="flex items-center gap-3"><span class="badge bg-accent/20 text-[#7a5b00]">Pending</span><h2 class="font-display font-bold">Canvasser application received</h2></div>
                <p class="mt-2 text-sm text-neutral-600">Your state coordinator is reviewing it.</p>
                @break
            @default
                <div x-data="{ open: {{ $errors->has('canvasser_roles') || $errors->has('whatsapp_phone') ? 'true' : 'false' }} }">
                    <h2 class="font-display font-bold">Want to do more? Become a canvasser</h2>
                    @if ($profile->canvasser_status?->value === 'rejected')
                        <p class="mt-1 text-sm text-neutral-600">Your last application was not approved{{ $profile->canvasser_review_note ? ': “'.$profile->canvasser_review_note.'”' : '.' }} You can apply again.</p>
                    @else
                        <p class="mt-1 text-sm text-neutral-600">Work with your ward team. Your state coordinator reviews every application.</p>
                    @endif
                    <button type="button" class="btn-secondary btn-sm mt-3" x-show="!open" x-on:click="open = true">Apply to canvass</button>
                    <form method="POST" action="{{ route('membership.canvasser.apply') }}" class="mt-4 space-y-4" x-show="open" x-cloak>
                        @csrf
                        <fieldset>
                            <legend class="label">How would you like to help?</legend>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($canvasserRoles as $role)
                                    <label class="chip group"><input type="checkbox" name="canvasser_roles[]" value="{{ $role->value }}" class="sr-only" @checked(in_array($role->value, old('canvasser_roles', [])))> <x-icon name="check" class="hidden h-4 w-4 group-has-[:checked]:block" /> {{ $role->label() }}</label>
                                @endforeach
                            </div>
                            @error('canvasser_roles')<p class="error">{{ $message }}</p>@enderror
                        </fieldset>
                        <div class="max-w-sm">
                            <label class="label" for="whatsapp_phone">WhatsApp number</label>
                            <input id="whatsapp_phone" name="whatsapp_phone" type="tel" class="field" value="{{ old('whatsapp_phone') }}" placeholder="Leave blank to use {{ \App\Support\NigerianPhone::format($user->phone) }}">
                            @error('whatsapp_phone')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <button class="btn-primary">Send application</button>
                    </form>
                </div>
        @endswitch
    </section>

    {{-- Updates --}}
    <section class="mt-6">
        <div class="flex items-baseline justify-between">
            <h2 class="font-display text-lg font-bold">Latest updates</h2>
            <a href="{{ route('membership.events') }}" class="text-sm font-semibold text-brand">See all</a>
        </div>
        <div class="mt-3 space-y-3">
            @forelse ($news as $post)
                @include('partials.news-item', ['post' => $post])
            @empty
                <p class="panel p-5 text-sm text-neutral-600">No updates yet.</p>
            @endforelse
        </div>
    </section>
</x-layouts.app>
