<?php
// Satu template untuk preview dan print. Label hanya dibaca pembaca layar.
$leftFields = [
    'merek' => 'Merek', 'tipe' => 'Tipe', 'jenis' => 'Jenis', 'model' => 'Model',
    '_tahun' => 'Tahun pembuatan / perakitan', 'isi_silinder' => 'Isi silinder (cc)',
    'nomor_rangka' => 'Nomor rangka', 'nomor_mesin' => 'Nomor mesin',
];
$middleFields = [
    'warna' => 'Warna', '_spacer' => '', 'bahan_bakar' => 'Bahan bakar',
    'warna_tnkb' => 'Warna TNKB', 'tahun_registrasi' => 'Tahun registrasi',
    'nomor_bpkb' => 'Nomor BPKB', 'nomor_urut' => 'Nomor urut pendaftaran',
];
$documentValue = static function (string $field) use ($vehicle): string {
    if ($field === 'alamat') {
        return trim(preg_replace('/\s+/u', ' ', (string) ($vehicle[$field] ?? '')));
    }
    if ($field === '_tahun') {
        $year = (string) $vehicle['tahun_pembuatan'];
        $assembly = (string) ($vehicle['tahun_perakitan'] ?? '');
        return $assembly !== '' && $assembly !== $year ? $year . ' / ' . $assembly : $year;
    }
    if (in_array($field, ['tanggal_penerbitan', 'berlaku_sampai'], true)) {
        if (empty($vehicle[$field])) return '';
        return $field === 'berlaku_sampai'
            ? (new DateTimeImmutable($vehicle[$field]))->format('d F Y')
            : format_date($vehicle[$field]);
    }
    return (string) ($vehicle[$field] ?? '');
};
$renderValue = static function (string $field, string $label, string $class = '') use ($documentValue): string {
    return '<div class="document-value ' . e($class) . '" data-field="' . e($field) . '">'
        . '<dt class="document-field-label">' . e($label) . '</dt>'
        . '<dd>' . nl2br(e($documentValue($field))) . '</dd></div>';
};
?>
<article class="vehicle-document" aria-label="Lembar data kendaraan <?= e($vehicle['nomor_polisi']) ?>">
    
    <div class="document-content">
        <div class="document-columns">
            <dl class="document-owner" aria-label="Data pemilik">
                <?= $renderValue('nomor_polisi', 'Nomor polisi', 'document-plate') ?>
                <?= $renderValue('nik', 'NIK', 'document-nik') ?>
                <?= $renderValue('nama_pemilik', 'Nama pemilik', 'document-owner-name') ?>
                <?= $renderValue('alamat', 'Alamat', 'document-address') ?>
            </dl>
            <div class="document-approval" aria-label="Pengesahan administratif internal">
                <?php require APP_ROOT . '/templates/pengesahan.php'; ?>
            </div>
            <section class="document-column document-primary" aria-label="Data kendaraan">
                <dl class="document-fields">
                    <?php foreach ($leftFields as $field => $label): ?>
                        <?= $renderValue($field, $label) ?>
                    <?php endforeach; ?>
                </dl>
            </section>
            <section class="document-column document-secondary" aria-label="Identitas tambahan">
                <dl class="document-fields">
                    <?php foreach ($middleFields as $field => $label): ?>
                        <?php if ($field === '_spacer'): ?>
                            <div class="document-value document-spacer" aria-hidden="true"></div>
                        <?php else: ?>
                            <?= $renderValue($field, $label) ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </dl>
            </section>
            <section class="document-column document-administration" aria-label="Data administrasi">
                <div class="document-admin-summary">
                    <?php require APP_ROOT . '/templates/referensi-internal.php'; ?>
                </div>
                <dl class="document-fields document-admin-bottom">
                    <?= $renderValue('kode_lokasi', 'Kode lokasi') ?>
                    <?= $renderValue('berlaku_sampai', 'Berlaku sampai', 'document-expiry') ?>
                </dl>
            </section>
        </div>

    </div>
    
</article>
