<?php

use App\Support\QrCode;

function manageLink(): string
{
    return 'https://studio-rossi.example.org/p/studio-rossi/b/'.str_repeat('k', 32);
}

test('the branded QR is a well-formed SVG in the Atrio colours', function () {
    $svg = QrCode::branded(manageLink(), 176);

    libxml_use_internal_errors(true);

    expect((new DOMDocument)->loadXML($svg))->toBeTrue()
        ->and($svg)
        ->toContain('viewBox="0 0 176 176"')
        ->toContain('#191c22')
        ->toContain('#2034aa')
        ->toContain('#3a5cff');
});

test('the logo stays small enough for the error correction to cover it', function () {
    $svg = QrCode::branded(manageLink(), 176);

    preg_match_all('/<rect x="[\d.]+" y="[\d.]+" width="([\d.]+)" height="[\d.]+" rx="[\d.]+" fill="#ffffff"\/>/', $svg, $knockouts);

    // The share of the whole picture, quiet zone included, so the share of the modules is higher but still small.
    expect($knockouts[1])->toHaveCount(1)
        ->and((float) $knockouts[1][0] / 176)->toBeLessThanOrEqual(0.25);
});

test('the quiet zone is drawn into the file, so the downloaded PNG has it too', function () {
    expect(QrCode::branded(manageLink()))->toContain('<g transform="translate(4,4)">');
});
