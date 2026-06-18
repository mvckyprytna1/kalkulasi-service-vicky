<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/service_workflow.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$order = get_service_order($id);
if (!$order) {
    http_response_code(404);
    echo 'Job ticket tidak ditemukan.';
    exit;
}
$config = app_config();
$statuses = service_statuses();
$paymentStatuses = payment_statuses();
$payments = get_order_payments((int)$order['id']);
$logs = get_order_logs((int)$order['id']);
$sisaBayar = max(0, (int)$order['final_amount'] - (int)$order['paid_amount']);
$deviceText = trim(implode(' ', array_filter([$order['device_type'], $order['device_brand'], $order['device_model']])));
$clientWa = normalize_wa_number($order['client_phone'] ?: ($order['client_db_phone'] ?? ''));
$templates = get_whatsapp_templates();
$publicInvoiceUrl = public_invoice_url($order, true);
$templatePayload = [];
foreach ($templates as $tpl) {
    $templatePayload[] = [
        'title' => $tpl['title'],
        'body' => render_template_body($tpl['body'], $order),
    ];
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title><?= e($order['ticket_code']) ?> - <?= e($config['app']['name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/partials_nav.php'; ?>
<main class="history-page">
    <div class="container detail-grid">
        <section class="panel wide-panel">
            <div class="panel-head">
                <div><span class="section-kicker">Job Ticket</span><h1 class="history-title"><?= e($order['ticket_code']) ?></h1></div>
                <div class="hero-actions tiny-actions">
                    <?php if (!empty($order['estimate_id'])): ?><a class="btn btn-ghost" href="estimate_edit.php?id=<?= e($order['estimate_id']) ?>">Revisi Estimasi</a><?php endif; ?>
                    <a class="btn" href="service_order_edit.php?id=<?= e($order['id']) ?>">Edit Job</a>
                    <a class="btn btn-ghost" target="_blank" href="invoice.php?id=<?= e($order['id']) ?>">Nota 1</a>
                    <a class="btn btn-soft" target="_blank" href="<?= e(public_invoice_url($order, false)) ?>">Nota 2</a>
                    <a class="btn btn-soft" href="service_orders.php">Kembali</a>
                </div>
            </div>

            <?php if (!empty($_GET['updated'])): ?><div class="alert success">Job ticket berhasil diperbarui.</div><?php endif; ?>

            <div class="summary-cards">
                <div class="price-card"><span>Status Service</span><strong><?= e($statuses[$order['service_status']] ?? $order['service_status']) ?></strong></div>
                <div class="price-card ideal"><span>Total</span><strong><?= money($order['final_amount']) ?></strong></div>
                <div class="price-card safe"><span>Dibayar</span><strong><?= money($order['paid_amount']) ?></strong></div>
                <div class="price-card"><span>Sisa</span><strong><?= money($sisaBayar) ?></strong></div>
            </div>

            <div class="detail-section">
                <h3>Client & Perangkat</h3>
                <div class="kv-grid">
                    <div><span>Client</span><b><?= e($order['client_name']) ?></b></div>
                    <div><span>WhatsApp</span><b><?= e($order['client_phone']) ?></b></div>
                    <div><span>Lokasi</span><b><?= e($order['location'] ?: '-') ?></b></div>
                    <div><span>Perangkat</span><b><?= e($deviceText ?: '-') ?></b></div>
                    <div><span>Serial / IMEI</span><b><?= e($order['serial_number'] ?: '-') ?></b></div>
                    <div><span>Password</span><b><?= e($order['device_password'] ?: '-') ?></b></div>
                    <div><span>Kondisi Fisik</span><b><?= e($order['physical_condition'] ?: '-') ?></b></div>
                    <div><span>Garansi Resmi</span><b><?= e($order['official_warranty'] ?: '-') ?></b></div>
                </div>
                <?php if ($order['accessories']): ?><p><b>Kelengkapan:</b><br><?= nl2br(e($order['accessories'])) ?></p><?php endif; ?>
            </div>

            <div class="detail-section">
                <h3>Pengerjaan</h3>
                <div class="kv-grid">
                    <div><span>Layanan</span><b><?= e($order['service_name']) ?></b></div>
                    <div><span>Status</span><b><?= e($statuses[$order['service_status']] ?? $order['service_status']) ?></b></div>
                    <div><span>Estimasi Awal</span><b><?= money($order['estimate_amount']) ?></b></div>
                    <div><span>Total Final</span><b><?= money($order['final_amount']) ?></b></div>
                </div>
                <?php if ($order['complaint']): ?><p><b>Keluhan:</b><br><?= nl2br(e($order['complaint'])) ?></p><?php endif; ?>
                <?php if ($order['technician_notes']): ?><p><b>Catatan Teknisi:</b><br><?= nl2br(e($order['technician_notes'])) ?></p><?php endif; ?>
                <?php if ($order['customer_notes']): ?><p><b>Catatan untuk Client:</b><br><?= nl2br(e($order['customer_notes'])) ?></p><?php endif; ?>
            </div>

            <div class="detail-section">
                <h3>Garansi</h3>
                <div class="kv-grid">
                    <div><span>Durasi</span><b><?= (int)$order['warranty_days'] ?> hari</b></div>
                    <div><span>Mulai</span><b><?= e($order['warranty_start_at'] ?: '-') ?></b></div>
                    <div><span>Akhir</span><b><?= e($order['warranty_end_at'] ?: '-') ?></b></div>
                    <div><span>Selesai / Diambil</span><b><?= e(($order['finished_at'] ?: '-') . ' / ' . ($order['picked_up_at'] ?: '-')) ?></b></div>
                </div>
                <?php if ($order['warranty_note']): ?><p><b>Catatan Garansi:</b><br><?= nl2br(e($order['warranty_note'])) ?></p><?php endif; ?>
            </div>

            <div class="detail-section" id="payment">
                <h3>Pembayaran</h3>
                <form method="post" action="update_service_order.php" class="settings-form addon-new">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= e($order['id']) ?>">
                    <input type="hidden" name="action" value="add_payment">
                    <input name="amount" type="number" min="1000" step="1000" placeholder="Nominal">
                    <select name="method"><?php foreach (payment_methods() as $m): ?><option><?= e($m) ?></option><?php endforeach; ?></select>
                    <input name="paid_at" type="datetime-local" value="<?= e(date('Y-m-d\TH:i')) ?>">
                    <input name="note" placeholder="Catatan pembayaran">
                    <button class="btn" type="submit">Tambah Bayar</button>
                </form>
                <?php if ($payments): ?>
                    <div class="table-wrap small-table"><table><thead><tr><th>Tanggal</th><th>Nominal</th><th>Metode</th><th>Catatan</th></tr></thead><tbody>
                    <?php foreach ($payments as $pay): ?><tr><td><?= e($pay['paid_at']) ?></td><td><strong><?= money($pay['amount']) ?></strong></td><td><?= e($pay['method']) ?></td><td><?= e($pay['note']) ?></td></tr><?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </div>

            <div class="detail-section">
                <h3>Log Aktivitas</h3>
                <?php if (!$logs): ?><p>Belum ada log.</p><?php else: ?>
                <div class="timeline-list">
                    <?php foreach ($logs as $log): ?><div><span><?= e($log['created_at']) ?> • <?= e($log['created_by']) ?></span><b><?= e($log['log_type']) ?>: <?= e($log['old_value']) ?> → <?= e($log['new_value']) ?></b><p><?= e($log['note']) ?></p></div><?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <aside class="panel result-panel">
            <div class="result-sticky">
                <div class="panel-head"><div><span class="section-kicker">WhatsApp</span><h2>Template Chat</h2></div></div>
                <label>Pilih Template
                    <select id="templateSelect">
                        <?php foreach ($templatePayload as $i => $tpl): ?><option value="<?= e((string)$i) ?>"><?= e($tpl['title']) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label class="message-box">Pesan<textarea id="templateOutput" rows="10" readonly></textarea></label>
                <div class="action-grid single">
                    <button class="btn btn-ghost" type="button" id="copyTemplateBtn">Copy Pesan</button>
                    <?php if ($clientWa): ?>
                        <button class="btn btn-soft" type="button" id="openTemplateWa">Buka WhatsApp</button>
                    <?php else: ?>
                        <button class="btn btn-soft" type="button" onclick="alert('Nomor WhatsApp client kosong / tidak valid.')">Buka WhatsApp</button>
                    <?php endif; ?>
                </div>
                <a class="btn btn-soft full-link" target="_blank" href="invoice.php?id=<?= e($order['id']) ?>">Nota 1 / Print</a>
                <a class="btn btn full-link" target="_blank" href="<?= e(public_invoice_url($order, false)) ?>">Nota 2 / Link Client</a>
                <button class="btn btn-ghost full-link" type="button" id="copyPublicInvoiceBtn">Copy Link Nota 2</button>
                <a class="btn btn-ghost full-link" href="service_order_edit.php?id=<?= e($order['id']) ?>">Mode Edit</a>
                <form method="post" action="delete_service_order.php" onsubmit="return confirm('Hapus job ticket ini? Pembayaran dan log untuk ticket ini juga ikut dihapus.')" class="delete-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= e($order['id']) ?>">
                    <button class="danger-btn" type="submit">Hapus Job Ticket</button>
                </form>
                <p class="mini-note">Device: <?= e($deviceText ?: '-') ?></p>
            </div>
        </aside>
    </div>
</main>
<script>
const templates = <?= json_encode($templatePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const clientWa = <?= json_encode($clientWa) ?>;
const select = document.querySelector('#templateSelect');
const output = document.querySelector('#templateOutput');
function renderTpl(){ output.value = templates[Number(select?.value || 0)]?.body || ''; }
select?.addEventListener('change', renderTpl); renderTpl();
document.querySelector('#copyTemplateBtn')?.addEventListener('click', async()=>{ await navigator.clipboard.writeText(output.value); alert('Pesan dicopy.'); });
document.querySelector('#openTemplateWa')?.addEventListener('click',()=>{ if(clientWa) window.open(`https://wa.me/${clientWa}?text=${encodeURIComponent(output.value)}`,'_blank','noopener'); });
document.querySelector('#copyPublicInvoiceBtn')?.addEventListener('click', async()=>{ try { await navigator.clipboard.writeText(<?= json_encode($publicInvoiceUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>); alert('Link nota 2 dicopy.'); } catch(err){ alert('Gagal copy link nota 2.'); } });
document.querySelector('#navToggle')?.addEventListener('click',()=>document.querySelector('#navLinks')?.classList.toggle('open'));
</script>
</body>
</html>
