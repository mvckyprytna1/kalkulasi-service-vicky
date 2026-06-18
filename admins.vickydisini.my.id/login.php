<?php
require_once __DIR__ . '/lib/bootstrap.php';

if (is_logged_in()) {
    redirect('calculator.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Refresh halaman lalu coba lagi.';
    } else {
        $username = clean_text($_POST['username'] ?? '', 80);
        $password = (string)($_POST['password'] ?? '');

        try {
            $stmt = db()->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int)$admin['id'];
                redirect('calculator.php');
            }
            $error = 'Username atau password salah.';
        } catch (Throwable $e) {
            $error = 'Gagal konek database. Cek config.php dan pastikan install.php sudah dijalankan.';
        }
    }
}
$config = app_config();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - <?= e($config['app']['name']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">
    <main class="auth-card">
        <div class="brand center"><span class="brand-logo">SP</span><span><strong><?= e($config['app']['name']) ?></strong><small><?= e($config['app']['tagline']) ?></small></span></div>
        <h1>Login Admin</h1>
        <p>Masuk dulu biar kalkulator, riwayat, dan setting harga nggak kebuka publik.</p>
        <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="stack-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label>Username <input name="username" autocomplete="username" required></label>
            <label>Password <input name="password" type="password" autocomplete="current-password" required></label>
            <button class="btn" type="submit">Masuk</button>
        </form>
        
    </main>
</body>
</html>
