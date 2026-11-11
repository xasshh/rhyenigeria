<?php

namespace App\Models;

use App\Models\Concerns\HasGeometry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['state_id', 'code', 'name'])]
#[Hidden(['boundary'])]
class Lga extends Model
{
    use HasFactory, HasGeometry;

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function wards(): HasMany
    {
        return $this->hasMany(Ward::class);
    }
}
