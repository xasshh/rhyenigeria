<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\State;
use App\Models\User;
use App\Models\Ward;
use App\Services\Audit\AuditLogger;
use App\Services\Geo\AreaMap;
use App\Services\Membership\MemberDirectory;
use App\Services\Tracking\LiveOperations;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The election command centre: one full-screen, live view of the campaign for the
 * leadership. Super admins only (route group); every opening is audit-logged.
 * A single-state site watches its LGAs; the national site watches the states.
 */
class CommandCentreController extends Controller
{
    public function index(Request $request, AreaMap $map, AuditLogger $audit): View
    {
        $state = $this->state();
        $shapes = $state ? $map->lgas($state) : $map->states();
        $audit->record($request, AuditLogger::COMMAND_CENTRE_OPENED);

        return view('admin.command', [
            'state' => $state,
            'map' => $shapes,
            'areaNoun' => $state ? 'LGA' : 'state',
            'electionDate' => Setting::electionDate(),
            'electionLabel' => Setting::electionLabel(),
            'config' => [
                'feed' => route('admin.command.feed'),
                'refreshSeconds' => 30,
                'operator' => $request->user()->name,
                'scope' => $state ? $state->name.' State' : 'Nigeria',
                'areasTotal' => count($shapes['areas']),
            ],
        ]);
    }

    public function feed(Request $request, MemberDirectory $directory, LiveOperations $live): JsonResponse
    {
        $viewer = $request->user();
        $state = $this->state();
        $members = $directory->query($viewer)->when($state, fn ($q) => $q->where('users.state_id', $state->id));

        $areas = $directory->byArea($members, $state ? 'lga' : 'state')->filter(fn ($a) => $a->id !== null)->values();
        $field = $live->feed($viewer, $state?->id);
        $tz = config('membership.timezone');

        $wardsCovered = (clone $members)->toBase()->whereNotNull('users.ward_id')->distinct()->count('users.ward_id');
        $wardsTotal = $state
            ? Ward::whereHas('lga', fn ($q) => $q->where('state_id', $state->id))->count()
            : Ward::count();

        $recent = (clone $members)->with('lga:id,name', 'ward:id,name')
            ->select('users.id', 'users.name', 'users.lga_id', 'users.ward_id', 'mp.created_at as joined_at', 'mp.registered_by_user_id', 'mp.import_source')
            ->whereNull('mp.import_source')
            ->latest('mp.created_at')->limit(12)->get()
            ->map(fn (User $u) => [
                'name' => $u->publicName(),
                'place' => collect([$u->ward?->name, $u->lga?->name])->filter()->implode(', '),
                'via' => $u->registered_by_user_id ? 'field' : 'web',
                'time' => CarbonImmutable::parse($u->joined_at, 'UTC')->setTimezone($tz)->format('H:i'),
            ]);

        return response()->json([
            'summary' => $directory->summary($members),
            'areas' => $areas->map(fn ($a) => [
                'id' => (int) $a->id, 'name' => $a->name, 'total' => (int) $a->total,
                'today' => (int) $a->today, 'agents' => (int) $a->canvassers,
            ]),
            'coverage' => [
                'wards' => $wardsCovered, 'wards_total' => $wardsTotal,
                'areas' => $areas->where('total', '>', 0)->count(),
            ],
            // Bulk-imported lists would swamp the day they were loaded; the trend shows people signing up.
            'trend' => $directory->perDay((clone $members)->whereNull('mp.import_source'), 30),
            'field' => $field['totals'],
            'activities' => collect($field['activities'])->take(10)->map(fn ($a) => [
                'who' => $a['canvasser'], 'label' => $a['label'], 'ward' => $a['ward'], 'time' => $a['time'],
            ])->values(),
            'recent' => $recent,
            'generated_at' => now()->toIso8601ZuluString(),
        ])->setPrivate();
    }

    private function state(): ?State
    {
        return site_state_code() ? State::where('code', site_state_code())->first() : null;
    }
}
