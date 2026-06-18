<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/service_workflow.php';
require_login();
$config = app_config();
$clients = db()->query('SELECT id, name, phone, whatsapp_normalized, address, client_type, notes, total_jobs, total_spent, last_service_at FROM clients ORDER BY last_service_at DESC, updated_at DESC, created_at DESC LIMIT 300')->fetchAll();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Tambah Job Ticket - <?= e($config['app']['name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/partials_nav.php'; ?>
<main class="history-page">
    <div class="container">
        <form class="panel wide-panel" method="post" action="store_service_order.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="panel-head">
                <div><span class="section-kicker">Manual Intake</span><h1 class="history-title">Tambah Job Ticket</h1></div>
                <a class="btn btn-soft" href="service_orders.php">Kembali</a>
            </div>

            <details open><summary>1. Client</summary>
                <div class="form-grid">
                    <label>Nama Client <input name="client_name" required></label>
                    <label>WhatsApp <input name="client_phone" placeholder="08xxxxxxxxxx"></label>
                    <label>Tipe Client
                        <select name="client_type" id="clientTypeSelect">
                            <?php foreach ($config['pricing_rules']['client_types'] as $key => $label): ?>
                                <option value="<?= e($key) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Lokasi <input name="location" placeholder="Alamat / area"></label>
                    <label id="existingClientWrap">
                            Pilih Client Langganan / Kantor
                            <select name="existing_client_id" id="existingClientSelect">
                                <option value="">Pilih dari database client...</option>
                                <?php foreach ($clients as $client): ?>
                                    <option
                                        value="<?= e($client['id']) ?>"
                                        data-name="<?= e($client['name']) ?>"
                                        data-phone="<?= e($client['phone']) ?>"
                                        data-address="<?= e($client['address']) ?>"
                                        data-type="<?= e($client['client_type']) ?>"
                                        data-notes="<?= e($client['notes']) ?>"
                                        <?= (string)'' === (string)$client['id'] ? 'selected' : '' ?>
                                    >
                                        <?= e($client['name']) ?><?= $client['phone'] ? ' • ' . e($client['phone']) : '' ?><?= $client['address'] ? ' • ' . e($client['address']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small>Pakai ini kalau client sudah pernah masuk database. Data nama, WA, lokasi, dan catatan akan otomatis diisi.</small>
                        </label>
                </div>
                <label>Catatan Client <textarea name="client_notes" rows="2" placeholder="Catatan internal tentang client..."></textarea></label>
            </details>

            <details open><summary>2. Perangkat & Kelengkapan</summary>
                <div class="form-grid">
                    <label>Jenis Perangkat <input name="device_type" required placeholder="Laptop / PC / Android"></label>
                    <label>Brand <input name="device_brand" placeholder="Acer / ASUS / Samsung"></label>
                    <label>Model <input name="device_model" placeholder="Aspire / X441U / dll"></label>
                    <label>Serial / IMEI <input name="serial_number" placeholder="Opsional"></label>
                    <label>Password Perangkat <input name="device_password" placeholder="Opsional, simpan hati-hati"></label>
                    <label>Kondisi Fisik <input name="physical_condition" placeholder="Mulus / sedang / rusak"></label>
                    <label>Garansi Resmi <input name="official_warranty" placeholder="Ada / tidak ada / tidak tahu"></label>
                    <label>Kelengkapan <input name="accessories" placeholder="Charger, tas, dus, SIM tray, dll"></label>
                </div>
            </details>

            <details open><summary>3. Keluhan & Pengerjaan</summary>
                <label>Keluhan <textarea name="complaint" rows="4" placeholder="Tulis keluhan client sedetail mungkin..."></textarea></label>
                <div class="form-grid">
                    <label>Layanan <input name="service_name" required placeholder="Contoh: Perbaikan Driver Laptop"></label>
                    <label>Status Service
                        <select name="service_status">
                            <?php foreach (service_statuses() as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <label>Total Harga <input name="final_amount" type="number" min="0" step="1000" value="0"></label>
                    <label>DP Disarankan <input name="dp_amount" type="number" min="0" step="1000" value="0"></label>
                    <label>Sudah Dibayar <input name="paid_amount" type="number" min="0" step="1000" value="0"></label>
                    <label>Garansi Hari <input name="warranty_days" type="number" min="0" step="1" value="0"></label>
                    <label>Mulai Garansi <input name="warranty_start_at" type="date"></label>
                    <label>Catatan Garansi <input name="warranty_note" placeholder="Contoh: garansi software 7 hari"></label>
                </div>
                <label>Catatan Teknisi <textarea name="technician_notes" rows="4" placeholder="Catatan internal, jangan dikirim ke client..."></textarea></label>
                <label>Catatan untuk Client <textarea name="customer_notes" rows="3" placeholder="Catatan yang aman dikirim ke client..."></textarea></label>
            </details>

            <button class="btn" type="submit">Simpan Job Ticket</button>
        </form>
    </div>
</main>
<script>
document.querySelector('#navToggle')?.addEventListener('click',()=>document.querySelector('#navLinks')?.classList.toggle('open'));

const clientTypeSelect = document.querySelector('#clientTypeSelect');
const existingClientWrap = document.querySelector('#existingClientWrap');
const existingClientSelect = document.querySelector('#existingClientSelect');

function setField(name, value) {
    const field = document.querySelector(`[name="${name}"]`);
    if (field && value !== undefined && value !== null) field.value = value;
}

function updatePicker() {
    if (!existingClientWrap || !clientTypeSelect) return;
    existingClientWrap.style.display = ['langganan', 'kantor'].includes(clientTypeSelect.value) ? '' : 'none';
}

clientTypeSelect?.addEventListener('change', () => {
    updatePicker();
    if (clientTypeSelect.value === 'baru' && existingClientSelect) existingClientSelect.value = '';
});

existingClientSelect?.addEventListener('change', () => {
    const opt = existingClientSelect.selectedOptions[0];
    if (!opt || !opt.value) return;
    setField('client_name', opt.dataset.name || '');
    setField('client_phone', opt.dataset.phone || '');
    setField('location', opt.dataset.address || '');
    setField('client_type', 'langganan');
    const notes = document.querySelector('[name="client_notes"]');
    if (notes && !notes.value.trim()) notes.value = opt.dataset.notes || '';
    updatePicker();
});

updatePicker();
</script>
</body>
</html>
