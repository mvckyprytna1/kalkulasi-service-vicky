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
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Edit <?= e($order['ticket_code']) ?> - <?= e($config['app']['name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/partials_nav.php'; ?>
<main class="history-page">
    <div class="container detail-grid">
        <section class="panel wide-panel">
            <form method="post" action="update_service_order.php" class="stack-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= e($order['id']) ?>">
                <input type="hidden" name="action" value="update_order">

                <div class="panel-head">
                    <div><span class="section-kicker">Mode Edit</span><h1 class="history-title"><?= e($order['ticket_code']) ?></h1></div>
                    <div class="hero-actions tiny-actions">
                        <a class="btn btn-soft" href="service_order_view.php?id=<?= e($order['id']) ?>">Batal</a>
                        <a class="btn btn-ghost" target="_blank" href="invoice.php?id=<?= e($order['id']) ?>">Print Nota</a>
                    </div>
                </div>

                <div class="detail-section">
                    <h3>Client & Perangkat</h3>
                    <div class="form-grid">
                        <label>Nama Client <input name="client_name" value="<?= e($order['client_name']) ?>" required></label>
                        <label>WhatsApp <input name="client_phone" value="<?= e($order['client_phone']) ?>"></label>
                        <label>Lokasi <input name="location" value="<?= e($order['location']) ?>"></label>
                        <label>Jenis Perangkat <input name="device_type" value="<?= e($order['device_type']) ?>" required></label>
                        <label>Brand <input name="device_brand" value="<?= e($order['device_brand']) ?>"></label>
                        <label>Model <input name="device_model" value="<?= e($order['device_model']) ?>"></label>
                        <label>Serial / IMEI <input name="serial_number" value="<?= e($order['serial_number']) ?>"></label>
                        <label>Password Perangkat <input name="device_password" value="<?= e($order['device_password']) ?>"></label>
                        <label>Kondisi Fisik <input name="physical_condition" value="<?= e($order['physical_condition']) ?>"></label>
                        <label>Garansi Resmi <input name="official_warranty" value="<?= e($order['official_warranty']) ?>"></label>
                    </div>
                    <label>Kelengkapan <textarea name="accessories" rows="2"><?= e($order['accessories']) ?></textarea></label>
                </div>

                <div class="detail-section">
                    <h3>Pengerjaan</h3>
                    <label>Keluhan <textarea name="complaint" rows="4"><?= e($order['complaint']) ?></textarea></label>
                    <div class="form-grid">
                        <label>Layanan <input name="service_name" value="<?= e($order['service_name']) ?>" required></label>
                        <label>Status Service
                            <select name="service_status">
                                <?php foreach ($statuses as $key => $label): ?><option value="<?= e($key) ?>" <?= $order['service_status']===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
                            </select>
                        </label>
                        <label>Total Harga <input name="final_amount" type="number" min="0" step="1000" value="<?= e($order['final_amount']) ?>"></label>
                        <label>DP <input name="dp_amount" type="number" min="0" step="1000" value="<?= e($order['dp_amount']) ?>"></label>
                        <label>Dibayar Manual <input name="paid_amount" type="number" min="0" step="1000" value="<?= e($order['paid_amount']) ?>"></label>
                        <label>Status Bayar <input value="<?= e($paymentStatuses[$order['payment_status']] ?? $order['payment_status']) ?>" readonly></label>
                    </div>
                    <label>Catatan Perubahan Status <textarea name="status_note" rows="2" placeholder="Contoh: Client sudah ACC harga, sparepart dipesan..."></textarea></label>
                    <label>Catatan Teknisi <textarea name="technician_notes" rows="5"><?= e($order['technician_notes']) ?></textarea></label>
                    <label>Catatan untuk Client <textarea name="customer_notes" rows="3"><?= e($order['customer_notes']) ?></textarea></label>
                </div>

                <div class="detail-section">
                    <h3>Garansi</h3>
                    <div class="form-grid">
                        <label>Garansi Hari <input name="warranty_days" type="number" min="0" step="1" value="<?= e($order['warranty_days']) ?>"></label>
                        <label>Mulai Garansi <input name="warranty_start_at" type="date" value="<?= e($order['warranty_start_at']) ?>"></label>
                        <label>Akhir Garansi <input value="<?= e($order['warranty_end_at']) ?>" readonly></label>
                        <label>Mulai/Selesai <input value="<?= e(($order['finished_at'] ?: '-') . ' / ' . ($order['picked_up_at'] ?: '-')) ?>" readonly></label>
                    </div>
                    <label>Catatan Garansi <textarea name="warranty_note" rows="2"><?= e($order['warranty_note']) ?></textarea></label>
                </div>

                <button class="btn" type="submit">Simpan Perubahan</button>
            </form>
        </section>

        <aside class="panel result-panel">
            <div class="result-sticky">
                <div class="panel-head"><div><span class="section-kicker">Aksi</span><h2>Edit Job</h2></div></div>
                <div class="kv-grid one-col">
                    <div><span>Ticket</span><b><?= e($order['ticket_code']) ?></b></div>
                    <div><span>Status</span><b><?= e($statuses[$order['service_status']] ?? $order['service_status']) ?></b></div>
                    <div><span>Total</span><b><?= money($order['final_amount']) ?></b></div>
                </div>
                <form method="post" action="delete_service_order.php" onsubmit="return confirm('Hapus job ticket ini? Pembayaran dan log untuk ticket ini juga ikut dihapus.')" class="delete-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= e($order['id']) ?>">
                    <button class="danger-btn" type="submit">Hapus Job Ticket</button>
                </form>
                <p class="mini-note">Mode edit hanya untuk memperbaiki data job. Estimasi awal tetap tersimpan di riwayat.</p>
            </div>
        </aside>
    </div>
</main>
<script>document.querySelector('#navToggle')?.addEventListener('click',()=>document.querySelector('#navLinks')?.classList.toggle('open'));</script>
</body>
</html>
