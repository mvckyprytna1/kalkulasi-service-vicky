<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/pricing.php';
require_once __DIR__ . '/lib/repositories.php';
require_login();

$config = app_config();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'save_preset') {
                $id = (int)($_POST['id'] ?? 0);
                $data = [
                    clean_text($_POST['code'] ?? '', 80), clean_text($_POST['category'] ?? '', 80), clean_text($_POST['name'] ?? '', 160),
                    (int)n($_POST['base_price'] ?? 0), n($_POST['default_hours'] ?? 1), clean_text($_POST['risk_level'] ?? 'rendah', 40),
                    clean_text($_POST['note'] ?? '', 1000), !empty($_POST['is_active']) ? 1 : 0, (int)n($_POST['sort_order'] ?? 0)
                ];
                if ($id > 0) {
                    $stmt = db()->prepare('UPDATE service_presets SET code=?, category=?, name=?, base_price=?, default_hours=?, risk_level=?, note=?, is_active=?, sort_order=? WHERE id=?');
                    $stmt->execute([...$data, $id]);
                } else {
                    $stmt = db()->prepare('INSERT INTO service_presets (code, category, name, base_price, default_hours, risk_level, note, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute($data);
                }
                $message = 'Preset layanan berhasil disimpan.';
            }
            if ($action === 'delete_preset') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = db()->prepare('DELETE FROM service_presets WHERE id=?');
                $stmt->execute([$id]);
                $message = 'Preset layanan dihapus.';
            }
            if ($action === 'save_addon') {
                $id = (int)($_POST['id'] ?? 0);
                $data = [clean_text($_POST['code'] ?? '', 80), clean_text($_POST['name'] ?? '', 160), (int)n($_POST['price'] ?? 0), !empty($_POST['is_active']) ? 1 : 0, (int)n($_POST['sort_order'] ?? 0)];
                if ($id > 0) {
                    $stmt = db()->prepare('UPDATE addon_services SET code=?, name=?, price=?, is_active=?, sort_order=? WHERE id=?');
                    $stmt->execute([...$data, $id]);
                } else {
                    $stmt = db()->prepare('INSERT INTO addon_services (code, name, price, is_active, sort_order) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute($data);
                }
                $message = 'Tambahan layanan berhasil disimpan.';
            }
            if ($action === 'delete_addon') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = db()->prepare('DELETE FROM addon_services WHERE id=?');
                $stmt->execute([$id]);
                $message = 'Tambahan layanan dihapus.';
            }
        } catch (PDOException $e) {
            $sqlState = $e->errorInfo[0] ?? '';
            $driverCode = $e->errorInfo[1] ?? '';

            if ($sqlState === '23000' || (string)$driverCode === '1062') {
                $error = 'Gagal menyimpan: kode unik sudah dipakai. Pakai kode lain tanpa spasi, contoh install_windows_2.';
            } else {
                $error = 'Gagal menyimpan database. Detail: ' . $e->getMessage();
            }
        } catch (Throwable $e) {
            $error = 'Gagal menyimpan. Detail: ' . $e->getMessage();
        }
    }
}

$presets = get_all_presets();
$addons = get_all_addons();
$riskOptions = array_keys($config['pricing_rules']['risk_fees']);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Settings - <?= e($config['app']['name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/partials_nav.php'; ?>
<main class="history-page">
    <div class="container settings-grid">
        <section class="panel wide-panel">
            <div class="panel-head"><div><span class="section-kicker">Settings</span><h1 class="history-title">Preset Layanan</h1></div></div>
            <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

            <h3>Tambah Preset Baru</h3>
            <form method="post" class="settings-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_preset">
                <input type="hidden" name="id" value="0">
                <input name="code" placeholder="kode_unik" required>
                <input name="category" placeholder="Kategori" required>
                <input name="name" placeholder="Nama layanan" required>
                <input name="base_price" type="number" placeholder="Harga dasar" required>
                <input name="default_hours" type="number" step="0.25" placeholder="Jam default" value="1">
                <select name="risk_level"><?php foreach ($riskOptions as $r): ?><option value="<?= e($r) ?>"><?= e($r) ?></option><?php endforeach; ?></select>
                <input name="sort_order" type="number" placeholder="Urutan" value="99">
                <label class="inline-check"><input type="checkbox" name="is_active" checked> Aktif</label>
                <textarea name="note" placeholder="Catatan" rows="2"></textarea>
                <button class="btn" type="submit">Tambah Preset</button>
            </form>

            <div class="table-wrap small-table">
                <table>
                    <thead><tr><th>Aktif</th><th>Kode</th><th>Kategori</th><th>Nama</th><th>Harga</th><th>Jam</th><th>Risiko</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <?php foreach ($presets as $p): ?>
                        <tr>
                            <td colspan="8">
                                <form method="post" class="settings-row">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="save_preset">
                                    <input type="hidden" name="id" value="<?= e($p['id']) ?>">
                                    <label class="tiny-check"><input type="checkbox" name="is_active" <?= $p['is_active']?'checked':'' ?>></label>
                                    <input name="code" value="<?= e($p['code']) ?>" required>
                                    <input name="category" value="<?= e($p['category']) ?>" required>
                                    <input name="name" value="<?= e($p['name']) ?>" required>
                                    <input name="base_price" type="number" value="<?= e($p['base_price']) ?>" required>
                                    <input name="default_hours" type="number" step="0.25" value="<?= e($p['default_hours']) ?>">
                                    <select name="risk_level"><?php foreach ($riskOptions as $r): ?><option value="<?= e($r) ?>" <?= $p['risk_level']===$r?'selected':'' ?>><?= e($r) ?></option><?php endforeach; ?></select>
                                    <input name="sort_order" type="number" value="<?= e($p['sort_order']) ?>">
                                    <input name="note" value="<?= e($p['note']) ?>" placeholder="Catatan">
                                    <button class="mini-btn" type="submit">Save</button>
                                </form>
                                <form method="post" onsubmit="return confirm('Hapus preset ini?')" class="inline-delete">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete_preset">
                                    <input type="hidden" name="id" value="<?= e($p['id']) ?>">
                                    <button class="danger-btn small" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel wide-panel">
            <div class="panel-head"><div><span class="section-kicker">Settings</span><h1 class="history-title">Tambahan Layanan</h1></div></div>
            <form method="post" class="settings-form addon-new">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_addon">
                <input type="hidden" name="id" value="0">
                <input name="code" placeholder="kode_unik" required>
                <input name="name" placeholder="Nama tambahan" required>
                <input name="price" type="number" placeholder="Harga" required>
                <input name="sort_order" type="number" placeholder="Urutan" value="99">
                <label class="inline-check"><input type="checkbox" name="is_active" checked> Aktif</label>
                <button class="btn" type="submit">Tambah</button>
            </form>
            <div class="table-wrap small-table">
                <table>
                    <thead><tr><th>Data</th></tr></thead>
                    <tbody>
                    <?php foreach ($addons as $a): ?>
                        <tr><td>
                            <form method="post" class="settings-row addon-row">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="save_addon">
                                <input type="hidden" name="id" value="<?= e($a['id']) ?>">
                                <label class="tiny-check"><input type="checkbox" name="is_active" <?= $a['is_active']?'checked':'' ?>></label>
                                <input name="code" value="<?= e($a['code']) ?>" required>
                                <input name="name" value="<?= e($a['name']) ?>" required>
                                <input name="price" type="number" value="<?= e($a['price']) ?>" required>
                                <input name="sort_order" type="number" value="<?= e($a['sort_order']) ?>">
                                <button class="mini-btn" type="submit">Save</button>
                            </form>
                            <form method="post" onsubmit="return confirm('Hapus addon ini?')" class="inline-delete">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete_addon">
                                <input type="hidden" name="id" value="<?= e($a['id']) ?>">
                                <button class="danger-btn small" type="submit">Delete</button>
                            </form>
                        </td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>
<script>document.querySelector('#navToggle')?.addEventListener('click',()=>document.querySelector('#navLinks')?.classList.toggle('open'));</script>
</body>
</html>
