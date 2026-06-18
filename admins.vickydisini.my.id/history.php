<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_login();

$config = app_config();
$status = clean_text($_GET['status'] ?? '', 30);
$q = clean_text($_GET['q'] ?? '', 120);
$where = [];
$params = [];

if ($status !== '') {
    $where[] = 'status = ?';
    $params[] = $status;
}
if ($q !== '') {
    $where[] = '(client_name LIKE ? OR client_phone LIKE ? OR device_brand LIKE ? OR service_name LIKE ? OR estimate_code LIKE ?)';
    for ($i=0; $i<5; $i++) $params[] = '%' . $q . '%';
}

$sql = 'SELECT estimates.*, (SELECT id FROM service_orders WHERE service_orders.estimate_id = estimates.id ORDER BY id DESC LIMIT 1) AS order_id FROM estimates';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY created_at DESC LIMIT 200';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$statuses = ['draft','sent','deal','rejected','revised','paid','done'];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Riwayat - <?= e($config['app']['name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/partials_nav.php'; ?>
<main class="history-page">
    <div class="container">
        <div class="panel wide-panel">
            <div class="panel-head">
                <div><span class="section-kicker">Database</span><h1 class="history-title">Riwayat Estimasi</h1></div>
                <a class="btn btn-soft" href="calculator.php">Buat Baru</a>
            </div>
            <form class="filter-bar" method="get">
                <input name="q" value="<?= e($q) ?>" placeholder="Cari client, HP, layanan, kode...">
                <select name="status">
                    <option value="">Semua Status</option>
                    <?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>" <?= $status===$s?'selected':'' ?>><?= e(strtoupper($s)) ?></option><?php endforeach; ?>
                </select>
                <button class="btn" type="submit">Filter</button>
            </form>

            <?php if (!$rows): ?>
                <div class="empty-state">Belum ada estimasi tersimpan.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Tanggal</th><th>Kode</th><th>Client</th><th>Tipe</th><th>Perangkat</th><th>Layanan</th><th>Ideal</th><th>Range</th><th>Profit</th><th>Status</th><th>Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= e($row['created_at']) ?></td>
                                <td><strong><?= e($row['estimate_code']) ?></strong></td>
                                <td><strong><?= e($row['client_name']) ?></strong><small><?= e($row['client_phone']) ?></small></td>
                                <td><?= e($row['device_type']) ?><br><small><?= e(trim($row['device_brand'].' '.$row['device_model'])) ?></small></td>
                                <td><?= e($row['service_name']) ?></td>
                                <td><strong><?= money($row['ideal_price']) ?></strong></td>
                                <td><?= money($row['range_low']) ?> - <?= money($row['range_high']) ?></td>
                                <td><?= money($row['profit']) ?></td>
                                <td><span class="status-badge"><?= e($row['status']) ?></span></td>
                                <td class="row-actions">
                                    <a class="mini-btn" href="estimate_view.php?id=<?= e($row['id']) ?>">Detail</a>
                                    <a class="mini-btn soft-mini" href="estimate_edit.php?id=<?= e($row['id']) ?>">Revisi</a>
                                    <?php if (!empty($row['order_id'])): ?>
                                        <a class="mini-btn soft-mini" href="service_order_view.php?id=<?= e($row['order_id']) ?>">Job</a>
                                    <?php else: ?>
                                        <form class="inline-form" method="post" action="create_order_from_estimate.php">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="estimate_id" value="<?= e($row['id']) ?>">
                                            <button class="mini-btn" type="submit">Buat Job</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<script>document.querySelector('#navToggle')?.addEventListener('click',()=>document.querySelector('#navLinks')?.classList.toggle('open'));</script>
</body>
</html>
