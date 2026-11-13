<?php

namespace App\Models;

use Database\Factories\HeroSlideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** One slide of the homepage header slider. */
#[Fillable(['position', 'is_active', 'layout', 'image_alt', 'eyebrow', 'headline', 'body', 'button_label', 'button_url'])]
class HeroSlide extends Model
{
    /** @use HasFactory<HeroSlideFactory> */
    use HasFactory;

    /** Files shipped with the code live under public/images; uploads are on the public disk. */
    public const BUNDLED_PREFIX = 'images/';

    /** A picture beside the text. */
    public const LAYOUT_IMAGE = 'image';

    /** The original landing-page hero: round portrait and the three stat badges. */
    public const LAYOUT_CLASSIC = 'classic';

    /** A photo cropped to a circle with a thin green ring, like the President's portrait on the original hero. */
    public const LAYOUT_PORTRAIT = 'portrait';

    public const LAYOUTS = [
        self::LAYOUT_PORTRAIT => 'Portrait in a circle (for photos of people)',
        self::LAYOUT_IMAGE => 'Image beside the text (for flyers)',
        self::LAYOUT_CLASSIC => 'Original hero (round portrait, stat badges)',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function scopeShown(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('position')->orderBy('id');
    }

    public function isClassic(): bool
    {
        return $this->layout === self::LAYOUT_CLASSIC;
    }

    public function isPortrait(): bool
    {
        return $this->layout === self::LAYOUT_PORTRAIT;
    }

    public function imageUrl(): ?string
    {
        return self::urlFor($this->image_path);
    }

    public function originalImageUrl(): ?string
    {
        return self::urlFor($this->image_original_path);
    }

    private static function urlFor(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return str_starts_with($path, self::BUNDLED_PREFIX) ? asset($path) : Storage::disk('public')->url($path);
    }

    /** Remove uploaded files (never the bundled ones). */
    public function deleteUploadedImages(): void
    {
        foreach ([$this->image_path, $this->image_original_path] as $path) {
            if ($path && ! str_starts_with($path, self::BUNDLED_PREFIX)) {
                Storage::disk('public')->delete($path);
            }
        }
    }
}
