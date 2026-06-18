<?php
require_once __DIR__ . '/lib/bootstrap.php';

$config = app_config();
$key = $_GET['key'] ?? ($_POST['install_key'] ?? '');
$validKey = $config['security']['install_key'] ?? '';
$allowed = $validKey !== '' && hash_equals($validKey, (string)$key);
$message = '';
$error = '';

$seedPresets = [
    ['cek_awal','Diagnosa','Cek Awal / Diagnosa',25000,0.50,'rendah','Cek gejala dan estimasi tindakan awal.',1],
    ['install_windows','Software','Install Ulang Windows',75000,1.50,'rendah','Install OS bersih tanpa backup besar.',2],
    ['install_driver_apps','Software','Install Ulang + Driver + Aplikasi Dasar',120000,2.00,'sedang','Paket software siap pakai.',3],
    ['backup_data','Data','Backup Data Ringan',50000,1.00,'sedang','Backup dokumen/foto/file umum.',4],
    ['recovery_data','Data','Recovery Data / Bad Sector',250000,4.00,'tinggi','Tidak boleh menjanjikan data 100% kembali.',5],
    ['cleaning_laptop','Maintenance','Cleaning Laptop + Thermal Paste',90000,1.50,'sedang','Bongkar ringan, bersihkan debu, ganti thermal.',6],
    ['upgrade_ssd','Hardware','Upgrade SSD / Cloning Sistem',85000,1.50,'sedang','Belum termasuk harga SSD.',7],
    ['ganti_keyboard','Hardware','Ganti Keyboard Laptop',75000,1.00,'sedang','Belum termasuk sparepart.',8],
    ['ganti_lcd','Hardware','Ganti LCD Laptop / HP',100000,1.50,'tinggi','Wajib DP jika beli sparepart.',9],
    ['flashing_android','Android','Flashing Android',100000,2.00,'tinggi','Wajib jelaskan risiko data dan firmware.',10],
    ['setting_printer','Peripheral','Setting Printer',65000,1.00,'rendah','Install driver dan koneksi dasar.',11],
    ['onsite_network','On-site','Panggilan / Setting Jaringan',150000,2.00,'sedang','Belum termasuk transport tambahan.',12],
];

$seedAddons = [
    ['backup_extra','Backup Data Tambahan',35000,1],
    ['driver_complete','Driver Lengkap',25000,2],
    ['apps_basic','Aplikasi Dasar',30000,3],
    ['malware_clean','Bersihkan Malware / Iklan',50000,4],
    ['thermal_paste','Thermal Paste / Bahan Cleaning',25000,5],
    ['data_migration','Migrasi Data ke SSD Baru',50000,6],
    ['pickup_delivery','Ambil / Antar Perangkat',30000,7],
    ['aftercare_report','Catatan Perawatan / Report',15000,8],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allowed) {
    $username = clean_text($_POST['username'] ?? 'admin', 80);
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || strlen($password) < 8) {
        $error = 'Username wajib diisi dan password minimal 8 karakter.';
    } else {
        try {
            $sql = file_get_contents(__DIR__ . '/database.sql');
            db()->exec($sql);

            $stmt = db()->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);

            $presetStmt = db()->prepare('INSERT INTO service_presets (code, category, name, base_price, default_hours, risk_level, note, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE category=VALUES(category), name=VALUES(name), base_price=VALUES(base_price), default_hours=VALUES(default_hours), risk_level=VALUES(risk_level), note=VALUES(note), sort_order=VALUES(sort_order)');
            foreach ($seedPresets as $p) {
                $presetStmt->execute($p);
            }

            $addonStmt = db()->prepare('INSERT INTO addon_services (code, name, price, sort_order)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE name=VALUES(name), price=VALUES(price), sort_order=VALUES(sort_order)');
            foreach ($seedAddons as $a) {
                $addonStmt->execute($a);
            }

            $message = 'Install berhasil. Admin dan data preset sudah dibuat. Demi keamanan, hapus file install.php setelah login berhasil.';
        } catch (Throwable $e) {
            $error = 'Install gagal: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install - <?= e($config['app']['name']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">
    <main class="auth-card">
        <div class="brand center"><span class="brand-logo">SP</span><span><strong>Installer</strong><small>MySQL cPanel Setup</small></span></div>
        <?php if (!$allowed): ?>
            <div class="alert error">Install key salah. Buka URL dengan format: <code>install.php?key=ISI_INSTALL_KEY</code>. Nilainya ada di <code>config.php</code>.</div>
        <?php else: ?>
            <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
            <form method="post" class="stack-form">
                <input type="hidden" name="install_key" value="<?= e($key) ?>">
                <label>Username Admin <input name="username" value="admin" required></label>
                <label>Password Admin <input name="password" type="password" minlength="8" placeholder="Minimal 8 karakter" required></label>
                <button class="btn" type="submit">Buat Tabel & Admin</button>
            </form>
            <p class="mini-note">Pastikan kredensial database di <code>config.php</code> sudah benar sebelum klik tombol.</p>
        <?php endif; ?>
    </main>
</body>
</html>
