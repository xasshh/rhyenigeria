<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A follow-up on an incident: more news from the reporter, or a note from the command centre. */
#[Fillable(['user_id', 'body', 'from_command'])]
class IncidentUpdate extends Model
{
    protected function casts(): array
    {
        return ['from_command' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(IncidentPhoto::class);
    }
}
