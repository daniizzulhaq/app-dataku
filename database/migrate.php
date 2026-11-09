<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/config/database.php';
$connection = db();
$locked = (int) $connection->query("SELECT GET_LOCK('kendara_schema_migration', 10)")->fetchColumn();
if ($locked !== 1) throw new RuntimeException('Migrasi sedang diproses.');
try {
    $query = $connection->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $columns = [
        'teks_atas_qr' => "VARCHAR(60) NULL DEFAULT 'TELITI ULANG' AFTER nomor_referensi",
        'pengesahan_internal' => 'VARCHAR(180) NULL AFTER nomor_referensi',
        'jabatan_pemeriksa' => 'VARCHAR(100) NULL AFTER pengesahan_internal',
        'wilayah' => 'VARCHAR(60) NULL AFTER jabatan_pemeriksa',
        'tanggal_wilayah' => 'DATE NULL AFTER wilayah',
        'wilayah_pengesahan' => 'VARCHAR(60) NULL AFTER jabatan_pemeriksa',
        'nrp_pemeriksa' => 'VARCHAR(30) NULL AFTER wilayah_pengesahan',
    ];
    foreach ($columns as $column => $definition) {
        $query->execute(['kendaraan', $column]);
        if ((int) $query->fetchColumn() === 0) {
            $connection->exec('ALTER TABLE kendaraan ADD COLUMN ' . $column . ' ' . $definition);
            fwrite(STDOUT, "Kolom $column berhasil ditambahkan. Data lama dipertahankan.\n");
        }
    }
    $nikNullable = $connection->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kendaraan' AND COLUMN_NAME = 'nik'")->fetchColumn();
    if ($nikNullable === 'NO') {
        $connection->exec('ALTER TABLE kendaraan MODIFY COLUMN nik VARCHAR(16) NULL');
        fwrite(STDOUT, "NIK sekarang opsional. Data lama dipertahankan.\n");
    }
    fwrite(STDOUT, "Database sudah menggunakan skema terbaru.\n");
} finally {
    $connection->query("SELECT RELEASE_LOCK('kendara_schema_migration')");
}
