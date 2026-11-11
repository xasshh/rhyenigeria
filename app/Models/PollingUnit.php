<?php

namespace App\Models;

use App\Models\Concerns\HasGeometry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ward_id', 'code', 'name', 'location'])]
#[Hidden(['location', 'boundary'])]
class PollingUnit extends Model
{
    use HasFactory, HasGeometry;

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }
}
