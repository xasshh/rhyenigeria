<?php

namespace App\Services\Geo;

use App\Models\State;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Turns state and LGA boundaries (PostGIS) into SVG paths, so the homepage and
 * coordinator maps need no mapping library. Shapes are simplified for mobile
 * data and cached; member counts are served separately.
 */
class AreaMap
{
    public const WIDTH = 1000;

    private const CACHE_SECONDS = 86400;

    /** @return array{viewBox: string, areas: list<array{id: int, code: string, name: string, d: string}>} */
    public function states(): array
    {
        return Cache::remember('area-map:states:v1', self::CACHE_SECONDS, fn () => $this->build(
            DB::select('SELECT id, code, name, ST_AsGeoJSON(ST_SimplifyPreserveTopology(boundary, 0.01), 4) AS geo FROM states WHERE boundary IS NOT NULL ORDER BY name'),
        ));
    }

    /** @return array{viewBox: string, areas: list<array{id: int, code: string, name: string, d: string}>} */
    public function lgas(State $state): array
    {
        return Cache::remember("area-map:lgas:{$state->id}:v1", self::CACHE_SECONDS, fn () => $this->build(
            DB::select('SELECT id, code, name, ST_AsGeoJSON(ST_SimplifyPreserveTopology(boundary, 0.0025), 5) AS geo FROM lgas WHERE state_id = ? AND boundary IS NOT NULL ORDER BY name', [$state->id]),
        ));
    }

    /** Clear the cached shapes, for example after `geo:import` loads new boundaries. */
    public static function forget(): void
    {
        Cache::forget('area-map:states:v1');
        foreach (State::pluck('id') as $id) {
            Cache::forget("area-map:lgas:{$id}:v1");
        }
    }

    /**
     * @param  list<object{id: int, code: string, name: string, geo: string}>  $rows
     * @return array{viewBox: string, areas: list<array{id: int, code: string, name: string, d: string}>}
     */
    private function build(array $rows): array
    {
        if ($rows === []) {
            return ['viewBox' => '0 0 '.self::WIDTH.' '.self::WIDTH, 'areas' => []];
        }

        $polygons = [];
        $minLon = $minLat = INF;
        $maxLon = $maxLat = -INF;

        foreach ($rows as $row) {
            $geometry = json_decode($row->geo, true);
            $rings = $geometry['type'] === 'Polygon' ? [$geometry['coordinates']] : $geometry['coordinates'];
            $polygons[$row->id] = $rings;
            foreach ($rings as $polygon) {
                foreach ($polygon[0] as [$lon, $lat]) {
                    $minLon = min($minLon, $lon);
                    $maxLon = max($maxLon, $lon);
                    $minLat = min($minLat, $lat);
                    $maxLat = max($maxLat, $lat);
                }
            }
        }

        // Equirectangular, corrected for latitude: accurate enough for a country this size.
        $k = cos(deg2rad(($minLat + $maxLat) / 2));
        $scale = self::WIDTH / max(($maxLon - $minLon) * $k, 1e-9);
        $height = (int) ceil(($maxLat - $minLat) * $scale);
        $project = fn (float $lon, float $lat) => round(($lon - $minLon) * $k * $scale, 1).' '.round(($maxLat - $lat) * $scale, 1);

        $areas = [];
        foreach ($rows as $row) {
            $d = '';
            foreach ($polygons[$row->id] as $polygon) {
                foreach ($polygon as $ring) {
                    $points = array_map(fn ($p) => $project($p[0], $p[1]), $ring);
                    $d .= 'M'.implode('L', $points).'Z';
                }
            }
            $areas[] = ['id' => (int) $row->id, 'code' => (string) $row->code, 'name' => (string) $row->name, 'd' => $d];
        }

        return ['viewBox' => '0 0 '.self::WIDTH.' '.$height, 'areas' => $areas];
    }
}
