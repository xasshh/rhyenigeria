<x-layouts.split title="Finish setting up" noindex>
    <x-slot:top>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="font-semibold text-action hover:underline">Sign out</button></form>
    </x-slot:top>

    <x-slot:aside>
        <h1 class="font-headline text-[34px] leading-[1.02] font-extrabold tracking-[-0.025em] sm:text-[42px] lg:text-[54px]">
            Welcome to <br class="hidden lg:block">the <span class="text-mint">movement.</span>
        </h1>
        <p class="mt-4 hidden max-w-[380px] text-[19px] leading-relaxed text-white/80 sm:block">
            @if ($profile->import_source)
                {{ $profile->import_source }} added you to {{ config('site.short') }}{{ $user->lga ? ' in '.$user->lga->name : '' }}. One minute and your membership card is ready.
            @else
                One minute and your membership card is ready.
            @endif
        </p>
    </x-slot:aside>

    <div class="lg:pl-[50px]" x-data="{ show: false }">
        <h2 class="font-headline text-[38px] leading-none font-extrabold tracking-[-0.03em] sm:text-[46px]">Finish setting up</h2>
        <p class="mt-3 text-lg text-neutral-600">Check your name, tell us a little about you and choose your own password.</p>

        <form method="POST" action="{{ route('membership.setup.complete') }}" class="mt-8 space-y-6">
            @csrf
            @method('PUT')

            <div>
                <label class="label text-base font-semibold" for="name">Full name</label>
                <input id="name" name="name" type="text" class="field-lg" value="{{ old('name', $user->name) }}" autocomplete="name" required @error('name') aria-invalid="true" @enderror>
                @error('name')<p class="error">{{ $message }}</p>@enderror
            </div>

            <fieldset>
                <legend class="label text-base font-semibold">Gender</legend>
                <div class="flex flex-wrap gap-3">
                    @foreach ($genders as $gender)
                        <label class="pill"><input type="radio" class="sr-only" name="gender" value="{{ $gender->value }}" @checked(old('gender', $profile->gender?->value) === $gender->value) required><span class="pill-dot"></span> {{ $gender->label() }}</label>
                    @endforeach
                </div>
                @error('gender')<p class="error">{{ $message }}</p>@enderror
            </fieldset>

            <fieldset>
                <legend class="label text-base font-semibold">Age range</legend>
                <div class="flex flex-wrap gap-3">
                    @foreach ($ageRanges as $range)
                        <label class="pill"><input type="radio" class="sr-only" name="age_range" value="{{ $range->value }}" @checked(old('age_range', $profile->age_range?->value) === $range->value) required><span class="pill-dot"></span> {{ $range->label() }}</label>
                    @endforeach
                </div>
                @error('age_range')<p class="error">{{ $message }}</p>@enderror
            </fieldset>

            @if ($user->must_change_password)
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label text-base font-semibold" for="password">New password</label>
                        <input id="password" name="password" :type="show ? 'text' : 'password'" type="password" class="field-lg" autocomplete="new-password" minlength="8" required @error('password') aria-invalid="true" @enderror>
                        @error('password')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label text-base font-semibold" for="password_confirmation">Type it again</label>
                        <input id="password_confirmation" name="password_confirmation" :type="show ? 'text' : 'password'" type="password" class="field-lg" autocomplete="new-password" minlength="8" required>
                    </div>
                </div>
                <label class="-mt-2 flex items-center gap-3 text-[15px] text-neutral-700"><input type="checkbox" class="h-5 w-5 rounded accent-action" x-model="show"> Show password</label>
                <p class="hint -mt-3 text-sm">At least 8 characters. You'll sign in with your phone number {{ \App\Support\NigerianPhone::format($user->phone) }} and this password.</p>
            @endif

            <label class="flex cursor-pointer items-start gap-3.5 rounded-2xl bg-[#f1f4f1] p-5">
                <input type="checkbox" name="consent" value="1" class="mt-1 h-5 w-5 shrink-0 rounded accent-action" @checked(old('consent')) required>
                <span class="text-[16px] leading-relaxed text-neutral-700">
                    I agree that {{ config('site.name') }} may store my details to coordinate the movement under the Nigeria Data Protection Act 2023. My data will never be sold and I can ask for it to be deleted at any time.
                    <a href="{{ route('privacy') }}" target="_blank" class="font-semibold text-action underline-offset-2 hover:underline">Read the privacy notice</a>
                </span>
            </label>
            @error('consent')<p class="error -mt-3">{{ $message }}</p>@enderror

            <button class="btn-action w-full text-[17px]">Save and open my card <x-icon name="arrow-right" class="h-4 w-4" /></button>
        </form>
    </div>
</x-layouts.split>
