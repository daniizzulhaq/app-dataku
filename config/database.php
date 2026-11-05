<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';

function db(): PDO
{
    static $connection;
    if ($connection instanceof PDO) {
        return $connection;
    }
    $settings = [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'db_kendaraan_print',
        'user' => getenv('DB_USER') !== false ? getenv('DB_USER') : 'root',
        'password' => getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '',
    ];
    // File ini opsional, tidak dilacak Git, dan tidak dapat diakses melalui HTTP.
    if (is_file(__DIR__ . '/database.local.php')) {
        $settings = array_replace($settings, require __DIR__ . '/database.local.php');
    }
    $connection = new PDO(
        "mysql:host={$settings['host']};port={$settings['port']};dbname={$settings['name']};charset=utf8mb4",
        $settings['user'], $settings['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false]
    );
    $connection->exec("SET time_zone = '+07:00'");
    return $connection;
}
