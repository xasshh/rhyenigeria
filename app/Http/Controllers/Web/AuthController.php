<?php

namespace App\Http\Controllers\Web;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Session sign-in for the website. The API keeps using Sanctum tokens. */
class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);
        $credentials['email'] = mb_strtolower(trim($credentials['email']));

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'That email and password do not match our records.']);
        }

        $user = $request->user();

        // Field agents use the mobile app; deactivated accounts cannot sign in.
        if (! $user->is_active || $user->role === Role::FieldAgent) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages(['email' => $user->is_active
                ? 'Field agent accounts sign in through the field app.'
                : 'This account has been deactivated.']);
        }

        $request->session()->regenerate();

        // A member page remembered from before sign-in is no use to staff (it would be a 403).
        $intended = (string) parse_url((string) $request->session()->get('url.intended'), PHP_URL_PATH);
        if (! $user->isMember() && str_starts_with($intended, '/membership')) {
            $request->session()->forget('url.intended');
        }

        return redirect()->intended($user->homeRoute());
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function showForgot(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        $email = mb_strtolower(trim($request->input('email')));

        Password::sendResetLink(['email' => $email]);

        // Same answer whether or not the address is registered.
        return redirect()->route('password.request')
            ->with('reset_sent_to', $email)
            ->with('status', 'If that email is registered, we have sent a link to reset your password. Check your inbox and spam folder.');
    }

    public function showReset(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $resetUser = null;
        $status = Password::reset(
            ['email' => mb_strtolower(trim($request->input('email')))] + $request->only('password', 'password_confirmation', 'token'),
            function ($user, string $password) use (&$resetUser) {
                // A new remember token signs out every other browser; deleting tokens signs out the API.
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                $user->tokens()->delete();
                event(new PasswordReset($user));
                $resetUser = $user;
            },
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        // "Save and sign in": only for accounts that may use the website.
        if ($resetUser !== null && $resetUser->is_active && $resetUser->role !== Role::FieldAgent) {
            Auth::login($resetUser);
            $request->session()->regenerate();

            return redirect($resetUser->homeRoute())->with('status', 'Your new password is saved. You are signed in.');
        }

        return redirect()->route('login')->with('status', 'Your password has been reset. You can sign in now.');
    }
}
