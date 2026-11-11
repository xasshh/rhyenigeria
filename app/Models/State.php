<?php

namespace App\Models;

use App\Models\Concerns\HasGeometry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name'])]
#[Hidden(['boundary'])]
class State extends Model
{
    use HasFactory, HasGeometry;

    public function lgas(): HasMany
    {
        return $this->hasMany(Lga::class);
    }
}
