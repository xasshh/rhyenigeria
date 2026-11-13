<?php

namespace App\Http\Controllers\Web;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\State;
use App\Services\Membership\MemberDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Member counts for the maps. Aggregates only: never names, numbers or anything about one person. */
class MapController extends Controller
{
    public const STATE_COUNTS_SECONDS = 300;

    /** Homepage map: members per state, keyed by state code. Public and cached. */
    public function states(): JsonResponse
    {
        $data = Cache::remember('map:state-counts', self::STATE_COUNTS_SECONDS, function () {
            $counts = DB::table('states')
                ->leftJoin('users', fn ($join) => $join->on('users.state_id', '=', 'states.id')->where('users.role', Role::Member->value))
                ->leftJoin('member_profiles as mp', 'mp.user_id', '=', 'users.id')
                ->groupBy('states.code')
                ->selectRaw('states.code, count(mp.id) AS n')
                ->pluck('n', 'code')
                ->map(fn ($n) => (int) $n);

            // Plain arrays only: the cache store does not unserialize objects.
            return ['counts' => $counts->all(), 'total' => $counts->sum(), 'updated_at' => now()->toIso8601String()];
        });

        return response()->json($data)->setPublic()->setMaxAge(self::STATE_COUNTS_SECONDS);
    }

    /**
     * Coordinator or admin state map: members per LGA in one state, keyed by LGA id.
     * Starts from the viewer's own scope, so a coordinator only ever gets their area.
     */
    public function lgas(Request $request, MemberDirectory $directory): JsonResponse
    {
        $viewer = $request->user();
        $stateId = $viewer->isSuperAdmin() ? $request->integer('state_id') : $viewer->state_id;
        abort_if(! $stateId || ! State::whereKey($stateId)->exists(), 404);

        $rows = $directory->byArea($directory->query($viewer)->where('users.state_id', $stateId), 'lga');

        return response()->json([
            'counts' => $rows->filter(fn ($row) => $row->id !== null)->mapWithKeys(fn ($row) => [$row->id => (int) $row->total]),
            'total' => (int) $rows->sum('total'),
        ])->setPrivate();
    }
}
