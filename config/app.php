<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');
define('APP_ROOT', dirname(__DIR__));
define('APP_STORAGE', getenv('KENDARA_STORAGE') ?: APP_ROOT . '/storage');
define('APP_NAME', 'Kendara');
ini_set('display_errors', '0');
error_reporting(E_ALL);

set_exception_handler(function (Throwable $error): void {
    error_log('[Kendara] ' . $error);
    http_response_code(503);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Aplikasi belum dapat dijalankan. Periksa layanan MySQL, impor database, dan konfigurasi lokal.\n");
        exit(1);
    }
    echo '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Layanan belum tersedia</title><body style="font-family:system-ui;max-width:600px;margin:12vh auto;padding:24px"><h1>Layanan belum tersedia</h1><p>Periksa layanan MySQL, impor database, dan konfigurasi lokal. Rincian teknis dicatat di log server.</p><a href="login.php">Coba kembali</a></body></html>';
    exit;
});

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, private, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self' https://cdn.jsdelivr.net; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('kendara_session');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}
