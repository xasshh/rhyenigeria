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

    /**
     * The same areas as GeoJSON for the command centre's live map: boundaries, plus a label
     * point inside each one. A state's LGAs, or every state when no state is given.
     *
     * @return array{areas: array<string, mixed>, labels: array<string, mixed>}
     */
    public function geojson(?State $state): array
    {
        $key = $state ? "area-map:geojson:lgas:{$state->id}:v1" : 'area-map:geojson:states:v1';

        return Cache::remember($key, self::CACHE_SECONDS, function () use ($state) {
            $rows = $state
                ? DB::select('SELECT id, name, ST_AsGeoJSON(ST_SimplifyPreserveTopology(boundary, 0.001), 5) AS geo, ST_AsGeoJSON(ST_PointOnSurface(boundary), 5) AS label FROM lgas WHERE state_id = ? AND boundary IS NOT NULL ORDER BY name', [$state->id])
                : DB::select('SELECT id, name, ST_AsGeoJSON(ST_SimplifyPreserveTopology(boundary, 0.005), 4) AS geo, ST_AsGeoJSON(ST_PointOnSurface(boundary), 4) AS label FROM states WHERE boundary IS NOT NULL ORDER BY name');

            $feature = fn ($row, string $geometry) => ['type' => 'Feature', 'id' => (int) $row->id, 'geometry' => json_decode($geometry, true), 'properties' => ['id' => (int) $row->id, 'name' => $row->name]];

            return [
                'areas' => ['type' => 'FeatureCollection', 'features' => array_map(fn ($r) => $feature($r, $r->geo), $rows)],
                'labels' => ['type' => 'FeatureCollection', 'features' => array_map(fn ($r) => $feature($r, $r->label), $rows)],
            ];
        });
    }

    /** Clear the cached shapes, for example after `geo:import` loads new boundaries. */
    public static function forget(): void
    {
        Cache::forget('area-map:states:v1');
        Cache::forget('area-map:geojson:states:v1');
        foreach (State::pluck('id') as $id) {
            Cache::forget("area-map:lgas:{$id}:v1");
            Cache::forget("area-map:geojson:lgas:{$id}:v1");
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
