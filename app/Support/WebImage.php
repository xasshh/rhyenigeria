<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Stores an uploaded image on the public disk together with a web-sized WebP copy (GD only, no imagick). */
final class WebImage
{
    /**
     * @return array{0: string, 1: string} [original path, webp path]
     */
    public static function storeWithWebp(UploadedFile $file, string $directory, int $maxWidth = 1600, int $quality = 80): array
    {
        $name = Str::uuid()->toString();
        $original = $file->storeAs($directory, $name.'.'.strtolower($file->extension()), 'public');

        $source = match (strtolower($file->extension())) {
            'jpg', 'jpeg' => imagecreatefromjpeg($file->getRealPath()),
            'png' => imagecreatefrompng($file->getRealPath()),
            'webp' => imagecreatefromwebp($file->getRealPath()),
            default => false,
        };

        if ($source === false) {
            throw new RuntimeException('Could not read that image.');
        }

        $width = imagesx($source);
        if ($width > $maxWidth) {
            $scaled = imagescale($source, $maxWidth, -1, IMG_BICUBIC);
            imagedestroy($source);
            $source = $scaled;
        }
        imagepalettetotruecolor($source);
        imagealphablending($source, true);
        imagesavealpha($source, true);

        ob_start();
        imagewebp($source, null, $quality);
        $webp = (string) ob_get_clean();
        imagedestroy($source);

        $webpPath = $directory.'/'.$name.'-web.webp';
        Storage::disk('public')->put($webpPath, $webp);

        return [$original, $webpPath];
    }
}
