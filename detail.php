<?php
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';
require_auth();
$vehicle = find_vehicle(positive_id());
$pageTitle = 'Detail kendaraan';
require __DIR__ . '/includes/header.php';
?>
<div class="page-heading"><div><div class="eyebrow">DETAIL KENDARAAN / #<?= (int) $vehicle['id'] ?></div><h1><?= e($vehicle['nomor_polisi']) ?><span class="heading-dot">.</span></h1><p><?= e($vehicle['merek'] . ' ' . $vehicle['tipe']) ?> · <?= e($vehicle['nama_pemilik']) ?></p></div><div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-secondary" href="edit.php?id=<?= $vehicle['id'] ?>"><i class="bi bi-pencil-square"></i> Edit data</a><a class="btn btn-primary" href="preview.php?id=<?= $vehicle['id'] ?>"><i class="bi bi-file-earmark-text"></i> Preview & Print</a></div></div>
<?php foreach (vehicle_fields() as $group): ?><section class="panel mb-4"><div class="panel-heading"><h2><i class="bi bi-<?= e($group['icon']) ?> me-2 text-primary"></i><?= e($group['title']) ?></h2></div><dl class="detail-grid"><?php foreach ($group['fields'] as $name => [$label, $type]): ?><div class="<?= $type === 'textarea' ? 'detail-wide' : '' ?>"><dt><?= e($label) ?></dt><dd><?= nl2br(e($type === 'date' ? format_date($vehicle[$name]) : ($vehicle[$name] ?? '—'))) ?></dd></div><?php endforeach; ?></dl></section><?php endforeach; ?>
<div class="detail-footer"><div><i class="bi bi-clock-history me-2"></i>Ditambahkan <?= e(format_date($vehicle['created_at'])) ?> · Diperbarui <?= e(format_date($vehicle['updated_at'])) ?></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="index.php">Kembali</a><a class="btn btn-outline-primary" href="print.php?id=<?= $vehicle['id'] ?>"><i class="bi bi-printer"></i> Cetak ulang</a><form method="post" action="hapus.php" data-confirm="Hapus data kendaraan ini? Data tidak dapat dikembalikan."><?= csrf_field() ?><input type="hidden" name="id" value="<?= $vehicle['id'] ?>"><button class="btn btn-outline-danger"><i class="bi bi-trash3"></i> Hapus</button></form></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
