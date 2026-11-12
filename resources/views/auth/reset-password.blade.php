<x-layouts.auth title="Choose a new password" width="max-w-md" noindex>
    <h1 class="font-display text-[28px] font-bold tracking-tight">Choose a new password</h1>

    <form method="POST" action="{{ route('password.update') }}" class="panel mt-5 space-y-5 p-5 sm:p-6">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label class="label" for="email">Email address</label>
            <input id="email" name="email" type="email" class="field" value="{{ old('email', $email) }}" autocomplete="email" required>
            @error('email')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="password">New password</label>
            <input id="password" name="password" type="password" class="field" autocomplete="new-password" placeholder="At least 8 characters" required>
            @error('password')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="password_confirmation">Type it again</label>
            <input id="password_confirmation" name="password_confirmation" type="password" class="field" autocomplete="new-password" required>
        </div>
        <button class="btn-primary w-full">Save new password</button>
    </form>
</x-layouts.auth>
