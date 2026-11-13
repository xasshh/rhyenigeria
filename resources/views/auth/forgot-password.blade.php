@php
    // After a link is sent, the same page shows the "Check your email" step.
    $sentTo = session('reset_sent_to');
    $masked = $sentTo ? preg_replace_callback('/^(.)(.*)(@.*)$/u', fn ($m) => $m[1].'•••••'.$m[3], $sentTo) : null;
@endphp
<x-layouts.split :title="$sentTo ? 'Check your email' : 'Forgot password'">
    <x-slot:top>
        @if ($sentTo)
            <a href="{{ route('login') }}" class="font-semibold text-action hover:underline">Back to sign in</a>
        @else
            Remembered it? <a href="{{ route('login') }}" class="font-semibold text-action hover:underline">Sign in</a>
        @endif
    </x-slot:top>

    <x-slot:aside>
        @if ($sentTo)
            <h1 class="font-headline text-[34px] leading-[1.02] font-extrabold tracking-[-0.025em] sm:text-[42px] lg:text-[54px]">Almost there.</h1>
            <p class="mt-4 hidden max-w-[380px] text-[19px] leading-relaxed text-white/80 sm:block">One tap from your inbox and you're back in.</p>
        @else
            <h1 class="font-headline text-[34px] leading-[1.02] font-extrabold tracking-[-0.025em] sm:text-[42px] lg:text-[54px]">Locked out? <br>It happens.</h1>
            <p class="mt-4 hidden max-w-[380px] text-[19px] leading-relaxed text-white/80 sm:block">We'll get you back to your card and your ward in under a minute.</p>
        @endif
    </x-slot:aside>

    <x-slot:asideFooter>
        <div class="flex gap-3 rounded-2xl border border-white/15 bg-white/[0.06] p-6">
            <x-icon name="help" class="text-mint" />
            <p class="text-[16px] leading-relaxed text-white/80"><strong class="block font-semibold text-white">Changed your email?</strong> Ask your ward or state coordinator to update it, then reset your password here.</p>
        </div>
    </x-slot:asideFooter>

    <div class="lg:pl-[50px]">
        @if ($sentTo)
            <span class="flex h-[72px] w-[72px] items-center justify-center rounded-2xl bg-[#e6f2e8] text-action"><x-icon name="mail" class="h-7 w-7" /></span>
            <h2 class="mt-8 font-headline text-[38px] leading-none font-extrabold tracking-[-0.03em] sm:text-[46px]">Check your email</h2>
            <p class="mt-4 text-lg leading-relaxed text-neutral-600">We sent a reset link to <strong class="font-semibold text-ink">{{ $masked }}</strong> if it is registered with us. Open it on this phone to choose a new password.</p>

            <ol class="mt-8 space-y-4 rounded-2xl bg-surface p-6 text-[16px]">
                @foreach (['Open the email from <strong>Renewed Hope Youth Engagement</strong>', 'Tap <strong>Reset password</strong> — the link works once', 'Not there? Check <strong>Spam</strong> or <strong>Promotions</strong>'] as $step)
                    <li class="flex items-start gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white text-sm font-bold text-action ring-1 ring-line">{{ $loop->iteration }}</span>
                        <span class="pt-0.5">{!! $step !!}</span>
                    </li>
                @endforeach
            </ol>

            <a href="{{ route('login') }}" class="btn-action mt-8 w-full text-[17px]">I've got the link <x-icon name="arrow-right" class="h-4 w-4" /></a>

            <form method="POST" action="{{ route('password.email') }}" class="mt-4" x-data="{ wait: 45, init() { const t = setInterval(() => { if (--this.wait <= 0) clearInterval(t) }, 1000) } }">
                @csrf
                <input type="hidden" name="email" value="{{ $sentTo }}">
                <button class="btn-ghost w-full text-[17px]" :disabled="wait > 0">
                    <span x-show="wait > 0">Resend link in <span x-text="'0:' + String(wait).padStart(2, '0')">0:45</span></span>
                    <span x-show="wait <= 0" x-cloak>Resend link</span>
                </button>
            </form>

            <a href="{{ route('password.request') }}" class="mt-7 inline-flex items-center gap-2 font-semibold text-action hover:underline"><x-icon name="arrow-left" class="h-4 w-4" /> Use a different email</a>
        @else
            <span class="flex h-[72px] w-[72px] items-center justify-center rounded-2xl bg-[#e6f2e8] text-action"><x-icon name="key" class="h-7 w-7" /></span>
            <h2 class="mt-8 font-headline text-[38px] leading-none font-extrabold tracking-[-0.03em] sm:text-[46px]">Forgot your password?</h2>
            <p class="mt-4 text-lg leading-relaxed text-neutral-600">Enter the email you signed up with and we'll send you a link to create a new one.</p>

            <x-flash class="mt-6" quiet />

            <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-6">
                @csrf
                <div>
                    <label class="label text-base font-semibold" for="email">Email address</label>
                    <input id="email" name="email" type="email" class="field-lg" value="{{ old('email') }}" placeholder="you@example.com" autocomplete="email" inputmode="email" required autofocus>
                    @error('email')<p class="error">{{ $message }}</p>@enderror
                </div>
                <button class="btn-action w-full text-[17px]">Send reset link <x-icon name="arrow-right" class="h-4 w-4" /></button>
            </form>

            <a href="{{ route('login') }}" class="mt-8 inline-flex items-center gap-2 font-semibold text-action hover:underline"><x-icon name="arrow-left" class="h-4 w-4" /> Back to sign in</a>
        @endif
    </div>
</x-layouts.split>
