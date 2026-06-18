<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/service_workflow.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM clients WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$client = $stmt->fetch();
if (!$client) {
    http_response_code(404);
    echo 'Client tidak ditemukan.';
    exit;
}
$config = app_config();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Edit Client - <?= e($config['app']['name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/partials_nav.php'; ?>
<main class="history-page">
    <div class="container detail-grid">
        <section class="panel wide-panel">
            <form method="post" action="client_update.php" class="stack-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= e($client['id']) ?>">
                <input type="hidden" name="action" value="update">

                <div class="panel-head">
                    <div><span class="section-kicker">Mode Edit</span><h1 class="history-title">Edit Client</h1></div>
                    <div class="hero-actions tiny-actions">
                        <a class="btn btn-soft" href="client_view.php?id=<?= e($client['id']) ?>">Batal</a>
                        <a class="btn btn-ghost" href="clients.php">Daftar Client</a>
                    </div>
                </div>

                <div class="detail-section">
                    <h3>Data Client</h3>
                    <div class="form-grid">
                        <label>Nama Client <input name="name" value="<?= e($client['name']) ?>" required></label>
                        <label>WhatsApp <input name="phone" value="<?= e($client['phone']) ?>" placeholder="08xxxxxxxxxx"></label>
                        <label>Alamat / Area <input name="address" value="<?= e($client['address']) ?>"></label>
                        <label>Tipe Client <input name="client_type" value="<?= e($client['client_type']) ?>" placeholder="Baru / langganan / kantor"></label>
                    </div>
                    <label>Catatan Internal <textarea name="notes" rows="6" placeholder="Catatan tentang client..."><?= e($client['notes']) ?></textarea></label>
                </div>

                <button class="btn" type="submit">Simpan Client</button>
            </form>
        </section>

        <aside class="panel result-panel">
            <div class="result-sticky">
                <div class="panel-head"><div><span class="section-kicker">Aksi</span><h2>Client</h2></div></div>
                <div class="kv-grid one-col">
                    <div><span>Total Job</span><b><?= e($client['total_jobs']) ?></b></div>
                    <div><span>Total Dibayar</span><b><?= money($client['total_spent']) ?></b></div>
                    <div><span>Terakhir Service</span><b><?= e($client['last_service_at'] ?: '-') ?></b></div>
                </div>
                <form method="post" action="client_update.php" onsubmit="return confirm('Hapus client ini? Riwayat job tidak ikut hilang, tapi koneksi client_id akan dilepas.')" class="delete-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= e($client['id']) ?>">
                    <input type="hidden" name="action" value="delete">
                    <button class="danger-btn" type="submit">Hapus Client</button>
                </form>
                <p class="mini-note">Hapus client tidak menghapus riwayat job ticket. Riwayat tetap tersimpan.</p>
            </div>
        </aside>
    </div>
</main>
<script>document.querySelector('#navToggle')?.addEventListener('click',()=>document.querySelector('#navLinks')?.classList.toggle('open'));</script>
</body>
</html>
