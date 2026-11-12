<?php

namespace App\Http\Controllers\Web\Staff;

use App\Enums\AgeRange;
use App\Enums\CanvasserRole;
use App\Enums\CanvasserStatus;
use App\Enums\Gender;
use App\Http\Controllers\Controller;
use App\Models\Lga;
use App\Models\State;
use App\Models\User;
use App\Models\Ward;
use App\Services\Audit\AuditLogger;
use App\Services\Membership\MemberDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Member list, detail and CSV export for coordinators (their area) and super admins (everyone). */
class MemberController extends Controller
{
    public const MEMBER_VIEWED = 'member.viewed';

    public const MEMBERS_EXPORTED = 'members.exported';

    public function __construct(private MemberDirectory $directory) {}

    public function index(Request $request): View
    {
        $viewer = $request->user();
        $filters = $this->directory->validateFilters($request);

        $members = $this->directory->filter($this->directory->query($viewer), $filters)
            ->select('users.*')
            ->with(['memberProfile', 'state:id,name', 'lga:id,name', 'ward:id,name'])
            ->orderByDesc('mp.created_at')
            ->paginate(25)
            ->withQueryString();

        return view('staff.members.index', [
            'members' => $members,
            'filters' => $filters,
        ] + $this->filterOptions($viewer, $filters));
    }

    public function show(Request $request, User $member, AuditLogger $audit): View
    {
        // 404 rather than 403, so ids outside the viewer's area reveal nothing.
        abort_unless($request->user()->canSeeMember($member), 404);

        $member->load('memberProfile.pollingUnit', 'memberProfile.referredBy.memberProfile', 'memberProfile.reviewer', 'state', 'lga', 'ward', 'empowermentReports');
        $audit->record($request, self::MEMBER_VIEWED, $member);

        return view('staff.members.show', [
            'member' => $member,
            'profile' => $member->memberProfile,
            'referrals' => $member->memberProfile
                ? User::query()->membersVisibleTo($request->user())
                    ->whereHas('memberProfile', fn ($q) => $q->where('referred_by_user_id', $member->id))->count()
                : 0,
            'canReview' => $this->canReviewCanvassers($request->user()),
        ]);
    }

    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        $viewer = $request->user();
        $filters = $this->directory->validateFilters($request);
        // NIN and VIN only for a super admin who explicitly asks.
        $identifiers = $viewer->isSuperAdmin() && $request->boolean('include_identifiers');

        $query = $this->directory->filter($this->directory->query($viewer), $filters)
            ->select('users.*')
            ->with(['memberProfile.pollingUnit:id,code,name', 'memberProfile.referredBy.memberProfile:id,user_id,member_number', 'state:id,name', 'lga:id,name', 'ward:id,name'])
            ->orderBy('users.id');

        $audit->record($request, self::MEMBERS_EXPORTED, null, [
            'filters' => array_filter($filters, fn ($v) => $v !== null && $v !== ''),
            'rows' => (clone $query)->count(),
            'include_identifiers' => $identifiers,
        ]);

        $filename = 'rhye-members-'.now()->setTimezone(config('membership.timezone'))->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query, $identifiers) {
            $out = fopen('php://output', 'w');
            $header = ['member_number', 'name', 'email', 'phone', 'gender', 'age_range', 'occupation', 'state', 'lga', 'ward',
                'polling_unit_code', 'polling_unit', 'has_pvc', 'vin_added', 'canvasser_status', 'canvasser_roles', 'whatsapp',
                'referred_by', 'joined_at'];
            if ($identifiers) {
                array_push($header, 'vin', 'nin');
            }
            fputcsv($out, $header);

            $query->chunk(1000, function (Collection $users) use ($out, $identifiers) {
                foreach ($users as $user) {
                    $p = $user->memberProfile;
                    $row = [
                        $p->member_number, $user->name, $user->email, $user->phone, $p->gender?->value, $p->age_range?->value,
                        $p->occupation, $user->state?->name, $user->lga?->name, $user->ward?->name,
                        $p->pollingUnit?->code, $p->pollingUnit?->name, $p->has_pvc ? 'yes' : 'no', $p->hasVin() ? 'yes' : 'no',
                        $p->canvasser_status?->value, implode('; ', $p->canvasser_roles ?? []), $p->whatsapp_phone,
                        $p->referredBy?->memberProfile?->member_number, $p->created_at->toIso8601String(),
                    ];
                    if ($identifiers) {
                        array_push($row, $p->vin, $p->nin);
                    }
                    // Stop spreadsheet formula injection from user-entered text.
                    fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) && ! preg_match('/^\+\d+$/', $v) ? "'".$v : $v, $row));
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Canvasser decisions belong to the state coordinator (or a super admin). */
    public static function canReviewCanvassers(User $user): bool
    {
        return $user->isSuperAdmin() || ($user->isCoordinator() && $user->state_id !== null && $user->lga_id === null);
    }

    /** @return array<string, mixed> */
    private function filterOptions(User $viewer, array $filters): array
    {
        $stateId = $viewer->isSuperAdmin() ? ($filters['state_id'] ?? null) : $viewer->state_id;

        return [
            'states' => $viewer->isSuperAdmin() ? State::orderBy('name')->get(['id', 'name']) : collect(),
            'lgas' => $stateId ? Lga::where('state_id', $stateId)
                ->when($viewer->lga_id && ! $viewer->isSuperAdmin(), fn ($q) => $q->whereKey($viewer->lga_id))
                ->orderBy('name')->get(['id', 'name']) : collect(),
            'wards' => ($filters['lga_id'] ?? null) ? Ward::where('lga_id', $filters['lga_id'])->orderBy('name')->get(['id', 'name']) : collect(),
            'genders' => Gender::cases(),
            'ageRanges' => AgeRange::cases(),
            'canvasserStatuses' => CanvasserStatus::cases(),
            'canvasserRoles' => CanvasserRole::cases(),
        ];
    }
}
