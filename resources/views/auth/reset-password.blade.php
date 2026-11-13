<x-layouts.split title="Create a new password" noindex>
    <x-slot:top><a href="{{ route('login') }}" class="font-semibold text-action hover:underline">Back to sign in</a></x-slot:top>

    <x-slot:aside>
        <h1 class="font-headline text-[34px] leading-[1.02] font-extrabold tracking-[-0.025em] sm:text-[42px] lg:text-[54px]">Fresh start.</h1>
        <p class="mt-4 hidden max-w-[380px] text-[19px] leading-relaxed text-white/80 sm:block">Once saved, you'll be signed out everywhere else for your safety.</p>
    </x-slot:aside>

    <x-slot:asideFooter>
        <div class="flex gap-3 rounded-2xl border border-white/15 bg-white/[0.06] p-6">
            <x-icon name="help" class="text-mint" />
            <p class="text-[16px] leading-relaxed text-white/80"><strong class="block font-semibold text-white">Changed your email?</strong> Ask your ward or state coordinator to update it, then reset your password here.</p>
        </div>
    </x-slot:asideFooter>

    <div class="lg:pl-[50px]" x-data="{
        password: '', confirm: '', show: false, email: @js(old('email', $email)),
        get checks() {
            const p = this.password, local = (this.email || '').split('@')[0].toLowerCase();
            return [
                ['At least 8 characters', p.length >= 8],
                ['Has a number', /\d/.test(p)],
                ['Has a capital letter', /[A-Z]/.test(p)],
                ['Not your email', p.length > 0 && !(local && p.toLowerCase().includes(local))],
            ];
        },
        get score() { return this.checks.filter(c => c[1]).length },
        get strength() { return ['', 'Weak', 'Fair', 'Good', 'Strong'][this.score] },
    }">
        <h2 class="font-headline text-[38px] leading-none font-extrabold tracking-[-0.03em] sm:text-[46px]">Create a new password</h2>
        <p class="mt-4 text-lg text-neutral-600">Pick something you haven't used here before.</p>

        <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-6">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            @if ($email)
                <input type="hidden" name="email" value="{{ $email }}">
                @error('email')<p class="error">{{ $message }}</p>@enderror
            @else
                <div>
                    <label class="label text-base font-semibold" for="email">Email address</label>
                    <input id="email" name="email" type="email" class="field-lg" x-model="email" autocomplete="email" required>
                    @error('email')<p class="error">{{ $message }}</p>@enderror
                </div>
            @endif
            <div>
                <label class="label text-base font-semibold" for="password">New password</label>
                <div class="relative">
                    <input id="password" name="password" :type="show ? 'text' : 'password'" type="password" class="field-lg pr-14" autocomplete="new-password" x-model="password" required aria-describedby="password-checks">
                    <button type="button" class="absolute inset-y-0 right-0 flex w-14 items-center justify-center text-neutral-500 hover:text-ink" x-on:click="show = !show" aria-label="Show password"><x-icon name="eye" /></button>
                </div>
                <div class="mt-3 flex items-center gap-3" aria-hidden="true">
                    <div class="grid flex-1 grid-cols-4 gap-1.5">
                        <template x-for="i in 4"><span class="h-1.5 rounded-full transition-colors" :class="i <= score ? (score >= 3 ? 'bg-action' : 'bg-[#e0a526]') : 'bg-line'"></span></template>
                    </div>
                    <span class="w-14 text-right text-sm font-semibold text-action-dark" x-text="strength"></span>
                </div>
                <ul id="password-checks" class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-[15px]">
                    <template x-for="[label, ok] in checks">
                        <li class="flex items-center gap-2" :class="ok ? 'text-action-dark' : 'text-neutral-500'">
                            <span class="flex h-4 w-4 items-center justify-center rounded-full" :class="ok ? 'bg-action text-white' : 'ring-1 ring-neutral-300'"><svg x-show="ok" class="h-2.5 w-2.5" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 6.5 4.6 9 10 3.5"/></svg></span>
                            <span x-text="label"></span>
                        </li>
                    </template>
                </ul>
                @error('password')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label text-base font-semibold" for="password_confirmation">Type it again</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="field-lg" autocomplete="new-password" x-model="confirm" required>
                <p class="error" x-show="confirm && confirm !== password" x-cloak>The two passwords don't match yet.</p>
            </div>
            <button class="btn-action w-full text-[17px]">Save and sign in <x-icon name="arrow-right" class="h-4 w-4" /></button>
        </form>
    </div>
</x-layouts.split>
