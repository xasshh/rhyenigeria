<?php

namespace App\Http\Controllers\Web\Staff;

use App\Enums\CanvasserStatus;
use App\Http\Controllers\Controller;
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
        $status = CanvasserStatus::tryFrom((string) $request->query('status')) ?? CanvasserStatus::Pending;

        $applications = $directory->query($request->user())
            ->where('mp.canvasser_status', $status->value)
            ->select('users.*')
            ->with(['memberProfile.reviewer:id,name', 'state:id,name', 'lga:id,name', 'ward:id,name'])
            ->orderBy($status === CanvasserStatus::Pending ? 'mp.canvasser_applied_at' : 'mp.canvasser_reviewed_at', $status === CanvasserStatus::Pending ? 'asc' : 'desc')
            ->paginate(25)->withQueryString();

        return view('staff.canvassers', [
            'applications' => $applications,
            'status' => $status,
            'counts' => $directory->summary($directory->query($request->user())),
            'canReview' => MemberController::canReviewCanvassers($request->user()),
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
