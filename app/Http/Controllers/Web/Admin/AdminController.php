<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\NigerianPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Super admins add and deactivate other super admins. */
class AdminController extends Controller
{
    public function index(): View
    {
        return view('admin.admins', [
            'admins' => User::where('role', Role::SuperAdmin->value)->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        if (filled($request->input('phone'))) {
            $request->merge(['phone' => NigerianPhone::normalise($request->input('phone')) ?? $request->input('phone')]);
        }
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'regex:/^\+234[789][01]\d{8}$/', Rule::unique('users', 'phone')],
            'password' => ['required', 'confirmed', Password::min(10)],
        ], ['phone.regex' => 'Enter a Nigerian mobile number.']);

        $admin = new User(collect($data)->only(['name', 'email', 'phone', 'password'])->all());
        $admin->role = Role::SuperAdmin;
        $admin->is_active = true;
        $admin->save();

        $audit->record($request, AuditLogger::ADMIN_CREATED, $admin, ['email' => $admin->email]);

        return back()->with('status', "{$admin->name} can now sign in as a super admin.");
    }

    public function update(Request $request, User $admin, AuditLogger $audit): RedirectResponse
    {
        abort_unless($admin->isSuperAdmin(), 404);

        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $active = (bool) $data['is_active'];

        // Blocking self-deactivation also means the last active super admin can never be switched off.
        if (! $active && $admin->is($request->user())) {
            return back()->withErrors(['admin' => 'You cannot deactivate your own account.']);
        }

        $admin->is_active = $active;
        $admin->save();
        if (! $active) {
            $admin->tokens()->delete();
        }

        $audit->record($request, $active ? AuditLogger::ADMIN_REACTIVATED : AuditLogger::ADMIN_DEACTIVATED, $admin);

        return back()->with('status', $active ? "{$admin->name} is a super admin again." : "{$admin->name} can no longer sign in.");
    }
}
