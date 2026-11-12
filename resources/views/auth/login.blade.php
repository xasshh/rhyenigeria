<x-layouts.auth title="Sign in" width="max-w-md">
    <h1 class="font-display text-[28px] font-bold tracking-tight">Sign in</h1>
    <p class="mt-1.5 text-neutral-600">Welcome back to the movement.</p>

    <x-flash class="mt-5" quiet />

    <form method="POST" action="{{ route('login') }}" class="panel mt-5 space-y-5 p-5 sm:p-6">
        @csrf
        <div>
            <label class="label" for="email">Email address</label>
            <input id="email" name="email" type="email" class="field" value="{{ old('email') }}" autocomplete="email" inputmode="email" required autofocus @error('email') aria-invalid="true" @enderror>
            @error('email')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <div class="flex items-baseline justify-between">
                <label class="label" for="password">Password</label>
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-brand">Forgot password?</a>
            </div>
            <input id="password" name="password" type="password" class="field" autocomplete="current-password" required>
            @error('password')<p class="error">{{ $message }}</p>@enderror
        </div>
        <label class="flex items-center gap-2 text-sm text-neutral-700"><input type="checkbox" name="remember" value="1" class="h-4 w-4 accent-brand" checked> Keep me signed in on this phone</label>
        <button class="btn-primary w-full">Sign in</button>
    </form>

    <p class="mt-6 text-center text-sm text-neutral-600">Not a member yet? <a href="{{ route('join') }}" class="font-semibold text-brand">Join the movement</a></p>
</x-layouts.auth>
