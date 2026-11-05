<?php
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';
require_auth();
$pageTitle = 'Tambah kendaraan';
$vehicle = ['tanggal_penerbitan' => date('Y-m-d')];
$errors = [];
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/vehicle-form.php';
require __DIR__ . '/includes/footer.php';
