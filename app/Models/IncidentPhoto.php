<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A photo sent with an incident report or one of its updates (private disk). */
#[Fillable(['incident_update_id', 'path'])]
class IncidentPhoto extends Model
{
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }
}
