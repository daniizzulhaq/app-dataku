<?php
declare(strict_types=1);
// Hanya boleh dijalankan pada database pengujian baru, melalui CLI.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/includes/functions.php';
$name = getenv('DB_NAME') ?: '';
if (!preg_match('/^db_kendaraan_print_test_[a-z0-9]+$/D', $name)) {
    throw new RuntimeException('Nama database pengujian tidak aman.');
}
$action = $argv[1] ?? '';
if (in_array($action, ['create', 'drop'], true)) {
    $pdo = new PDO('mysql:host=' . (getenv('DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('DB_PORT') ?: '3306') . ';charset=utf8mb4', getenv('DB_USER') ?: 'root', getenv('DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    if ($action === 'create') {
        $pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $sql = file_get_contents(APP_ROOT . '/database/kendaraan.sql');
        $sql = preg_replace('/CREATE DATABASE IF NOT EXISTS db_kendaraan_print[^;]*;/', '', $sql);
        $sql = str_replace('USE db_kendaraan_print;', "USE `$name`;", $sql);
        $pdo->exec($sql);
    } else {
        $pdo->exec("DROP DATABASE `$name`");
    }
} elseif ($action === 'snapshot') {
    echo json_encode(['users' => db()->query('SELECT id, username, password FROM users')->fetchAll(), 'vehicles' => db()->query('SELECT * FROM kendaraan ORDER BY id')->fetchAll(), 'settings' => db()->query('SELECT * FROM print_settings')->fetchAll()], JSON_THROW_ON_ERROR);
} else {
    throw new InvalidArgumentException('Tindakan pengujian tidak dikenal.');
}
