<?php

namespace App\Http\Controllers\Api;

use App\Enums\AreaLevel;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /** Users the requester may see (for a coordinator: the agents in their area). */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role' => ['nullable', Rule::enum(Role::class)],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'between:1,200'],
        ]);

        $users = User::query()
            ->visibleTo($request->user())
            ->when($data['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->when($data['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('name', 'ilike', '%'.addcslashes($search, '%_\\').'%')
                ->orWhere('email', 'ilike', '%'.addcslashes($search, '%_\\').'%')))
            ->orderBy('name')
            ->paginate($data['per_page'] ?? 50);

        return response()->json([
            'data' => collect($users->items())->map(fn (User $user) => AuthController::present($user))->all(),
            'meta' => ['current_page' => $users->currentPage(), 'last_page' => $users->lastPage(), 'per_page' => $users->perPage(), 'total' => $users->total()],
        ]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->canSee($user), 403);

        return response()->json(['user' => AuthController::present($user)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules() + [
            'password' => ['required', 'string', Password::min(10)],
        ]);

        $user = new User([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
        ]);
        $user->role = Role::from($data['role']);
        $this->applyArea($user, $data);
        $user->save();

        return response()->json(['user' => AuthController::present($user)], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate($this->rules($user, partial: true) + [
            'password' => ['sometimes', 'string', Password::min(10)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user->fill(collect($data)->only(['name', 'phone', 'password'])->all());

        if (isset($data['email'])) {
            $user->email = strtolower($data['email']);
        }

        if (isset($data['role'])) {
            $user->role = Role::from($data['role']);
        }

        if (array_key_exists('area_level', $data)) {
            $this->applyArea($user, $data);
        }

        if (isset($data['is_active'])) {
            abort_if(! $data['is_active'] && $user->is($request->user()), 422, 'You cannot deactivate your own account.');
            $user->is_active = $data['is_active'];
        }

        abort_if($user->role === Role::Coordinator && $user->areaLevel() === null, 422, 'A coordinator must be scoped to a state, LGA or ward.');

        $user->save();

        if (! $user->is_active || isset($data['password'])) {
            // Sign the account out everywhere.
            $user->tokens()->delete();
        }

        if (! $user->is_active) {
            $user->shifts()->whereNull('ended_at')->update(['ended_at' => now()]);
        }

        return response()->json(['user' => AuthController::present($user)]);
    }

    /** @return array<string, mixed> */
    private function rules(?User $user = null, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255'],
            'email' => [$required, 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'role' => [$required, Rule::enum(Role::class)],
            'area_level' => [
                Rule::requiredIf(fn () => ! $partial && request('role') === Role::Coordinator->value),
                'nullable',
                Rule::enum(AreaLevel::class),
            ],
            'area_id' => ['required_with:area_level', 'nullable', 'integer'],
        ];
    }

    /** @param array<string, mixed> $data */
    private function applyArea(User $user, array $data): void
    {
        $level = isset($data['area_level']) ? AreaLevel::from($data['area_level']) : null;

        if ($level !== null) {
            $table = ['state' => 'states', 'lga' => 'lgas', 'ward' => 'wards'][$level->value];
            validator($data, ['area_id' => [Rule::exists($table, 'id')]])->validate();
        }

        $user->assignArea($level, $level ? (int) $data['area_id'] : null);
    }
}
