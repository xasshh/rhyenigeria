<?php

namespace App\Services\Tracking;

use App\Jobs\ResolveLocationContext;
use App\Models\Shift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Stores a batch of location points reported by one agent's device.
 *
 * Devices queue points while offline, so a batch may be large, arrive long
 * after it was recorded, be out of order, and repeat points that were already
 * sent. A point is kept only if its timestamp falls inside one of the agent's
 * own shifts; a repeated UUID is silently ignored.
 */
class LocationIngestor
{
    /**
     * @param  list<array{uuid: string, latitude: float|int|string, longitude: float|int|string, accuracy?: float|int|string|null, recorded_at: string}>  $points
     * @return array{received: int, stored: int, duplicates: int, outside_shift: int, invalid_time: int, matched_shift: int}
     */
    public function ingest(User $agent, array $points): array
    {
        $result = [
            'received' => count($points),
            'stored' => 0,
            'duplicates' => 0,
            'outside_shift' => 0,
            'invalid_time' => 0,
            'matched_shift' => 0,
        ];

        $latestAllowed = CarbonImmutable::now('UTC')->addSeconds((int) config('tracking.max_clock_skew_seconds'));

        // Collapse repeats inside the batch itself.
        $unique = [];
        foreach ($points as $point) {
            $uuid = strtolower($point['uuid']);
            if (isset($unique[$uuid])) {
                $result['duplicates']++;

                continue;
            }
            $unique[$uuid] = $point + ['at' => CarbonImmutable::parse($point['recorded_at'])->utc()];
        }

        $shifts = $this->shiftsCovering($agent, array_column($unique, 'at'));

        $rows = [];
        foreach ($unique as $uuid => $point) {
            if ($point['at']->greaterThan($latestAllowed)) {
                $result['invalid_time']++;

                continue;
            }

            $shift = $this->shiftAt($shifts, $point['at']);
            if ($shift === null) {
                $result['outside_shift']++;

                continue;
            }

            $result['matched_shift']++;
            $rows[] = [
                $uuid,
                $agent->id,
                $shift->id,
                (float) $point['longitude'],
                (float) $point['latitude'],
                isset($point['accuracy']) ? (float) $point['accuracy'] : null,
                $point['at']->format('Y-m-d H:i:s'),
            ];
        }

        if ($rows === []) {
            return $result;
        }

        $insertedIds = DB::transaction(function () use ($rows, $agent) {
            $inserted = [];
            foreach (array_chunk($rows, 500) as $chunk) {
                array_push($inserted, ...$this->insertChunk($chunk));
            }

            if ($inserted !== []) {
                $this->advanceLatestPosition($agent, $inserted);
            }

            return array_column($inserted, 'id');
        });

        $result['stored'] = count($insertedIds);
        $result['duplicates'] += count($rows) - count($insertedIds);

        if ($insertedIds !== []) {
            // Ward / nearest polling unit lookup and the broadcast happen off the request.
            ResolveLocationContext::dispatch($agent->id, $insertedIds);
        }

        return $result;
    }

    /**
     * @param  list<CarbonImmutable>  $times
     * @return list<Shift>
     */
    private function shiftsCovering(User $agent, array $times): array
    {
        if ($times === []) {
            return [];
        }

        $earliest = min($times);
        $latest = max($times);

        return Shift::query()
            ->where('user_id', $agent->id)
            ->where('started_at', '<=', $latest)
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>=', $earliest->startOfSecond()))
            ->orderBy('started_at')
            ->get()
            ->all();
    }

    /** @param list<Shift> $shifts */
    private function shiftAt(array $shifts, CarbonImmutable $at): ?Shift
    {
        $at = $at->startOfSecond();

        foreach ($shifts as $shift) {
            if ($shift->started_at->lessThanOrEqualTo($at) && ($shift->ended_at === null || $shift->ended_at->greaterThanOrEqualTo($at))) {
                return $shift;
            }
        }

        return null;
    }

    /**
     * @param  list<array{0: string, 1: int, 2: int, 3: float, 4: float, 5: ?float, 6: string}>  $rows
     * @return list<array{id: int, recorded_at: string}>
     */
    private function insertChunk(array $rows): array
    {
        $placeholders = implode(', ', array_fill(0, count($rows), '(?, ?, ?, ST_SetSRID(ST_MakePoint(?, ?), 4326), ?, ?, ?)'));
        $now = now('UTC')->format('Y-m-d H:i:s');

        $bindings = [];
        foreach ($rows as $row) {
            array_push($bindings, ...$row, ...[$now]);
        }

        $inserted = DB::select(
            "INSERT INTO location_points (uuid, user_id, shift_id, location, accuracy, recorded_at, created_at)
             VALUES {$placeholders}
             ON CONFLICT (uuid) DO NOTHING
             RETURNING id, recorded_at",
            $bindings,
        );

        return array_map(fn ($row) => ['id' => (int) $row->id, 'recorded_at' => (string) $row->recorded_at], $inserted);
    }

    /**
     * Move the agent's latest position forward if this batch contains a newer
     * point. Late-arriving old points never move it backwards.
     *
     * @param  list<array{id: int, recorded_at: string}>  $inserted
     */
    private function advanceLatestPosition(User $agent, array $inserted): void
    {
        usort($inserted, fn ($a, $b) => [$b['recorded_at'], $b['id']] <=> [$a['recorded_at'], $a['id']]);

        DB::statement(
            'INSERT INTO agent_positions (user_id, location_point_id, location, accuracy, recorded_at, updated_at)
             SELECT user_id, id, location, accuracy, recorded_at, ? FROM location_points WHERE id = ?
             ON CONFLICT (user_id) DO UPDATE SET
                 location_point_id = EXCLUDED.location_point_id,
                 location = EXCLUDED.location,
                 accuracy = EXCLUDED.accuracy,
                 recorded_at = EXCLUDED.recorded_at,
                 ward_id = NULL,
                 nearest_polling_unit_id = NULL,
                 nearest_polling_unit_distance_m = NULL,
                 updated_at = EXCLUDED.updated_at
             WHERE agent_positions.recorded_at < EXCLUDED.recorded_at',
            [now('UTC')->format('Y-m-d H:i:s'), $inserted[0]['id']],
        );
    }
}
