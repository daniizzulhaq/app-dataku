<?php
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';
require_auth();
if ($_SERVER['REQUEST_METHOD'] === 'POST') verify_csrf();
$printPosition = selected_print_position();
if ($_SERVER['REQUEST_METHOD'] === 'POST') redirect('print-settings.php?position=' . $printPosition);
$pageTitle = 'Posisi cetak';
require __DIR__ . '/includes/header.php';
?>
<div class="page-heading"><div><div class="eyebrow">FORMAT CETAK TETAP</div><h1>Posisi cetak<span class="heading-dot">.</span></h1><p>Kertas landscape dengan isi STNK portrait, sesuai format yang sudah ditetapkan.</p></div></div>
<section class="panel">
    <div class="panel-heading"><div><h2>Satu STNK per cetak</h2><p>Pilih kolom kiri, tengah, atau kanan. Posisi pilihan diingat selama sesi.</p></div><i class="bi bi-printer text-muted"></i></div>
    <?php require __DIR__ . '/includes/print-options.php'; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
