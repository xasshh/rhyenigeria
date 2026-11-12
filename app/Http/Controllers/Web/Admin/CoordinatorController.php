<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\AreaLevel;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\State;
use App\Models\User;
use App\Support\NigerianPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class CoordinatorController extends Controller
{
    public function index(): View
    {
        return view('admin.coordinators', [
            'coordinators' => User::where('role', Role::Coordinator->value)->with('state:id,name', 'lga:id,name', 'ward:id,name')->orderBy('name')->get(),
            'states' => State::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
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
            'state_id' => ['required', 'integer', Rule::exists('states', 'id')],
        ], ['phone.regex' => 'Enter a Nigerian mobile number.']);

        $user = new User(collect($data)->only(['name', 'email', 'phone', 'password'])->all());
        $user->role = Role::Coordinator;
        $user->is_active = true;
        $user->assignArea(AreaLevel::State, (int) $data['state_id']);
        $user->save();

        return back()->with('status', "{$user->name} can now sign in as the coordinator for {$user->state->name}.");
    }

    public function update(Request $request, User $coordinator): RedirectResponse
    {
        abort_unless($coordinator->isCoordinator(), 404);

        $data = $request->validate([
            'state_id' => ['nullable', 'integer', Rule::exists('states', 'id')],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (isset($data['state_id'])) {
            $coordinator->assignArea(AreaLevel::State, (int) $data['state_id']);
        }
        if (isset($data['is_active'])) {
            $coordinator->is_active = (bool) $data['is_active'];
            if (! $coordinator->is_active) {
                $coordinator->tokens()->delete();
            }
        }
        $coordinator->save();

        return back()->with('status', "{$coordinator->name} updated.");
    }
}
