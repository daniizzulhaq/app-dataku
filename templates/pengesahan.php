<?php
// Komponen khusus pemeriksaan administratif internal.
$approvalText = trim((string) ($vehicle['pengesahan_internal'] ?? ''));
$approvalRole = trim((string) ($vehicle['jabatan_pemeriksa'] ?? ''));
$approvalRegion = trim((string) ($vehicle['wilayah'] ?? ''));
$approvalProvince = trim((string) ($vehicle['wilayah_pengesahan'] ?? ''));
$approvalDate = !empty($vehicle['tanggal_wilayah'])
    ? (new DateTimeImmutable($vehicle['tanggal_wilayah']))->format('d-F-Y') : '';
$approvalNrp = trim((string) ($vehicle['nrp_pemeriksa'] ?? ''));
?>
<div class="internal-approval">
    <div class="internal-approval-regions">
        <div class="internal-approval-region"><?= e($approvalRegion) ?><?= $approvalRegion !== '' && $approvalDate !== '' ? ',' : '' ?></div>
        <?php if ($approvalDate !== ''): ?><div class="internal-approval-date"><?= e($approvalDate) ?></div><?php endif; ?>
        <div class="internal-approval-province"><?= e($approvalProvince) ?></div>
    </div>
    <div class="internal-approval-signature-space">
        <img class="internal-approval-signature" src="assets/images/pengesahan-contoh.jpg" alt="Contoh tanda tangan dan stempel dari gambar referensi">
    </div>
    <?php if ($approvalText !== ''): ?>
        <div class="internal-approval-content"><?= nl2br(e($approvalText)) ?></div>
    <?php else: ?>
        <strong class="internal-approval-content">BELUM DIISI</strong>
    <?php endif; ?>
    <?php if ($approvalRole !== '' || $approvalNrp !== ''): ?>
        <div class="internal-approval-role">
            <?php if ($approvalRole !== ''): ?><span class="internal-approval-job"><?= e($approvalRole) ?></span><?php endif; ?>
            <?php if ($approvalNrp !== ''): ?><span class="internal-approval-nrp">NRP <?= e($approvalNrp) ?></span><?php endif; ?>
        </div>
    <?php endif; ?>
</div>
