<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Renders QR codes as inline SVG.
 *
 * Uses bacon/bacon-qr-code, already present as a Fortify dependency for the
 * two-factor setup screen, so no extra package is involved.
 */
class QrCode
{
    /**
     * An inline SVG for the given content, without the XML declaration.
     *
     * The SVG inherits the surrounding text colour, so it reads correctly in
     * both light and dark themes.
     */
    public static function svg(string $content, int $size = 180): string
    {
        $svg = (new Writer(
            new ImageRenderer(new RendererStyle($size, 0), new SvgImageBackEnd),
        ))->writeString($content);

        // Drop the XML declaration: the markup is embedded, not served as a file.
        return trim(substr($svg, strpos($svg, "\n") + 1));
    }
}
