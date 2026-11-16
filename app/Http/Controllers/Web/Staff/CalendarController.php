<?php

namespace App\Http\Controllers\Web\Staff;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\State;
use App\Support\EventCalendar;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Past and upcoming events by month. Coordinators: national events plus their state's. Super admins: everything, or one state. */
class CalendarController extends Controller
{
    public function index(Request $request): View
    {
        $viewer = $request->user();
        $stateId = $viewer->isSuperAdmin() ? ($request->integer('state_id') ?: null) : null;

        $events = Event::query()->when(
            $viewer->isSuperAdmin(),
            fn ($q) => $q->when($stateId, fn ($q) => $q->forState($stateId)),
            fn ($q) => $q->forState($viewer->state_id),
        );

        return view('staff.calendar', [
            'calendar' => EventCalendar::for($events, $request->query('month')),
            'states' => $viewer->isSuperAdmin() ? State::orderBy('name')->get(['id', 'name']) : collect(),
            'stateId' => $stateId,
            'upcoming' => (clone $events)->upcoming()->with('state:id,name')->limit(5)->get(),
        ]);
    }
}
