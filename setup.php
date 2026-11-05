<?php
declare(strict_types=1);
require __DIR__ . '/includes/functions.php';
$tokenPath = APP_STORAGE . '/setup-token.json';
if (PHP_SAPI === 'cli') {
    if ((int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 0) {
        fwrite(STDOUT, "Setup terkunci: administrator sudah dibuat.\n");
        exit(1);
    }
    $token = bin2hex(random_bytes(32));
    $payload = json_encode(['hash' => hash('sha256', $token), 'expires' => time() + 1800], JSON_THROW_ON_ERROR);
    if (file_put_contents($tokenPath, $payload, LOCK_EX) === false) {
        throw new RuntimeException('Tidak dapat membuat token setup.');
    }
    fwrite(STDOUT, "Buka setup.php melalui localhost, lalu masukkan token berikut (berlaku 30 menit):\n" . $token . "\nToken tidak boleh dibagikan. Setup terkunci setelah admin pertama dibuat.\n");
    exit;
}
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    abort_request(403, 'Setup awal hanya tersedia dari komputer lokal.');
}
if ((int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
    abort_request(403, 'Setup awal sudah terkunci karena akun admin telah dibuat.');
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stored = is_file($tokenPath) ? json_decode((string) file_get_contents($tokenPath), true) : null;
    if (!$stored || ($stored['expires'] ?? 0) < time() || !hash_equals($stored['hash'], hash('sha256', post_string('setup_token')))) {
        $errors[] = 'Token setup tidak valid atau sudah kedaluwarsa. Jalankan ulang php setup.php.';
    }
    $name = post_string('name');
    $username = strtolower(post_string('username'));
    $password = post_string('password');
    if ($name === '' || text_length($name) > 100) $errors[] = 'Nama wajib diisi, maksimal 100 karakter.';
    if (!preg_match('/^[a-z0-9_.-]{3,50}$/D', $username)) $errors[] = 'Username harus 3–50 karakter: huruf, angka, titik, garis bawah atau tanda minus.';
    if (strlen($password) < 12 || strlen($password) > 72) $errors[] = 'Gunakan password sepanjang 12–72 byte.';
    if ($password !== post_string('password_confirm')) $errors[] = 'Konfirmasi password tidak sesuai.';
    if (!$errors) {
        // Lock database mencegah dua akun pertama dibuat secara bersamaan.
        $locked = (int) db()->query("SELECT GET_LOCK('kendara_initial_setup', 5)")->fetchColumn();
        if ($locked !== 1) {
            $errors[] = 'Setup sedang diproses. Coba kembali.';
        } else {
            try {
                if ((int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 0) {
                    abort_request(403, 'Administrator sudah dibuat. Setup telah terkunci.');
                }
                db()->prepare('INSERT INTO users (name, username, password) VALUES (?, ?, ?)')->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT)]);
                if (is_file($tokenPath)) unlink($tokenPath);
            } finally {
                db()->query("SELECT RELEASE_LOCK('kendara_initial_setup')");
            }
            session_regenerate_id(true);
            flash('Administrator berhasil dibuat. Silakan masuk dengan akun Anda.');
            redirect('login.php');
        }
    }
    if ($errors) http_response_code(422);
}
$pageTitle = 'Setup admin pertama';
require __DIR__ . '/includes/auth-header.php';
?>
<div class="auth-card"><div class="eyebrow">MULAI DARI SINI</div><h1>Siapkan workspace</h1><p class="text-muted">Buat administrator pertama. Dapatkan token dengan menjalankan <code>php setup.php</code> di terminal lokal.</p>
<?php if ($errors): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="setup.php"><?= csrf_field() ?><div class="mb-3"><label class="form-label" for="setup_token">Token setup sekali pakai</label><input class="form-control" id="setup_token" name="setup_token" required maxlength="64" autocomplete="off"></div><div class="mb-3"><label class="form-label" for="name">Nama administrator</label><input class="form-control" id="name" name="name" required maxlength="100" autocomplete="name" value="<?= e(post_string('name')) ?>"></div><div class="mb-3"><label class="form-label" for="username">Username</label><input class="form-control" id="username" name="username" required minlength="3" maxlength="50" pattern="[a-zA-Z0-9_.\-]{3,50}" autocomplete="username" value="<?= e(post_string('username')) ?>"></div><div class="row"><div class="col-sm-6 mb-3"><label class="form-label" for="password">Password</label><input type="password" class="form-control" id="password" name="password" required minlength="12" maxlength="72" autocomplete="new-password"></div><div class="col-sm-6 mb-3"><label class="form-label" for="password_confirm">Konfirmasi password</label><input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="12" maxlength="72" autocomplete="new-password"></div></div><small class="text-muted d-block mb-3">Gunakan password unik, minimal 12 karakter (maksimal 72 byte).</small><button class="btn btn-primary w-100">Buat administrator <i class="bi bi-arrow-right ms-2"></i></button></form><a class="d-inline-block mt-3" href="login.php">Kembali ke login</a></div>
<?php require __DIR__ . '/includes/auth-footer.php'; ?>
