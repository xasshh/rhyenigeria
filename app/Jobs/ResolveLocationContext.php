<?php

namespace App\Jobs;

use App\Events\AgentPositionUpdated;
use App\Models\AgentPosition;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * For a batch of newly stored points: work out the ward each one falls in and
 * its nearest polling unit, copy that onto the agent's latest position, and
 * broadcast the latest position if this batch moved it.
 */
class ResolveLocationContext implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    /** @param list<int> $pointIds */
    public function __construct(public int $userId, public array $pointIds) {}

    public function handle(): void
    {
        $ids = array_values(array_unique(array_map('intval', $this->pointIds)));

        if ($ids === []) {
            return;
        }

        $in = implode(', ', array_fill(0, count($ids), '?'));
        $now = now('UTC')->format('Y-m-d H:i:s');

        // Ward: the polygon that covers the point (none if boundaries are not
        // loaded). Polling unit: index-assisted nearest neighbour, with the
        // true distance in metres.
        DB::update(
            "UPDATE location_points lp
             SET ward_id = c.ward_id,
                 nearest_polling_unit_id = c.polling_unit_id,
                 nearest_polling_unit_distance_m = c.distance_m,
                 context_resolved_at = ?
             FROM (
                 SELECT p.id,
                        (SELECT w.id FROM wards w WHERE ST_Covers(w.boundary, p.location) ORDER BY w.id LIMIT 1) AS ward_id,
                        n.id AS polling_unit_id,
                        n.distance_m
                 FROM location_points p
                 LEFT JOIN LATERAL (
                     SELECT pu.id, ST_Distance(pu.location::geography, p.location::geography) AS distance_m
                     FROM polling_units pu
                     WHERE pu.location IS NOT NULL
                     ORDER BY pu.location <-> p.location
                     LIMIT 1
                 ) n ON true
                 WHERE p.id IN ({$in})
             ) c
             WHERE lp.id = c.id",
            [$now, ...$ids],
        );

        $moved = DB::update(
            "UPDATE agent_positions ap
             SET ward_id = lp.ward_id,
                 nearest_polling_unit_id = lp.nearest_polling_unit_id,
                 nearest_polling_unit_distance_m = lp.nearest_polling_unit_distance_m
             FROM location_points lp
             WHERE ap.location_point_id = lp.id AND ap.user_id = ? AND lp.id IN ({$in})",
            [$this->userId, ...$ids],
        );

        if ($moved === 0) {
            // Only older points arrived; the live position did not change.
            return;
        }

        $position = AgentPosition::query()
            ->withCoordinates()
            ->with(['user', 'ward', 'nearestPollingUnit'])
            ->find($this->userId);

        if ($position) {
            AgentPositionUpdated::dispatch($position->toMapArray());
        }
    }
}
