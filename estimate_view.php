<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/service_workflow.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM estimates WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    echo 'Estimasi tidak ditemukan.';
    exit;
}
$config = app_config();
$statuses = ['draft','sent','deal','rejected','revised','paid','done'];
$addons = json_decode($row['addon_json'] ?? '[]', true) ?: [];
$warnings = json_decode($row['warnings_json'] ?? '[]', true) ?: [];
$clientWaNumber = normalize_wa_number($row['client_phone'] ?? '');
$serviceOrder = get_service_order_by_estimate((int)$row['id']);

?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title><?= e($row['estimate_code']) ?> - <?= e($config['app']['name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/partials_nav.php'; ?>
<main class="history-page">
    <div class="container detail-grid">
        <section class="panel wide-panel">
            <div class="panel-head">
                <div><span class="section-kicker">Detail</span><h1 class="history-title"><?= e($row['estimate_code']) ?></h1></div>
                <a class="btn btn-ghost" href="estimate_edit.php?id=<?= e($row['id']) ?>">Revisi Estimasi</a><?php if ($serviceOrder): ?><a class="btn" href="service_order_view.php?id=<?= e($serviceOrder['id']) ?>">Buka Job Ticket</a><?php else: ?><form method="post" action="create_order_from_estimate.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="estimate_id" value="<?= e($row['id']) ?>"><button class="btn" type="submit">Buat Job Ticket</button></form><?php endif; ?><a class="btn btn-soft" href="history.php">Kembali</a>
            </div>
            <div class="alert">
                <b>Alur yang benar:</b> Estimasi = harga awal/penawaran. Kalau awalnya cuma cek 30K lalu setelah dicek ketemu kerusakan, klik <b>Revisi Estimasi</b>. Job Ticket = data barang yang benar-benar masuk/dikerjakan.
            </div>

            <div class="summary-cards">
                <div class="price-card safe"><span>Minimal Aman</span><strong><?= money($row['minimal_price']) ?></strong></div>
                <div class="price-card ideal"><span>Harga Ideal</span><strong><?= money($row['ideal_price']) ?></strong></div>
                <div class="price-card"><span>Range Client</span><strong><?= money($row['range_low']) ?> - <?= money($row['range_high']) ?></strong></div>
                <div class="price-card"><span>Batas Nego</span><strong><?= money($row['nego_price']) ?></strong></div>
            </div>

            <div class="detail-section">
                <h3>Client & Perangkat</h3>
                <div class="kv-grid">
                    <div><span>Client</span><b><?= e($row['client_name']) ?></b></div>
                    <div><span>WhatsApp</span><b><?= e($row['client_phone']) ?></b></div>
                    <div><span>Perangkat</span><b><?= e(trim($row['device_type'].' '.$row['device_brand'].' '.$row['device_model'])) ?></b></div>
                    <div><span>Lokasi</span><b><?= e($row['location']) ?></b></div>
                    <div><span>Kondisi</span><b><?= e($row['physical_condition']) ?></b></div>
                    <div><span>Garansi Resmi</span><b><?= e($row['official_warranty']) ?></b></div>
                </div>
            </div>

            <div class="detail-section">
                <h3>Layanan</h3>
                <p><b><?= e($row['service_name']) ?></b></p>
                <?php if ($addons): ?><p>Tambahan: <?= e(implode(', ', $addons)) ?></p><?php endif; ?>
                <div class="kv-grid">
                    <div><span>Difficulty</span><b><?= e($row['difficulty']) ?></b></div>
                    <div><span>Risiko</span><b><?= e($row['risk_level']) ?></b></div>
                    <div><span>Urgency</span><b><?= e($row['urgency']) ?></b></div>
                    <div><span>Garansi</span><b><?= e($row['warranty']) ?></b></div>
                </div>
            </div>

            <div class="detail-section">
                <h3>Breakdown</h3>
                <div class="breakdown compact-list">
                    <?php foreach ([
                        'Modal Total'=>$row['modal_total'], 'Jasa Dasar'=>$row['jasa_dasar'], 'Biaya Waktu'=>$row['time_cost'], 'Biaya Risiko'=>$row['risk_fee'],
                        'Jasa Total'=>$row['jasa_total'], 'Urgency Fee'=>$row['urgency_fee'], 'Garansi Fee'=>$row['warranty_fee'], 'Margin'=>$row['margin_profit'],
                        'Profit'=>$row['profit'], 'DP'=>$row['dp']
                    ] as $label=>$value): ?>
                        <div class="breakdown-row"><span><?= e($label) ?></span><strong><?= money($value) ?></strong></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <aside class="panel result-panel">
            <form method="post" action="update_status.php" class="stack-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                <label>Status
                    <select name="status">
                        <?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>" <?= $row['status']===$s?'selected':'' ?>><?= e(strtoupper($s)) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <button class="btn" type="submit">Update Status</button>
            </form>
            <label class="message-box">Pesan WhatsApp<textarea id="detailWa" rows="9" readonly><?= e($row['whatsapp_message']) ?></textarea></label>
            <div class="action-grid single">
                <button class="btn btn-ghost" onclick="navigator.clipboard.writeText(document.querySelector('#detailWa').value)" type="button">Copy Pesan</button>
                <?php if ($clientWaNumber): ?>
                    <a class="btn btn-soft" target="_blank" rel="noopener" href="https://wa.me/<?= e($clientWaNumber) ?>?text=<?= e(rawurlencode($row['whatsapp_message'])) ?>">Buka WhatsApp</a>
                <?php else: ?>
                    <button class="btn btn-soft" type="button" onclick="alert('Nomor WhatsApp client kosong / tidak valid.')">Buka WhatsApp</button>
                <?php endif; ?>
            </div>
            <?php if ($serviceOrder): ?><div class="warning-box"><div class="warning ok">✓ Job ticket sudah dibuat: <?= e($serviceOrder['ticket_code']) ?></div></div><?php endif; ?>
            <?php if ($warnings): ?><div class="warning-box"><?php foreach ($warnings as $w): ?><div class="warning">⚠ <?= e($w) ?></div><?php endforeach; ?></div><?php endif; ?>
            <form method="post" action="delete_estimate.php" onsubmit="return confirm('Hapus estimasi ini?')" class="delete-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                <button class="danger-btn" type="submit">Hapus Estimasi</button>
            </form>
        </aside>
    </div>
</main>
<script>document.querySelector('#navToggle')?.addEventListener('click',()=>document.querySelector('#navLinks')?.classList.toggle('open'));</script>
</body>
</html>
