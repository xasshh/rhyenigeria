<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['submission_id', 'disk', 'path', 'original_name', 'mime_type', 'size'])]
#[Hidden(['disk', 'path'])]
class SubmissionPhoto extends Model
{
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
