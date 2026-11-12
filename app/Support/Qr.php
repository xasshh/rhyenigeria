<?php

namespace App\Support;

use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

final class Qr
{
    /** An inline SVG QR code. Pure PHP, so the server needs neither GD nor imagick. */
    public static function svg(string $data, int $size = 256, string $colour = '#0b3d20'): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => false,
            'svgAddXmlHeader' => false,
            'addQuietzone' => false,
            'connectPaths' => true,
            'drawLightModules' => false,
        ]);

        $svg = (new QRCode($options))->render($data);

        // Explicit size (browsers need one to draw it on a canvas) and colour.
        return str_replace(
            ['<svg xmlns="http://www.w3.org/2000/svg"', 'fill="#000"'],
            ['<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" role="img" aria-label="QR code"', 'fill="'.$colour.'"'],
            $svg,
        );
    }
}
