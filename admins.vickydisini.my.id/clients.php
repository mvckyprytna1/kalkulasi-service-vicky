<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/service_workflow.php';
require_login();
$config = app_config();
$q = clean_text($_GET['q'] ?? '', 120);
$params = [];
$sql = 'SELECT * FROM clients';
if ($q !== '') {
    $sql .= ' WHERE name LIKE ? OR phone LIKE ? OR address LIKE ? OR notes LIKE ?';
    for ($i=0;$i<4;$i++) $params[] = '%' . $q . '%';
}
$sql .= ' ORDER BY updated_at DESC, created_at DESC LIMIT 300';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>
<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><title>Client - <?= e($config['app']['name']) ?></title><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><link rel="stylesheet" href="assets/css/style.css"></head>
<body>
<?php include __DIR__ . '/partials_nav.php'; ?>
<main class="history-page"><div class="container"><div class="panel wide-panel">
    <div class="panel-head"><div><span class="section-kicker">Database Client</span><h1 class="history-title">Client</h1></div><a class="btn btn-soft" href="service_order_new.php">Tambah Job</a></div>
    <?php if (!empty($_GET['deleted'])): ?><div class="alert success">Client berhasil dihapus. Riwayat job ticket tetap tersimpan.</div><?php endif; ?>
    <form class="filter-bar" method="get"><input name="q" value="<?= e($q) ?>" placeholder="Cari nama, nomor, alamat, catatan..."><button class="btn" type="submit">Cari</button></form>
    <?php if (!$rows): ?><div class="empty-state">Belum ada client.</div><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Client</th><th>WhatsApp</th><th>Alamat</th><th>Total Job</th><th>Total Dibayar</th><th>Terakhir</th><th>Aksi</th></tr></thead><tbody>
        <?php foreach ($rows as $row): ?><tr><td><strong><?= e($row['name']) ?></strong><small><?= e($row['client_type']) ?></small></td><td><?= e($row['phone']) ?></td><td><?= e($row['address']) ?></td><td><?= e($row['total_jobs']) ?></td><td><?= money($row['total_spent']) ?></td><td><?= e($row['last_service_at']) ?></td><td class="row-actions"><a class="mini-btn" href="client_view.php?id=<?= e($row['id']) ?>">Detail</a> <a class="mini-btn soft-mini" href="client_edit.php?id=<?= e($row['id']) ?>">Edit</a> <form class="inline-form" method="post" action="client_update.php" onsubmit="return confirm('Hapus client ini? Riwayat job tidak ikut hilang.')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= e($row['id']) ?>"><input type="hidden" name="action" value="delete"><button class="mini-btn danger-mini" type="submit">Hapus</button></form></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</div></div></main>
<script>document.querySelector('#navToggle')?.addEventListener('click',()=>document.querySelector('#navLinks')?.classList.toggle('open'));</script>
</body></html>
