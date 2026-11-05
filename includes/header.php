<?php
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$vehiclePages = ['tambah.php', 'simpan.php', 'edit.php', 'update.php', 'detail.php', 'preview.php'];
$printPages = ['print-settings.php', 'print-test.php'];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($pageTitle ?? 'Dashboard') ?> · Kendara</title>
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendor/icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <?php if (in_array($currentPage, ['tambah.php', 'edit.php', 'simpan.php', 'update.php'], true)): ?>
        <link rel="stylesheet" href="assets/css/vehicle-form.css?v=<?= filemtime(APP_ROOT . '/assets/css/vehicle-form.css') ?>">
    <?php endif; ?>
    <?php if (!empty($hasDocument)): ?>
        <link rel="stylesheet" href="assets/css/preview.css?v=<?= filemtime(APP_ROOT . '/assets/css/preview.css') ?>">
        <link rel="stylesheet" href="assets/css/print.css?v=<?= filemtime(APP_ROOT . '/assets/css/print.css') ?>">
        <?= print_css($printPosition ?? 'middle') ?>
    <?php endif; ?>
    <script defer src="assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script defer src="assets/js/app.js?v=<?= filemtime(APP_ROOT . '/assets/js/app.js') ?>"></script>
</head>
<body>
<a class="skip-link" href="#main-content">Lewati navigasi</a>
<div class="sidebar-backdrop" id="sidebar-backdrop"></div>
<aside class="sidebar" id="sidebar" aria-label="Menu utama">
    <a class="brand" href="index.php"><span class="brand-mark"><i class="bi bi-intersect" aria-hidden="true"></i></span><span>kendara<span class="brand-dot">.</span></span></a>
    <div class="workspace-badge"><span class="status-dot"></span> Administrasi kendaraan <span class="ms-auto">v1.0</span></div>
    <div class="nav-caption">WORKSPACE</div>
    <nav class="side-nav">
        <a class="<?= $currentPage === 'index.php' ? 'active' : '' ?>" href="index.php"><i class="bi bi-grid-1x2"></i> Dashboard <i class="bi bi-chevron-right nav-arrow"></i></a>
        <a class="<?= in_array($currentPage, $vehiclePages, true) ? 'active' : '' ?>" href="tambah.php"><i class="bi bi-car-front"></i> Data kendaraan <span class="nav-plus">+</span></a>
        <a class="<?= in_array($currentPage, $printPages, true) ? 'active' : '' ?>" href="print-settings.php"><i class="bi bi-printer"></i> Posisi cetak</a>
    </nav>
    <div class="sidebar-bottom">
        <div class="internal-note"><i class="bi bi-shield-check"></i><strong>Ruang kerja internal</strong><p>Data tersimpan untuk kebutuhan administrasi. Seluruh cetakan berstatus draft.</p></div>
        <div class="user-block"><span class="avatar"><?= e(strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1))) ?></span><div><strong><?= e($_SESSION['user_name'] ?? 'Admin') ?></strong><small>Administrator</small></div><form action="logout.php" method="post"><?= csrf_field() ?><button class="icon-button" title="Keluar" aria-label="Keluar"><i class="bi bi-box-arrow-right"></i></button></form></div>
    </div>
</aside>
<div class="app-shell">
    <header class="topbar"><div class="d-flex align-items-center gap-3"><button class="icon-button mobile-menu" id="menu-toggle" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false"><i class="bi bi-list"></i></button><span class="breadcrumb-label">Workspace <i class="bi bi-chevron-right"></i> <strong><?= e($pageTitle ?? 'Dashboard') ?></strong></span></div><span class="topbar-date"><i class="bi bi-calendar3"></i> <?= e(format_date(date('Y-m-d'))) ?></span></header>
    <main class="main-content" id="main-content">
    <?php if ($notification = $_SESSION['flash'] ?? null): unset($_SESSION['flash']); ?>
        <div class="toast-container position-fixed top-0 end-0 p-3"><div class="toast app-toast show" role="status" aria-live="polite"><div class="toast-body d-flex gap-3 align-items-center"><i class="bi bi-check-circle text-<?= e($notification['type']) ?>"></i><span><?= e($notification['message']) ?></span><button class="btn-close ms-auto" type="button" data-bs-dismiss="toast" aria-label="Tutup notifikasi"></button></div></div></div>
    <?php endif; ?>
