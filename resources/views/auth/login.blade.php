@php
    $toEmpowerment = str_ends_with(rtrim((string) parse_url((string) session('url.intended'), PHP_URL_PATH), '/'), 'empowerment');
@endphp
<x-layouts.split title="Sign in" stats>
    <x-slot:top>Not a member yet? <a href="{{ route('join') }}" class="font-semibold text-action hover:underline">Join</a></x-slot:top>

    <x-slot:aside>
        <h1 class="font-headline text-[34px] leading-[1.02] font-extrabold tracking-[-0.025em] sm:text-[42px] lg:text-[54px]">
            Welcome back <br class="hidden lg:block">to the <span class="text-mint">movement.</span>
        </h1>
        <p class="mt-4 hidden max-w-[380px] text-[19px] leading-relaxed text-white/80 sm:block lg:mt-5">Your card, your ward's events and messages from your coordinator — all in one place.</p>
        <x-card-preview class="mt-12 hidden lg:block" name="Adaeze Okafor" state="Lagos" ward="Ikeja · Ward 04" number="RHYE-LA-004821" />
    </x-slot:aside>

    <div class="lg:pl-[50px]">
        <h2 class="font-headline text-[40px] leading-none font-extrabold tracking-[-0.03em] sm:text-[48px]">Sign in</h2>
        <p class="mt-3 text-lg text-neutral-600">{{ $toEmpowerment ? 'Sign in to open Empowerment on your dashboard.' : 'Use the email you joined with.' }}</p>

        <x-flash class="mt-6" quiet />

        <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-6" x-data="{ show: false }">
            @csrf
            <div>
                <label class="label text-base font-semibold" for="email">Email address</label>
                <input id="email" name="email" type="email" class="field-lg" value="{{ old('email') }}" placeholder="you@example.com" autocomplete="email" inputmode="email" required autofocus @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email')<p class="error" id="email-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <div class="mb-1.5 flex items-baseline justify-between">
                    <label class="text-base font-semibold" for="password">Password</label>
                    <a href="{{ route('password.request') }}" class="text-[15px] font-semibold text-action hover:underline">Forgot password?</a>
                </div>
                <div class="relative">
                    <input id="password" name="password" :type="show ? 'text' : 'password'" type="password" class="field-lg pr-14" autocomplete="current-password" required>
                    <button type="button" class="absolute inset-y-0 right-0 flex w-14 items-center justify-center text-neutral-500 hover:text-ink" x-on:click="show = !show" :aria-label="show ? 'Hide password' : 'Show password'" aria-label="Show password">
                        <x-icon name="eye" />
                    </button>
                </div>
                @error('password')<p class="error">{{ $message }}</p>@enderror
            </div>
            <label class="flex items-center gap-3 text-[16px] text-ink"><input type="checkbox" name="remember" value="1" class="h-5 w-5 rounded accent-action" checked> Keep me signed in on this phone</label>
            <button class="btn-action w-full text-[17px]">Sign in <x-icon name="arrow-right" class="h-4 w-4" /></button>
        </form>

        <div class="mt-8 flex items-center gap-4 text-[15px] text-neutral-600">
            <span class="h-px flex-1 bg-line"></span> Not a member yet? <span class="h-px flex-1 bg-line"></span>
        </div>
        <a href="{{ route('join') }}" class="btn-ghost mt-6 w-full text-[17px]">Join the movement</a>
    </div>
</x-layouts.split>
