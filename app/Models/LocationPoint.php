<?php

namespace App\Models;

use App\Models\Concerns\HasGeometry;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One reported GPS fix. Rows are written in bulk by LocationIngestor, not
 * through Eloquent.
 */
#[Hidden(['location'])]
class LocationPoint extends Model
{
    use HasGeometry;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'context_resolved_at' => 'datetime',
            'accuracy' => 'float',
            'latitude' => 'float',
            'longitude' => 'float',
            'nearest_polling_unit_distance_m' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function nearestPollingUnit(): BelongsTo
    {
        return $this->belongsTo(PollingUnit::class, 'nearest_polling_unit_id');
    }
}
