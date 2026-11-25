<?php

namespace App\Http\Controllers\Web\Staff;

use App\Enums\AreaLevel;
use App\Enums\CanvasserRole;
use App\Enums\CanvasserStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Lga;
use App\Models\MemberProfile;
use App\Models\State;
use App\Models\User;
use App\Models\Ward;
use App\Services\Audit\AuditLogger;
use App\Services\Membership\TemporaryCredentials;
use App\Support\NigerianPhone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A state's field team below the state coordinator: LGA coordinators, ward coordinators and polling unit agents.
 * State coordinators manage their own state; super admins any state. Each new account gets a temporary
 * password to share, and the person chooses their own on first sign-in.
 */
class TeamController extends Controller
{
    public const TYPES = [
        'lga_coordinator' => 'LGA coordinator',
        'ward_coordinator' => 'Ward coordinator',
        'polling_unit_agent' => 'Polling unit agent',
    ];

    public const ADDED = 'team.added';

    public const DEACTIVATED = 'team.deactivated';

    public const REACTIVATED = 'team.reactivated';

    public const PASSWORD_RESET = 'team.password_reset';

    public function index(Request $request, TemporaryCredentials $credentials): View
    {
        $viewer = $this->manager($request);
        $stateId = $viewer->isSuperAdmin() ? ($request->integer('state_id') ?: null) : $viewer->state_id;
        $type = array_key_exists((string) $request->query('type'), self::TYPES) ? (string) $request->query('type') : null;

        $team = $this->teamQuery($stateId)
            ->when($type, fn (Builder $q, string $t) => $this->ofType($q, $t))
            ->with(['state:id,name', 'lga:id,name', 'ward:id,name', 'memberProfile.pollingUnit:id,code,name'])
            ->orderByDesc('users.created_at')
            ->paginate(50)->withQueryString();

        return view('staff.team', [
            'team' => $team,
            'types' => self::TYPES,
            'type' => $type,
            'stateId' => $stateId,
            'states' => $viewer->isSuperAdmin() && ! site_state_code() ? State::orderBy('name')->get(['id', 'name']) : collect(),
            'lgas' => $stateId ? Lga::where('state_id', $stateId)->orderBy('name')->get(['id', 'name']) : collect(),
            'canSms' => $credentials->canSms(),
        ]);
    }

    public function store(Request $request, TemporaryCredentials $credentials, AuditLogger $audit): RedirectResponse
    {
        $viewer = $this->manager($request);
        $stateId = $viewer->isSuperAdmin() ? $request->integer('state_id') : (int) $viewer->state_id;

        $request->merge([
            'phone' => NigerianPhone::normalise((string) $request->input('phone')) ?? $request->input('phone'),
            'email' => filled($request->input('email')) ? mb_strtolower(trim((string) $request->input('email'))) : null,
            'polling_unit_id' => in_array($request->input('polling_unit_id'), ['', 'other', null], true) ? null : $request->input('polling_unit_id'),
            'polling_unit_name' => trim((string) preg_replace('/\s+/', ' ', (string) $request->input('polling_unit_name'))) ?: null,
        ]);
        $type = (string) $request->input('type');
        $needsWard = in_array($type, ['ward_coordinator', 'polling_unit_agent'], true);

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'phone' => ['required', 'regex:/^\+234[789][01]\d{8}$/', Rule::unique('users', 'phone')],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'state_id' => [Rule::requiredIf($viewer->isSuperAdmin()), 'nullable', 'integer', Rule::exists('states', 'id')],
            'lga_id' => ['required', 'integer', Rule::exists('lgas', 'id')->where('state_id', $stateId)],
            'ward_id' => [Rule::requiredIf($needsWard), 'nullable', 'integer', Rule::exists('wards', 'id')->where('lga_id', $request->integer('lga_id'))],
            'polling_unit_id' => ['nullable', 'integer', Rule::exists('polling_units', 'id')->where('ward_id', $request->integer('ward_id'))],
            'polling_unit_name' => [Rule::requiredIf($type === 'polling_unit_agent' && ! $request->filled('polling_unit_id')), 'nullable', 'string', 'max:120'],
            'send_sms' => ['nullable', 'boolean'],
        ], [
            'phone.regex' => 'Enter a Nigerian mobile number.',
            'phone.unique' => 'This phone number already has an account. Each person can only have one.',
            'lga_id.exists' => 'Choose an LGA in this state.',
            'ward_id.required' => 'Choose the ward.',
            'polling_unit_name.required' => 'Choose the polling unit, or type it if it is not listed.',
        ]);

        $person = DB::transaction(function () use ($data, $viewer) {
            $person = new User(['name' => preg_replace('/\s+/', ' ', trim($data['name'])), 'phone' => $data['phone'], 'email' => $data['email']]);
            $person->password = Str::random(64);
            $person->is_active = true;
            $person->added_by_user_id = $viewer->id;

            if ($data['type'] === 'polling_unit_agent') {
                // A canvasser account tied to a polling unit: they get the field page, including registering people.
                $person->role = Role::Member;
                $person->assignArea(AreaLevel::Ward, (int) $data['ward_id']);
                $person->save();

                $number = MemberProfile::newMemberNumber(State::findOrFail($person->state_id));
                $profile = new MemberProfile;
                $profile->user_id = $person->id;
                $profile->member_number = $number;
                $profile->referral_code = $number;
                $profile->verify_token = MemberProfile::newVerifyToken();
                $profile->has_pvc = false;
                $profile->polling_unit_id = $data['polling_unit_id'] ?? null;
                $profile->polling_unit_name = $profile->polling_unit_id ? null : ($data['polling_unit_name'] ?? null);
                $profile->canvasser_status = CanvasserStatus::Approved;
                $profile->canvasser_roles = [CanvasserRole::PollingUnitAgent->value];
                $profile->whatsapp_phone = $data['phone'];
                $profile->canvasser_applied_at = now();
                $profile->canvasser_reviewed_by = $viewer->id;
                $profile->canvasser_reviewed_at = now();
                // Consent and the rest of their profile are theirs to give on first sign-in (membership/setup).
                $profile->save();
            } else {
                $person->role = Role::Coordinator;
                $data['type'] === 'lga_coordinator'
                    ? $person->assignArea(AreaLevel::Lga, (int) $data['lga_id'])
                    : $person->assignArea(AreaLevel::Ward, (int) $data['ward_id']);
                $person->save();
            }

            return $person;
        });

        $audit->record($request, self::ADDED, $person, ['type' => $data['type']]);

        return $this->withCredentials($person, $data['type'], (bool) ($data['send_sms'] ?? false), $credentials, 'added');
    }

    public function update(Request $request, User $person, AuditLogger $audit): RedirectResponse
    {
        $this->managed($request, $person);
        abort_unless($person->isCoordinator() || $person->added_by_user_id !== null, 403, 'This member joined on their own; manage them from their canvasser application.');

        $active = $request->validate(['is_active' => ['required', 'boolean']])['is_active'];
        $person->is_active = (bool) $active;
        $person->save();
        if (! $person->is_active) {
            $person->tokens()->delete();
        }

        $audit->record($request, $person->is_active ? self::REACTIVATED : self::DEACTIVATED, $person);

        return back()->with('status', $person->is_active ? "{$person->name} can sign in again." : "{$person->name} can no longer sign in.");
    }

    public function resetPassword(Request $request, User $person, TemporaryCredentials $credentials, AuditLogger $audit): RedirectResponse
    {
        $this->managed($request, $person);
        abort_unless($person->isCoordinator() || $person->added_by_user_id !== null, 403, 'Members who joined on their own reset their password themselves.');

        $audit->record($request, self::PASSWORD_RESET, $person);

        return $this->withCredentials($person, $this->typeOf($person), $request->boolean('send_sms'), $credentials, 'reset');
    }

    /** Super admins and state-level coordinators. */
    public static function canManage(User $user): bool
    {
        return $user->isSuperAdmin() || ($user->isCoordinator() && $user->state_id !== null && $user->lga_id === null);
    }

    public static function typeOf(User $person): string
    {
        return match (true) {
            $person->isMember() => 'polling_unit_agent',
            $person->ward_id !== null => 'ward_coordinator',
            default => 'lga_coordinator',
        };
    }

    private function withCredentials(User $person, string $type, bool $sendSms, TemporaryCredentials $credentials, string $why): RedirectResponse
    {
        $password = $credentials->issue($person);
        $place = $person->ward_id ? Ward::find($person->ward_id)?->name.' ward' : Lga::find($person->lga_id)?->name.' LGA';
        $roleLabel = self::TYPES[$type].' for '.$place;
        $message = $credentials->message($person, $password, $roleLabel);
        $smsError = $sendSms && $credentials->canSms() ? $credentials->sendSms($person, $password) : null;

        return back()->with('status', $why === 'added' ? "{$person->name} was added as {$roleLabel}." : "{$person->name} has a new temporary password.")
            ->with('credentials', [
                'name' => $person->name,
                'phone' => NigerianPhone::format($person->phone),
                'password' => $password,
                'message' => $message,
                'whatsapp' => $credentials->whatsappUrl($person, $message),
                'sms' => $sendSms && $credentials->canSms() ? ($smsError ?? 'Sent by SMS.') : null,
            ]);
    }

    private function manager(Request $request): User
    {
        $viewer = $request->user();
        abort_unless(self::canManage($viewer), 403, 'Only state coordinators and admins manage the team.');

        return $viewer;
    }

    /** The person must be on the viewer's team (in their state, for a coordinator). */
    private function managed(Request $request, User $person): void
    {
        $viewer = $this->manager($request);
        $stateId = $viewer->isSuperAdmin() ? null : $viewer->state_id;
        abort_unless($this->teamQuery($stateId)->whereKey($person->id)->exists(), 404);
    }

    private function teamQuery(?int $stateId): Builder
    {
        return User::query()
            ->when($stateId, fn (Builder $q, int $id) => $q->where('users.state_id', $id))
            ->where(fn (Builder $q) => $q->where(fn (Builder $c) => $this->ofType($c, 'lga_coordinator'))
                ->orWhere(fn (Builder $c) => $this->ofType($c, 'ward_coordinator'))
                ->orWhere(fn (Builder $c) => $this->ofType($c, 'polling_unit_agent')));
    }

    private function ofType(Builder $query, string $type): Builder
    {
        return match ($type) {
            'lga_coordinator' => $query->where('users.role', Role::Coordinator->value)->whereNotNull('users.lga_id')->whereNull('users.ward_id'),
            'ward_coordinator' => $query->where('users.role', Role::Coordinator->value)->whereNotNull('users.ward_id'),
            default => $query->where('users.role', Role::Member->value)->whereHas('memberProfile', fn (Builder $p) => $p
                ->where('canvasser_status', CanvasserStatus::Approved->value)
                ->whereJsonContains('canvasser_roles', CanvasserRole::PollingUnitAgent->value)),
        };
    }
}
