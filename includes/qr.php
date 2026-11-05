<?php
declare(strict_types=1);

// Dependensi lokal berlisensi BSD-2-Clause; tidak mengirim data ke API QR pihak ketiga.
spl_autoload_register(static function (string $class): void {
    $namespaces = [
        'BaconQrCode\\' => __DIR__ . '/vendor/bacon-qr-code/src/',
        'DASPRiD\\Enum\\' => __DIR__ . '/vendor/enum/src/',
    ];
    foreach ($namespaces as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $relative = substr($class, strlen($prefix));
            if (!preg_match('/^[A-Za-z0-9_\\\\]+$/D', $relative)) return;
            $path = $directory . str_replace('\\', '/', $relative) . '.php';
            if (is_file($path)) require_once $path;
            return;
        }
    }
});

function internal_qr_payload(array $vehicle): string
{
    $reference = trim((string) ($vehicle['nomor_referensi'] ?? ''));
    return "KENDARA - REFERENSI INTERNAL\nDATA #" . (int) $vehicle['id']
        . ($reference !== '' ? "\nREF: " . $reference : '');
}

function internal_qr_svg(array $vehicle): string
{
    $code = \BaconQrCode\Encoder\Encoder::encode(
        internal_qr_payload($vehicle),
        \BaconQrCode\Common\ErrorCorrectionLevel::M(),
        'UTF-8'
    );
    $matrix = $code->getMatrix();
    $quietZone = 4;
    $width = $matrix->getWidth();
    $size = $width + 2 * $quietZone;
    $path = '';
    for ($y = 0; $y < $width; $y++) {
        for ($x = 0; $x < $width; $x++) {
            if ($matrix->get($x, $y) !== 1) continue;
            $start = $x;
            while ($x + 1 < $width && $matrix->get($x + 1, $y) === 1) $x++;
            $length = $x - $start + 1;
            $path .= 'M' . ($start + $quietZone) . ' ' . ($y + $quietZone)
                . 'h' . $length . 'v1h-' . $length . 'z';
        }
    }
    // Hanya koordinat bilangan bulat dari encoder yang dimasukkan ke SVG.
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $size . ' ' . $size . '"'
        . ' role="img" aria-label="QR referensi internal kendaraan" shape-rendering="crispEdges">'
        . '<title>QR referensi internal</title><rect width="' . $size . '" height="' . $size . '" fill="#fff"/>'
        . '<path d="' . $path . '" fill="#000"/></svg>';
}
