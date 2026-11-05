<?php
// Router untuk server pengembangan PHP. Apache menggunakan .htaccess.
declare(strict_types=1);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (str_contains($path, '\\') || str_contains($path, '..') || preg_match('~/(?:config|includes|templates|database|storage|tests|\.[^/]*)(?:/|$)~i', $path)) {
    http_response_code(404);
    exit('Tidak ditemukan.');
}
$file = __DIR__ . $path;
if (is_file($file) && (str_starts_with($path, '/assets/') || (preg_match('~^/[a-z-]+\.php$~', $path) && basename($path) !== 'router.php'))) {
    return false;
}
if ($path === '/') {
    require __DIR__ . '/index.php';
    return true;
}
http_response_code(404);
echo 'Tidak ditemukan.';
