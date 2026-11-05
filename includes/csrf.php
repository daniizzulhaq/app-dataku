<?php
declare(strict_types=1);

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function verify_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        abort_request(405, 'Tindakan ini harus menggunakan POST.');
    }
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        abort_request(419, 'Sesi formulir tidak valid. Muat ulang halaman dan coba kembali.');
    }
}
