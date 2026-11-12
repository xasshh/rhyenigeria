<?php

namespace App\Http\Controllers\Web\Staff;

use App\Http\Controllers\Controller;
use App\Models\EmpowermentReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmpowermentController extends Controller
{
    public function index(Request $request): View
    {
        $reports = EmpowermentReport::query()
            ->visibleTo($request->user())
            ->when($request->query('q'), fn ($q, $term) => $q->where('programme', 'ilike', '%'.addcslashes($term, '%_\\').'%'))
            ->with(['user:id,name,state_id,lga_id,ward_id', 'user.state:id,name', 'user.lga:id,name', 'user.ward:id,name', 'user.memberProfile:id,user_id,member_number'])
            ->latest()->paginate(24)->withQueryString();

        return view('staff.empowerment', compact('reports'));
    }

    /** The photo is on the private disk; members see their own, staff see their area's. */
    public function photo(Request $request, EmpowermentReport $report): StreamedResponse
    {
        $viewer = $request->user();
        $allowed = $report->user_id === $viewer->id
            || EmpowermentReport::query()->visibleTo($viewer)->whereKey($report->id)->exists();

        abort_unless($allowed && $report->photo_path && Storage::disk('local')->exists($report->photo_path), 404);

        return Storage::disk('local')->response($report->photo_path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
