<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/service_workflow.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM clients WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$client = $stmt->fetch();
if (!$client) { http_response_code(404); echo 'Client tidak ditemukan.'; exit; }
$stmt = db()->prepare('SELECT * FROM service_orders WHERE client_id = ? ORDER BY created_at DESC LIMIT 200');
$stmt->execute([$id]);
$orders = $stmt->fetchAll();
$config = app_config();
$statuses = service_statuses();
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><title><?= e($client['name']) ?> - <?= e($config['app']['name']) ?></title><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><link rel="stylesheet" href="assets/css/style.css"></head>
<body><?php include __DIR__ . '/partials_nav.php'; ?>
<main class="history-page"><div class="container detail-grid">
<section class="panel wide-panel"><div class="panel-head"><div><span class="section-kicker">Client</span><h1 class="history-title"><?= e($client['name']) ?></h1></div><div class="hero-actions tiny-actions"><a class="btn" href="client_edit.php?id=<?= e($client['id']) ?>">Edit Client</a><a class="btn btn-soft" href="clients.php">Kembali</a></div></div>
<?php if (!empty($_GET['updated'])): ?><div class="alert success">Client berhasil diperbarui.</div><?php endif; ?>
<div class="summary-cards"><div class="price-card"><span>Total Job</span><strong><?= e($client['total_jobs']) ?></strong></div><div class="price-card safe"><span>Total Dibayar</span><strong><?= money($client['total_spent']) ?></strong></div><div class="price-card"><span>WhatsApp</span><strong><?= e($client['phone']) ?></strong></div><div class="price-card"><span>Terakhir</span><strong><?= e($client['last_service_at'] ?: '-') ?></strong></div></div>
<div class="detail-section"><h3>Riwayat Service</h3><?php if(!$orders): ?><p>Belum ada job.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Tanggal</th><th>Ticket</th><th>Perangkat</th><th>Layanan</th><th>Total</th><th>Status</th><th>Aksi</th></tr></thead><tbody><?php foreach($orders as $o): ?><tr><td><?= e($o['created_at']) ?></td><td><strong><?= e($o['ticket_code']) ?></strong></td><td><?= e(trim($o['device_type'].' '.$o['device_brand'].' '.$o['device_model'])) ?></td><td><?= e($o['service_name']) ?></td><td><?= money($o['final_amount']) ?></td><td><span class="status-badge status-<?= e($o['service_status']) ?>"><?= e($statuses[$o['service_status']] ?? $o['service_status']) ?></span></td><td class="row-actions"><a class="mini-btn" href="service_order_view.php?id=<?= e($o['id']) ?>">Detail</a> <a class="mini-btn soft-mini" href="service_order_edit.php?id=<?= e($o['id']) ?>">Edit</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></section>
<aside class="panel result-panel"><div class="result-sticky"><h2>Data Client</h2><div class="kv-grid one-col"><div><span>Nomor</span><b><?= e($client['phone']) ?></b></div><div><span>Alamat</span><b><?= e($client['address']) ?></b></div><div><span>Tipe</span><b><?= e($client['client_type'] ?: '-') ?></b></div><div><span>Catatan</span><b><?= nl2br(e($client['notes'])) ?></b></div></div><?php $wa=normalize_wa_number($client['phone']); if($wa): ?><a class="btn full-link" target="_blank" href="https://wa.me/<?= e($wa) ?>">Chat WhatsApp</a><?php endif; ?><a class="btn btn-ghost full-link" href="client_edit.php?id=<?= e($client['id']) ?>">Mode Edit</a><form method="post" action="client_update.php" onsubmit="return confirm('Hapus client ini? Riwayat job tidak ikut hilang.')" class="delete-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= e($client['id']) ?>"><input type="hidden" name="action" value="delete"><button class="danger-btn" type="submit">Hapus Client</button></form></div></aside>
</div></main><script>document.querySelector('#navToggle')?.addEventListener('click',()=>document.querySelector('#navLinks')?.classList.toggle('open'));</script></body></html>
