<?php

namespace App\Models\Concerns;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Helpers for models with PostGIS geometry columns (SRID 4326).
 *
 * Geometry columns are never selected raw into JSON (they would come back as
 * EWKB hex); queries add latitude/longitude or GeoJSON explicitly.
 */
trait HasGeometry
{
    /** SQL expression for a WGS84 point. Bind as [longitude, latitude]. */
    public static function pointSql(): string
    {
        return 'ST_SetSRID(ST_MakePoint(?, ?), 4326)';
    }

    public static function pointExpression(float $latitude, float $longitude): Expression
    {
        // Values are cast to float and formatted by PHP, so inlining is safe.
        return DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%s, %s), 4326)', self::formatCoordinate($longitude), self::formatCoordinate($latitude)));
    }

    public static function formatCoordinate(float $value): string
    {
        return rtrim(rtrim(number_format($value, 8, '.', ''), '0'), '.') ?: '0';
    }

    /** Add latitude and longitude columns computed from a point column. */
    public function scopeWithCoordinates(Builder $query, string $column = 'location'): void
    {
        $qualified = $query->getModel()->qualifyColumn($column);

        if ($query->getQuery()->columns === null) {
            $query->select($query->getModel()->qualifyColumn('*'));
        }

        $query->selectRaw("ST_Y({$qualified}) as latitude, ST_X({$qualified}) as longitude");
    }
}
