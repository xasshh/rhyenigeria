<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Staff replace the temporary password they were given. Members do this on membership/setup instead. */
class AccountPasswordController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        if ($request->user()->isMember()) {
            return redirect()->route('membership.setup');
        }

        return view('auth.choose-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->isMember(), 404);

        $data = $request->validate(['password' => ['required', 'confirmed', Password::min(10)]]);

        $user->forceFill(['password' => Hash::make($data['password']), 'must_change_password' => false])->save();

        return redirect($user->homeRoute())->with('status', 'Your new password is set.');
    }
}
