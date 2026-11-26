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
        $extension = strtolower($file->extension());

        $source = match ($extension) {
            'jpg', 'jpeg' => imagecreatefromjpeg($file->getRealPath()),
            'png' => imagecreatefrompng($file->getRealPath()),
            'webp' => imagecreatefromwebp($file->getRealPath()),
            default => false,
        };

        if ($source === false) {
            throw new RuntimeException('Could not read that image.');
        }

        imagepalettetotruecolor($source);
        imagealphablending($source, true);
        imagesavealpha($source, true);

        // Full-size display copy, re-encoded through GD so no EXIF/GPS metadata survives the upload.
        $original = $directory.'/'.$name.'.'.($extension === 'jpeg' ? 'jpg' : $extension);
        Storage::disk('public')->put($original, self::encode($source, $extension, 92));

        // Smaller WebP for fast loading; metadata is dropped here too. The full-size image is freed
        // as soon as the scaled copy exists, so two large bitmaps are never held at once.
        $scaled = imagesx($source) > $maxWidth ? imagescale($source, $maxWidth, -1, IMG_BICUBIC) : false;
        if ($scaled !== false) {
            imagedestroy($source);
            $web = $scaled;
            imagepalettetotruecolor($web);
            imagealphablending($web, true);
            imagesavealpha($web, true);
        } else {
            $web = $source;
        }

        ob_start();
        imagewebp($web, null, $quality);
        $webp = (string) ob_get_clean();
        imagedestroy($web);

        $webpPath = $directory.'/'.$name.'-web.webp';
        Storage::disk('public')->put($webpPath, $webp);

        return [$original, $webpPath];
    }

    /** Encode a GD image to the given format, dropping any metadata. */
    private static function encode(\GdImage $image, string $extension, int $quality): string
    {
        ob_start();
        match ($extension) {
            'png' => imagepng($image),
            'webp' => imagewebp($image, null, $quality),
            default => imagejpeg($image, null, $quality),
        };

        return (string) ob_get_clean();
    }

    /**
     * Store a photo on the private disk as a web-sized WebP. Formats GD cannot read (iPhone HEIC)
     * are kept as they are, so nothing a reporter sends is lost.
     */
    public static function storePrivate(UploadedFile $file, string $directory, int $maxWidth = 1600, int $quality = 78): string
    {
        $extension = strtolower($file->extension() ?: $file->getClientOriginalExtension());
        $source = match ($extension) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($file->getRealPath()),
            'png' => @imagecreatefrompng($file->getRealPath()),
            'webp' => @imagecreatefromwebp($file->getRealPath()),
            default => false,
        };

        if ($source === false) {
            return $file->storeAs($directory, Str::uuid()->toString().'.'.($extension ?: 'jpg'), 'local');
        }

        // Phone photos are often stored sideways with an EXIF note; turn them upright.
        if (in_array($extension, ['jpg', 'jpeg'], true) && function_exists('exif_read_data')) {
            $orientation = (int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1);
            $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
            if ($angle !== 0) {
                $rotated = imagerotate($source, $angle, 0);
                imagedestroy($source);
                $source = $rotated;
            }
        }

        if (imagesx($source) > $maxWidth) {
            $scaled = imagescale($source, $maxWidth, -1, IMG_BICUBIC);
            imagedestroy($source);
            $source = $scaled;
        }
        imagepalettetotruecolor($source);

        ob_start();
        imagewebp($source, null, $quality);
        $webp = (string) ob_get_clean();
        imagedestroy($source);

        $path = $directory.'/'.Str::uuid()->toString().'.webp';
        Storage::disk('local')->put($path, $webp);

        return $path;
    }
}
