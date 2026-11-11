<?php

namespace App\Models;

use App\Models\Concerns\HasGeometry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['lga_id', 'code', 'name'])]
#[Hidden(['boundary'])]
class Ward extends Model
{
    use HasFactory, HasGeometry;

    public function lga(): BelongsTo
    {
        return $this->belongsTo(Lga::class);
    }

    public function pollingUnits(): HasMany
    {
        return $this->hasMany(PollingUnit::class);
    }
}
