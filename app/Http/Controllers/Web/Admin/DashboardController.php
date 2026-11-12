<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\State;
use App\Services\Membership\MemberDirectory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, MemberDirectory $directory): View
    {
        $members = $directory->query($request->user());
        $byState = $directory->byArea($members, 'state')->keyBy('id');

        // Every state appears, including those with no members yet.
        $states = State::orderBy('name')->get(['id', 'name'])->map(fn (State $s) => (object) [
            'id' => $s->id,
            'name' => $s->name,
            'total' => (int) ($byState[$s->id]->total ?? 0),
            'pvc' => (int) ($byState[$s->id]->pvc ?? 0),
            'canvassers' => (int) ($byState[$s->id]->canvassers ?? 0),
            'pending' => (int) ($byState[$s->id]->pending ?? 0),
            'today' => (int) ($byState[$s->id]->today ?? 0),
        ]);

        return view('admin.dashboard', [
            'summary' => $directory->summary($members),
            'states' => $states,
            'gender' => $directory->breakdown($members, 'gender'),
            'ages' => $directory->breakdown($members, 'age_range'),
            'pvc' => $directory->breakdown($members, 'has_pvc'),
            'trend' => $directory->perDay($members, (int) $request->integer('days', 60) ?: 60),
        ]);
    }
}
