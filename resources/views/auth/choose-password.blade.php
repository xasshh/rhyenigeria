<x-layouts.app title="Choose your password" heading="Choose your own password">
    <section class="panel mt-5 max-w-lg p-5 sm:p-6">
        <p class="text-neutral-600">You signed in with a temporary password. Choose your own now; nobody else will know it.</p>
        <form method="POST" action="{{ route('account.password.update') }}" class="mt-5 space-y-4">
            @csrf @method('PUT')
            <div>
                <label class="label" for="password">New password</label>
                <input id="password" name="password" type="password" class="field" autocomplete="new-password" required placeholder="At least 10 characters">
                @error('password')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="password_confirmation">Type it again</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="field" autocomplete="new-password" required>
            </div>
            <button class="btn-primary w-full">Save my password</button>
        </form>
    </section>
</x-layouts.app>
