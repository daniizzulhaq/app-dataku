<?php
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';
require_auth();
verify_csrf();
$id = positive_id();
$vehicle = find_vehicle($id);
db()->prepare('DELETE FROM kendaraan WHERE id = ?')->execute([$id]);
flash('Data kendaraan ' . $vehicle['nomor_polisi'] . ' berhasil dihapus.');
redirect('index.php');
