<?php
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';
require_auth();
$vehicle = find_vehicle(positive_id());
$printPosition = selected_print_position();
$settings = print_settings();

$hasDocument = true;
$pageTitle = 'Preview dokumen';
require __DIR__ . '/includes/header.php';
?>
<div class="page-heading"><div><div class="eyebrow">PERIKSA SEBELUM MENCETAK</div><h1>Preview dokumen<span class="heading-dot">.</span></h1><p><?= e($vehicle['nomor_polisi']) ?> · <?= e($vehicle['nama_pemilik']) ?></p></div><div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-secondary" href="edit.php?id=<?= $vehicle['id'] ?>"><i class="bi bi-pencil-square"></i> Edit data</a><a class="btn btn-primary" href="print.php?<?= e(http_build_query(['id' => $vehicle['id'], 'position' => $printPosition])) ?>"><i class="bi bi-printer"></i> Lanjut cetak</a></div></div>
<div class="document-meta"><span><i class="bi bi-file-earmark-text"></i> LEMBAR DATA KENDARAAN</span><span><i class="bi bi-lock"></i> Dokumen internal · Draft</span></div>
<section class="panel">
<?php require __DIR__ . '/includes/print-options.php'; ?>
<p class="fit-message no-print" id="fit-message" role="status" hidden></p>
<div class="preview-workspace"><div data-print-position="<?= e($printPosition) ?>" class="paper-sheet"><div class="paper-slot-guide no-print" aria-hidden="true"></div><div class="document-position"><?php require __DIR__ . '/templates/kendaraan.php'; ?></div></div></div>
<div class="preview-status"><span><i class="bi bi-aspect-ratio"></i> Landscape · <?= e($settings['paper_width_mm']) ?> × <?= e($settings['paper_height_mm']) ?> mm</span><span><i class="bi bi-check2-circle"></i> Data terbaru dari database</span></div>
<?php if ($vehicle['catatan']): ?><div class="preview-notes no-print"><h2>Catatan admin · Tidak dicetak</h2><p><?= nl2br(e($vehicle['catatan'])) ?></p></div><?php endif; ?>
</section>
<div class="print-help"><strong><i class="bi bi-info-circle me-1"></i> Sebelum mencetak</strong><br>Pilih kertas <?= e($settings['paper_width_mm']) ?> × <?= e($settings['paper_height_mm']) ?> mm, landscape, skala 100% / Actual size, margin 0, dan matikan header/footer browser.</div>
<div class="print-failure">Konten melebihi area cetak. Periksa panjang data sebelum mencetak.</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
