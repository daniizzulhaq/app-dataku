<?php
$isEdit = isset($vehicle['id']);
$errors = $errors ?? [];
$vehicle += ['teks_atas_qr' => 'TELITI ULANG'];
$fieldDefinitions = [];
foreach (vehicle_fields() as $group) {
    $fieldDefinitions = array_merge($fieldDefinitions, $group['fields']);
}
$renderInput = static function (string $name, string $positionClass = '') use ($fieldDefinitions, $vehicle, $errors): void {
    [$label, $type, $max, $required, $placeholder] = $fieldDefinitions[$name];
    $value = $vehicle[$name] ?? '';
    $invalid = isset($errors[$name]);
    require APP_ROOT . '/includes/vehicle-input.php';
};
$backLink = $isEdit ? 'detail.php?id=' . (int) $vehicle['id'] : 'index.php';
?>
<div class="page-heading">
    <div>
        <div class="eyebrow">PENCATATAN KENDARAAN</div>
        <h1><?= $isEdit ? 'Edit isian kendaraan' : 'Isi data kendaraan' ?><span class="heading-dot">.</span></h1>
        <p>Isi langsung pada kolom setelah tanda titik dua, lalu simpan untuk melihat hasil cetaknya. Data otomatis menggunakan huruf kapital.</p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e($backLink) ?>"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>
<div class="workflow-strip">
    <span class="active"><b>1</b> Isi template</span><i class="bi bi-chevron-right"></i>
    <span><b>2</b> Preview</span><i class="bi bi-chevron-right"></i>
    <span><b>3</b> Cetak isian</span>
</div>
<?php if ($errors): ?>
    <div class="alert alert-danger" role="alert"><strong>Data belum dapat disimpan.</strong><ul class="mb-0 mt-2">
        <?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?>
    </ul></div>
<?php endif; ?>
<form action="<?= $isEdit ? 'update.php' : 'simpan.php' ?>" method="post" class="vehicle-form template-form" data-vehicle-form>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $vehicle['id'] ?>">
        <input type="hidden" name="version" value="<?= e($vehicle['updated_at']) ?>">
    <?php endif; ?>
    <section class="panel template-editor-panel" aria-label="Formulir isian kendaraan">
        <div class="template-editor-toolbar">
            <span><i class="bi bi-pencil-square" aria-hidden="true"></i> Formulir isian langsung</span>
            <span><span class="required-star">*</span> Wajib diisi</span>
        </div>
        <div class="template-editor-scroll">
            <div class="template-editor-sheet">
                <header class="template-editor-header">
                    <span>ADMINISTRASI INTERNAL</span>
                    <p>Klik kolom isian setelah tanda <strong>:</strong> untuk mengetik.</p>
                </header>
                <div class="template-editor-grid">
                    <div class="template-owner-inputs" role="group" aria-label="Identitas pemilik">
                        <?php $renderInput('nomor_polisi', 'template-plate-input'); ?>
                        <?php $renderInput('nik', 'template-nik-input'); ?>
                        <?php $renderInput('nama_pemilik', 'template-name-input'); ?>
                        <?php $renderInput('alamat', 'template-address-input'); ?>
                    </div>
                    <fieldset class="template-approval-input" aria-label="Pengesahan internal">
                        <legend>AREA PENGESAHAN INTERNAL</legend>
                        <?php $renderInput('wilayah_pengesahan'); ?>
                        <?php $renderInput('pengesahan_internal'); ?>
                        <?php $renderInput('jabatan_pemeriksa'); ?>
                        <?php $renderInput('nrp_pemeriksa'); ?>
                        <p class="template-approval-hint">Wilayah tampil di atas ruang kosong tanda tangan dan stempel. Nama bergaris bawah, diikuti jabatan dan NRP pada baris berikutnya. Semua kolom opsional.</p>
                    </fieldset>
                    <fieldset class="template-input-section template-vehicle-inputs">
                        <legend>Spesifikasi kendaraan</legend>
                        <?php foreach (['merek', 'tipe', 'jenis', 'model', 'tahun_pembuatan', 'tahun_perakitan', 'isi_silinder', 'nomor_rangka', 'nomor_mesin'] as $name): ?>
                            <?php $renderInput($name); ?>
                        <?php endforeach; ?>
                    </fieldset>
                    <fieldset class="template-input-section template-additional-inputs">
                        <legend>Identitas tambahan</legend>
                        <?php foreach (['warna', 'bahan_bakar', 'warna_tnkb', 'tahun_registrasi', 'nomor_bpkb', 'nomor_urut'] as $name): ?>
                            <?php $renderInput($name); ?>
                        <?php endforeach; ?>
                    </fieldset>
                    <fieldset class="template-input-section template-admin-inputs">
                        <legend>Administrasi</legend>
                        <?php foreach (['kode_lokasi', 'tanggal_penerbitan', 'berlaku_sampai', 'nomor_referensi', 'teks_atas_qr'] as $name): ?>
                            <?php $renderInput($name); ?>
                        <?php endforeach; ?>
                        <p class="template-print-note"><i class="bi bi-info-circle" aria-hidden="true"></i> Label dan tanda titik dua hanya panduan pengisian. Hasil cetak menampilkan nilai data.</p>
                    </fieldset>
                </div>
            </div>
        </div>
    </section>
    <section class="panel template-notes-panel" aria-label="Catatan admin">
        <?php $renderInput('catatan'); ?>
        <p class="template-notes-help">Catatan disimpan untuk administrasi dan tidak ikut dicetak.</p>
    </section>
    <div class="form-actions">
        <span><i class="bi bi-shield-check"></i> Data hanya dapat diakses administrator.</span>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="<?= e($backLink) ?>">Batal</a>
            <button class="btn btn-outline-primary" type="submit" name="action" value="save"><i class="bi bi-check2"></i> Simpan</button>
            <button class="btn btn-primary" type="submit" name="action" value="preview"><i class="bi bi-file-earmark-check"></i> Simpan & Preview</button>
        </div>
    </div>
</form>
