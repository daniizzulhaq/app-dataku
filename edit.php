<?php
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';
require_auth();
$vehicle = find_vehicle(positive_id());
$pageTitle = 'Edit kendaraan';
$errors = [];
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/vehicle-form.php';
require __DIR__ . '/includes/footer.php';
