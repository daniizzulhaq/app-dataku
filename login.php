<?php
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';
if (!empty($_SESSION['user_id'])) redirect('index.php');
$error = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = strtolower(post_string('username'));
    $password = post_string('password');
    $keys = login_rate_keys($username);
    if (login_is_limited($keys)) {
        http_response_code(429);
        header('Retry-After: 900');
        $error = 'Terlalu banyak percobaan masuk. Tunggu hingga 15 menit sebelum mencoba kembali.';
    } else {
        record_login_attempt($keys);
        $query = db()->prepare('SELECT * FROM users WHERE username = ?');
        $query->execute([$username]);
        $user = $query->fetch();
        $hash = $user['password'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        if (strlen($password) <= 72 && password_verify($password, $hash) && $user) {
            session_regenerate_id(true);
            $_SESSION = ['user_id' => (int) $user['id'], 'user_name' => $user['name'], 'last_activity' => time()];
            $reset = db()->prepare('DELETE FROM login_attempts WHERE rate_key = ?');
            $reset->execute([$keys[1]]);
            if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            }
            redirect('index.php');
        }
        $error = 'Username atau password tidak sesuai.';
        http_response_code(422);
    }
}
$hasAdmin = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
$pageTitle = 'Masuk ke workspace';
require __DIR__ . '/includes/auth-header.php';
?>
<div class="auth-card"><div class="eyebrow">SELAMAT DATANG KEMBALI</div><h1>Masuk ke workspace</h1><p class="text-muted mb-4">Kelola data kendaraan dalam satu tempat.</p>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if (isset($_SESSION['flash'])): ?><div class="alert alert-info" role="status"><?= e($_SESSION['flash']['message']) ?></div><?php unset($_SESSION['flash']); endif; ?>
<form action="login.php" method="post"><?= csrf_field() ?><div class="mb-3"><label class="form-label" for="username">Username</label><input id="username" name="username" class="form-control" autocomplete="username" maxlength="50" required autofocus value="<?= e($username) ?>" placeholder="Username administrator"></div><div class="mb-4"><label class="form-label" for="password">Password</label><div class="password-field"><input id="password" type="password" name="password" class="form-control" autocomplete="current-password" maxlength="72" required placeholder="Masukkan password"><button class="icon-button password-toggle" type="button" aria-label="Tampilkan password" data-password="password"><i class="bi bi-eye"></i></button></div></div><button class="btn btn-primary w-100" type="submit">Masuk <i class="bi bi-arrow-right ms-2"></i></button></form>
<?php if (!$hasAdmin): ?><div class="setup-hint"><i class="bi bi-info-circle"></i><div>Belum ada administrator. Jalankan <code>php setup.php</code> dari folder proyek, lalu <a href="setup.php">buka setup awal</a>.</div></div><?php endif; ?>
<div class="auth-disclaimer"><i class="bi bi-lock"></i> Akses khusus administrator yang berwenang.</div></div>
<?php require __DIR__ . '/includes/auth-footer.php'; ?>
