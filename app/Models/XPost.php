<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\XPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A post on X shown on the homepage. Admins add them by pasting the post's link. */
#[Fillable(['status_id', 'handle', 'posted_at', 'added_by'])]
class XPost extends Model
{
    /** @use HasFactory<XPostFactory> */
    use HasFactory;

    /** Posts in the homepage grid at first, and how many more each "Load more" shows. */
    public const HOMEPAGE_COUNT = 6;

    /** The most the homepage holds; older ones stay on X. */
    public const HOMEPAGE_MAX = 60;

    /** x.com/<handle>/status/<id> (also twitter.com, mobile., with or without query strings). */
    public const LINK_PATTERN = '#^https?://(?:www\.|mobile\.)?(?:x|twitter)\.com/([A-Za-z0-9_]{1,15})/status(?:es)?/(\d{5,25})(?:[/?\#].*)?$#i';

    protected function casts(): array
    {
        return ['status_id' => 'integer', 'posted_at' => 'datetime'];
    }

    /** @return array{handle: string, status_id: int}|null */
    public static function parseLink(string $link): ?array
    {
        if (! preg_match(self::LINK_PATTERN, trim($link), $m)) {
            return null;
        }

        return ['handle' => $m[1], 'status_id' => (int) $m[2]];
    }

    /** When the post was made, read from its id (X ids carry a millisecond timestamp). */
    public static function timeFromId(int $id): ?CarbonImmutable
    {
        $ms = ($id >> 22) + 1288834974657;

        return $ms > 1288834974657 ? CarbonImmutable::createFromTimestampMs($ms) : null;
    }

    public function scopeNewest(Builder $query): Builder
    {
        return $query->orderByDesc('status_id');
    }

    public function url(): string
    {
        return "https://x.com/{$this->handle}/status/{$this->status_id}";
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
