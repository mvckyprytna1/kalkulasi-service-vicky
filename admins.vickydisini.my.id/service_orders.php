<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/service_workflow.php';
require_login();

$config = app_config();
$q = clean_text($_GET['q'] ?? '', 120);
$status = clean_text($_GET['status'] ?? '', 40);
$payment = clean_text($_GET['payment'] ?? '', 40);
$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(so.ticket_code LIKE ? OR so.client_name LIKE ? OR so.client_phone LIKE ? OR so.device_brand LIKE ? OR so.device_model LIKE ? OR so.service_name LIKE ?)';
    for ($i = 0; $i < 6; $i++) $params[] = '%' . $q . '%';
}
if ($status !== '') {
    $where[] = 'so.service_status = ?';
    $params[] = $status;
}
if ($payment !== '') {
    $where[] = 'so.payment_status = ?';
    $params[] = $payment;
}

$sql = 'SELECT so.*, c.whatsapp_normalized FROM service_orders so LEFT JOIN clients c ON c.id = so.client_id';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY so.created_at DESC LIMIT 300';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$statuses = service_statuses();
$paymentStatuses = payment_statuses();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Service Order - <?= e($config['app']['name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/partials_nav.php'; ?>
<main class="history-page">
    <div class="container">
        <div class="panel wide-panel">
            <div class="panel-head">
                <div><span class="section-kicker">Service Order</span><h1 class="history-title">Job Ticket</h1></div>
                <a class="btn btn-soft" href="service_order_new.php">Tambah Manual</a>
            </div>
            <?php if (!empty($_GET['deleted'])): ?><div class="alert success">Job ticket berhasil dihapus.</div><?php endif; ?>
            <form class="filter-bar" method="get">
                <input name="q" value="<?= e($q) ?>" placeholder="Cari ticket, client, HP, perangkat, layanan...">
                <select name="status">
                    <option value="">Semua Status</option>
                    <?php foreach ($statuses as $key => $label): ?><option value="<?= e($key) ?>" <?= $status===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
                <select name="payment">
                    <option value="">Semua Bayar</option>
                    <?php foreach ($paymentStatuses as $key => $label): ?><option value="<?= e($key) ?>" <?= $payment===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
                <button class="btn" type="submit">Filter</button>
            </form>

            <?php if (!$rows): ?>
                <div class="empty-state">Belum ada job ticket. Buat dari detail estimasi atau klik Tambah Manual.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Tanggal</th><th>Ticket</th><th>Client</th><th>Perangkat</th><th>Layanan</th><th>Total</th><th>Bayar</th><th>Status</th><th>Garansi</th><th>Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= e($row['created_at']) ?></td>
                                <td><strong><?= e($row['ticket_code']) ?></strong></td>
                                <td><strong><?= e($row['client_name']) ?></strong><small><?= e($row['client_phone']) ?></small></td>
                                <td><?= e($row['device_type']) ?><br><small><?= e(trim(($row['device_brand'] ?? '').' '.($row['device_model'] ?? ''))) ?></small></td>
                                <td><?= e($row['service_name']) ?></td>
                                <td><strong><?= money($row['final_amount']) ?></strong></td>
                                <td><?= money($row['paid_amount']) ?><br><span class="status-badge soft-badge"><?= e($paymentStatuses[$row['payment_status']] ?? $row['payment_status']) ?></span></td>
                                <td><span class="status-badge status-<?= e($row['service_status']) ?>"><?= e($statuses[$row['service_status']] ?? $row['service_status']) ?></span></td>
                                <td><?= $row['warranty_end_at'] ? e($row['warranty_end_at']) : '-' ?></td>
                                <td class="row-actions"><a class="mini-btn" href="service_order_view.php?id=<?= e($row['id']) ?>">Detail</a> <a class="mini-btn soft-mini" href="service_order_edit.php?id=<?= e($row['id']) ?>">Edit</a> <a class="mini-btn soft-mini" target="_blank" href="invoice.php?id=<?= e($row['id']) ?>">Nota</a> <form class="inline-form" method="post" action="delete_service_order.php" onsubmit="return confirm('Hapus job ticket ini? Pembayaran dan log ikut dihapus.')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= e($row['id']) ?>"><button class="mini-btn danger-mini" type="submit">Hapus</button></form></td>
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
