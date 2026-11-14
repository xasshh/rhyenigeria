<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** One photo of a news post, shown in its gallery. */
#[Fillable(['path', 'original_path', 'alt', 'position'])]
class NewsPhoto extends Model
{
    /** Files shipped with the code live under public/images; uploads are on the public disk. */
    public const BUNDLED_PREFIX = 'images/';

    public function post(): BelongsTo
    {
        return $this->belongsTo(NewsPost::class, 'news_post_id');
    }

    public function url(): string
    {
        return self::urlFor($this->path);
    }

    public function originalUrl(): string
    {
        return self::urlFor($this->original_path ?? $this->path);
    }

    /** Remove uploaded files (never the bundled ones). */
    public function deleteFiles(): void
    {
        foreach ([$this->path, $this->original_path] as $path) {
            if ($path && ! str_starts_with($path, self::BUNDLED_PREFIX)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    private static function urlFor(string $path): string
    {
        return str_starts_with($path, self::BUNDLED_PREFIX) ? asset($path) : Storage::disk('public')->url($path);
    }
}
