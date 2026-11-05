<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';

function require_auth(): void
{
    if (empty($_SESSION['user_id'])) {
        redirect('login.php');
    }
    if (time() - (int) ($_SESSION['last_activity'] ?? 0) > 1800) {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('Sesi berakhir setelah 30 menit tanpa aktivitas. Silakan masuk kembali.', 'warning');
        redirect('login.php');
    }
    $statement = db()->prepare('SELECT id, name, username FROM users WHERE id = ?');
    $statement->execute([$_SESSION['user_id']]);
    if (!$user = $statement->fetch()) {
        $_SESSION = [];
        redirect('login.php');
    }
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['last_activity'] = time();
}

function login_rate_keys(string $username): array
{
    return [hash('sha256', 'ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')),
        hash('sha256', 'user:' . strtolower($username))];
}

function login_is_limited(array $keys): bool
{
    $query = db()->prepare('SELECT rate_key, attempts FROM login_attempts WHERE rate_key IN (?, ?) AND window_started > DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
    $query->execute($keys);
    foreach ($query as $row) {
        if ((int) $row['attempts'] >= ($row['rate_key'] === $keys[0] ? 20 : 5)) {
            return true;
        }
    }
    return false;
}

function record_login_attempt(array $keys): void
{
    $query = db()->prepare('INSERT INTO login_attempts (rate_key, attempts, window_started) VALUES (?, 1, NOW())
        ON DUPLICATE KEY UPDATE attempts = IF(window_started <= DATE_SUB(NOW(), INTERVAL 15 MINUTE), 1, attempts + 1),
        window_started = IF(window_started <= DATE_SUB(NOW(), INTERVAL 15 MINUTE), NOW(), window_started)');
    foreach ($keys as $key) {
        $query->execute([$key]);
    }
    db()->exec('DELETE FROM login_attempts WHERE window_started < DATE_SUB(NOW(), INTERVAL 1 DAY)');
}
