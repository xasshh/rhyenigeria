<x-layouts.auth title="Forgot password" width="max-w-md">
    <h1 class="font-display text-[28px] font-bold tracking-tight">Forgot your password?</h1>
    <p class="mt-1.5 text-neutral-600">Enter the email you joined with and we'll send you a link to choose a new one.</p>

    <x-flash class="mt-5" quiet />

    <form method="POST" action="{{ route('password.email') }}" class="panel mt-5 space-y-5 p-5 sm:p-6">
        @csrf
        <div>
            <label class="label" for="email">Email address</label>
            <input id="email" name="email" type="email" class="field" value="{{ old('email') }}" autocomplete="email" required autofocus>
            @error('email')<p class="error">{{ $message }}</p>@enderror
        </div>
        <button class="btn-primary w-full">Send reset link</button>
    </form>
    <p class="mt-6 text-center text-sm"><a href="{{ route('login') }}" class="font-semibold text-brand">Back to sign in</a></p>
</x-layouts.auth>
