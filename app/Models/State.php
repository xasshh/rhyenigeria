<?php

namespace App\Models;

use App\Models\Concerns\HasGeometry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['code', 'name'])]
#[Hidden(['boundary'])]
class State extends Model
{
    use HasFactory, HasGeometry;

    public function lgas(): HasMany
    {
        return $this->hasMany(Lga::class);
    }

    /** The state coordinator's photo for the public WhatsApp groups page (public disk). */
    public function coordinatorPhotoUrl(): ?string
    {
        return $this->coordinator_photo_path ? Storage::disk('public')->url($this->coordinator_photo_path) : null;
    }
}
