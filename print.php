<?php
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';
require_auth();
// Selalu ambil ulang data; tidak menggunakan salinan HTML dari preview.
$vehicle = find_vehicle(positive_id());
$printPosition = selected_print_position();
$settings = print_settings();

?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Cetak · <?= e($vehicle['nomor_polisi']) ?></title><link rel="icon" href="assets/favicon.svg"><link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css"><link rel="stylesheet" href="assets/vendor/icons/bootstrap-icons.min.css"><link rel="stylesheet" href="assets/css/style.css"><link rel="stylesheet" href="assets/css/preview.css?v=<?= filemtime(APP_ROOT . '/assets/css/preview.css') ?>"><link rel="stylesheet" href="assets/css/print.css"><?= print_css($printPosition) ?><script defer src="assets/js/app.js?v=<?= filemtime(APP_ROOT . '/assets/js/app.js') ?>"></script></head><body class="print-page">
<section class="print-controls no-print"><div class="control-row"><div><div class="eyebrow">CETAK LEMBAR ADMINISTRASI INTERNAL</div><h1><?= e($vehicle['nomor_polisi']) ?> <span class="text-muted">/ <?= e($vehicle['nama_pemilik']) ?></span></h1></div><button class="btn btn-primary" type="button" data-print><i class="bi bi-printer"></i> Print / Cetak ulang</button></div><p class="mt-3 mb-2">Pilih <?= e($settings['paper_width_mm']) ?> × <?= e($settings['paper_height_mm']) ?> mm, orientasi <strong>landscape</strong>, skala <strong>100% / Actual size</strong>, margin <strong>None / 0</strong> dan nonaktifkan header/footer browser. Setiap kali cetak berisi satu STNK pada kolom yang dipilih.</p><a href="preview.php?<?= e(http_build_query(['id' => $vehicle['id'], 'position' => $printPosition])) ?>">← Kembali ke preview</a>

<?php require __DIR__ . '/includes/print-options.php'; ?>
<p class="fit-message" id="fit-message" role="status" hidden></p><noscript><p>Aktifkan JavaScript untuk pemeriksaan ukuran konten dan tombol Print.</p></noscript></section>
<div class="paper-scroll"><div data-print-position="<?= e($printPosition) ?>" class="paper-sheet"><div class="paper-slot-guide no-print" aria-hidden="true"></div><div class="document-position"><?php require __DIR__ . '/templates/kendaraan.php'; ?></div></div></div>
<div class="print-failure">Konten melebihi area cetak. Periksa panjang data sebelum mencetak.</div>
</body></html>
