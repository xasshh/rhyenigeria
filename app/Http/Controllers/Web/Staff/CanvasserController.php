<?php

namespace App\Http\Controllers\Web\Staff;

use App\Enums\CanvasserStatus;
use App\Http\Controllers\Controller;
use App\Models\State;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Membership\MemberDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CanvasserController extends Controller
{
    public const APPROVED = 'canvasser.approved';

    public const REJECTED = 'canvasser.rejected';

    public function index(Request $request, MemberDirectory $directory): View
    {
        $viewer = $request->user();
        $status = CanvasserStatus::tryFrom((string) $request->query('status')) ?? CanvasserStatus::Pending;
        // Super admins can narrow the list to one state; coordinators are already scoped to theirs.
        $stateId = $viewer->isSuperAdmin() ? ($request->integer('state_id') ?: null) : null;
        $scoped = fn () => $directory->query($viewer)->when($stateId, fn ($q, $id) => $q->where('users.state_id', $id));

        // Newest first: the latest applications (or decisions) on top.
        $applications = $scoped()
            ->where('mp.canvasser_status', $status->value)
            ->select('users.*')
            ->with(['memberProfile.reviewer:id,name', 'state:id,name', 'lga:id,name', 'ward:id,name'])
            ->orderByRaw(($status === CanvasserStatus::Pending ? 'mp.canvasser_applied_at' : 'mp.canvasser_reviewed_at').' desc nulls last')
            ->orderByDesc('users.id')
            ->paginate(25)->withQueryString();

        return view('staff.canvassers', [
            'applications' => $applications,
            'status' => $status,
            'stateId' => $stateId,
            'states' => $viewer->isSuperAdmin() && ! site_state_code() ? State::orderBy('name')->get(['id', 'name']) : collect(),
            'counts' => $directory->summary($scoped()),
            'canReview' => MemberController::canReviewCanvassers($viewer),
        ]);
    }

    public function decide(Request $request, User $member, AuditLogger $audit): RedirectResponse
    {
        $viewer = $request->user();
        abort_unless($viewer->canSeeMember($member), 404);
        abort_unless(MemberController::canReviewCanvassers($viewer), 403, 'Only the state coordinator can review canvasser applications.');

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $profile = $member->memberProfile;
        abort_if($profile?->canvasser_status === null, 422, 'This member has not applied to canvass.');

        $previous = $profile->canvasser_status;
        $profile->canvasser_status = $data['decision'] === 'approve' ? CanvasserStatus::Approved : CanvasserStatus::Rejected;
        $profile->canvasser_reviewed_by = $viewer->id;
        $profile->canvasser_reviewed_at = now();
        $profile->canvasser_review_note = $data['note'] ?? null;
        $profile->save();

        $audit->record($request, $data['decision'] === 'approve' ? self::APPROVED : self::REJECTED, $member, [
            'member_number' => $profile->member_number,
            'previous_status' => $previous->value,
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('status', $member->name.($data['decision'] === 'approve' ? ' is now a canvasser.' : '’s application was rejected.'));
    }
}
