<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Eye\ModuleEye;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Module\RoundnessModule;
use BaconQrCode\Renderer\RendererStyle\EyeFill;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;

/**
 * Renders QR codes as inline SVG.
 *
 * Uses bacon/bacon-qr-code, already present as a Fortify dependency for the
 * two-factor setup screen, so no extra package is involved.
 */
class QrCode
{
    /** Quiet zone around the code, in modules: the QR standard asks for four. */
    public const MARGIN = 4;

    /**
     * Side of the logo knock-out as a share of the code's side. About 4% of the
     * modules are covered, well inside what the error correction can restore.
     */
    public const LOGO_SHARE = 0.2;

    /** Graphite data modules (identity ink) on white: far above the contrast a scanner needs. */
    private const INK = [25, 28, 34];

    /**
     * Deep cobalt (the dark end of the brand scale) for the three finder patterns.
     * The brand's #3A5CFF itself is too light there: in a decoding test it lost
     * scans that graphite or this darker blue kept, so it stays in the logo's dot.
     */
    private const EYES = [32, 52, 170];

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

    /**
     * The QR of a customer's booking link in the Atrio identity.
     *
     * Scanning comes first: dark graphite modules on white, a full four-module
     * quiet zone drawn into the file (so the downloaded PNG has it too) and
     * error correction raised to Q. The brand shows in the cobalt finder
     * patterns, softly rounded modules and the Atrio symbol in the middle,
     * kept to about 4% of the modules.
     */
    public static function branded(string $content, int $size = 176, ?ErrorCorrectionLevel $level = null, float $logoShare = self::LOGO_SHARE): string
    {
        $level ??= ErrorCorrectionLevel::Q();

        $ink = new Rgb(...self::INK);
        $cobalt = EyeFill::uniform(new Rgb(...self::EYES));
        $style = new RendererStyle(
            $size,
            self::MARGIN,
            new RoundnessModule(RoundnessModule::SOFT),
            new ModuleEye(new RoundnessModule(RoundnessModule::MEDIUM)),
            Fill::withForegroundColor(new Rgb(255, 255, 255), $ink, $cobalt, $cobalt, $cobalt),
        );

        $svg = (new Writer(new ImageRenderer($style, new SvgImageBackEnd)))->writeString($content, 'UTF-8', $level);
        $svg = trim(substr($svg, strpos($svg, "\n") + 1));

        $matrixWidth = Encoder::encode($content, $level, 'UTF-8')->getMatrix()->getWidth();

        return Str::beforeLast($svg, '</svg>').self::logo($size, $matrixWidth, $logoShare).'</svg>';
    }

    /**
     * The Atrio symbol on a white knock-out, centred on the module grid.
     */
    private static function logo(int $size, int $matrixWidth, float $share): string
    {
        $module = $size / ($matrixWidth + 2 * self::MARGIN);

        // An odd number of modules keeps the logo centred on the grid, like the code itself.
        $modules = (int) round($matrixWidth * $share);
        $modules += $modules % 2 === 0 ? 1 : 0;

        $knockout = $modules * $module;
        $tile = ($modules - 2) * $module;
        $origin = ($size - $knockout) / 2;
        $scale = $tile / 100;

        return sprintf(
            '<rect x="%1$.2f" y="%1$.2f" width="%2$.2f" height="%2$.2f" rx="%3$.2f" fill="#ffffff"/>'
            .'<g transform="translate(%4$.2f %4$.2f) scale(%5$.4f)">'
            .'<rect width="100" height="100" rx="22" fill="#191c22"/>'
            .'<path d="M19.81 84.32L35.41 84.32L59.59 15.68L43.99 15.68Z" fill="#f5f7fa"/>'
            .'<path d="M43.99 15.68L59.59 15.68L75.51 60.92L59.91 60.92Z" fill="#f5f7fa"/>'
            .'<rect x="42.43" y="53.12" width="18.72" height="7.8" fill="#f5f7fa"/>'
            .'<circle cx="72.39" cy="74.18" r="10.14" fill="#3a5cff"/>'
            .'</g>',
            $origin,
            $knockout,
            $module * 1.5,
            $origin + $module,
            $scale,
        );
    }
}
