<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/repositories.php';
require_once __DIR__ . '/lib/service_workflow.php';
require_login();

$config = app_config();
$presets = get_active_presets();
$addons = get_active_addons();
$clients = db()->query('SELECT id, name, phone, whatsapp_normalized, address, client_type, notes, total_jobs, total_spent, last_service_at FROM clients ORDER BY last_service_at DESC, updated_at DESC, created_at DESC LIMIT 300')->fetchAll();

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM estimates WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$estimate = $stmt->fetch();

if (!$estimate) {
    http_response_code(404);
    echo 'Estimasi tidak ditemukan.';
    exit;
}

$serviceOrder = get_service_order_by_estimate((int)$estimate['id']);
$selectedClientId = $serviceOrder['client_id'] ?? null;
if (!$selectedClientId && !empty($estimate['client_phone'])) {
    $wa = normalize_wa_number($estimate['client_phone']);
    if ($wa !== '') {
        $stmtClient = db()->prepare('SELECT id FROM clients WHERE whatsapp_normalized = ? LIMIT 1');
        $stmtClient->execute([$wa]);
        $selectedClientId = $stmtClient->fetchColumn() ?: null;
    }
}
$addonNames = json_decode($estimate['addon_json'] ?? '[]', true) ?: [];
$addonNameLookup = array_fill_keys(array_map('strval', $addonNames), true);

$deviceOptions = $config['pricing_rules']['device_types']
    ?? $config['device_types']
    ?? ['Laptop', 'PC / Komputer', 'Android / HP', 'Printer', 'Jaringan', 'Lainnya'];

$currentDeviceType = trim((string)($estimate['device_type'] ?? ''));
if ($currentDeviceType !== '' && !in_array($currentDeviceType, $deviceOptions, true)) {
    array_unshift($deviceOptions, $currentDeviceType);
}

function sel($a, $b): string { return (string)$a === (string)$b ? 'selected' : ''; }
function chk($cond): string { return $cond ? 'checked' : ''; }
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Revisi Estimasi - <?= e($estimate['estimate_code']) ?></title>
    <meta name="description" content="<?= e($config['app']['description']) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f172a">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/partials_nav.php'; ?>

<main>
    <section class="hero compact">
        <div class="container hero-grid">
            <div>
                <div class="pill">Revision Mode • Setelah Cek Barang • Sinkron Job Ticket</div>
                <h1>Revisi estimasi tanpa bikin data mentok.</h1>
                <p>
                    Pakai halaman ini kalau awalnya cuma cek/diagnosa, lalu setelah dibongkar atau dicek ternyata
                    ada layanan tambahan, sparepart, risiko, atau harga final baru.
                </p>
                <div class="hero-actions">
                    <a class="btn btn-ghost" href="estimate_view.php?id=<?= e($estimate['id']) ?>">Kembali Detail</a>
                    <?php if ($serviceOrder): ?>
                        <a class="btn btn-soft" href="service_order_view.php?id=<?= e($serviceOrder['id']) ?>">Buka Job Ticket</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="hero-card">
                <div class="card-head"><span></span><span></span><span></span></div>
                <div class="mini-title">Harga Saat Ini</div>
                <div class="mock-list">
                    <div><span>Minimal Aman</span><b><?= money($estimate['minimal_price']) ?></b></div>
                    <div><span>Harga Ideal</span><b><?= money($estimate['ideal_price']) ?></b></div>
                    <div><span>Range Client</span><b><?= money($estimate['range_low']) ?> - <?= money($estimate['range_high']) ?></b></div>
                    <div><span>Status</span><b><?= e(strtoupper($estimate['status'])) ?></b></div>
                </div>
            </div>
        </div>
    </section>

    <section class="calculator-section" id="calculator">
        <div class="container app-grid">
            <form class="panel form-panel" id="pricingForm">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="estimate_id" value="<?= e($estimate['id']) ?>">

                <div class="panel-head">
                    <div><span class="section-kicker">Edit</span><h2>Revisi Estimasi</h2></div>
                    <button class="btn btn-soft" type="button" id="resetBtn">Reset ke Data Awal</button>
                </div>

                <?php if ($serviceOrder): ?>
                    <div class="alert success">
                        Estimasi ini sudah punya job ticket <b><?= e($serviceOrder['ticket_code']) ?></b>.
                        Setelah revisi disimpan, harga dan layanan di job ticket ikut disinkronkan.
                    </div>
                <?php else: ?>
                    <div class="alert">
                        Estimasi ini belum punya job ticket. Revisi dulu harga/layanan, lalu buat job ticket jika client sudah titip barang / ACC pengerjaan.
                    </div>
                <?php endif; ?>

                <details open>
                    <summary>1. Data Client</summary>
                    <div class="form-grid">
                        <label>Nama Client <input name="client_name" type="text" value="<?= e($estimate['client_name']) ?>" required></label>
                        <label>Nomor WhatsApp <input name="client_phone" type="text" value="<?= e($estimate['client_phone']) ?>"></label>
                        <label>Tipe Client
                            <select name="client_type" id="clientTypeSelect">
                                <?php foreach ($config['pricing_rules']['client_types'] as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= sel($estimate['client_type'], $key) ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small>Client baru akan otomatis masuk database client saat revisi disimpan.</small>
                        </label>
                        <label>Lokasi <input name="location" type="text" value="<?= e($estimate['location']) ?>"></label>
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
                                        <?= (string)$selectedClientId === (string)$client['id'] ? 'selected' : '' ?>
                                    >
                                        <?= e($client['name']) ?><?= $client['phone'] ? ' • ' . e($client['phone']) : '' ?><?= $client['address'] ? ' • ' . e($client['address']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small>Pakai ini kalau client sudah pernah masuk database. Data nama, WA, lokasi, dan catatan akan otomatis diisi.</small>
                        </label>
                    </div>
                    <label>Catatan / Keluhan Client
                        <textarea name="client_notes" rows="2" placeholder="Contoh: awalnya cek 30K, setelah dicek ternyata keyboard rusak..."><?= e($estimate['client_notes']) ?></textarea>
                    </label>
                </details>

                <details open>
                    <summary>2. Data Perangkat</summary>
                    <div class="form-grid">
                        <label>Jenis Perangkat
                            <select name="device_type" required>
                                <?php foreach ($deviceOptions as $device): ?>
                                    <option value="<?= e($device) ?>" <?= sel($estimate['device_type'], $device) ?>><?= e($device) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Brand <input name="device_brand" type="text" value="<?= e($estimate['device_brand']) ?>"></label>
                        <label>Model <input name="device_model" type="text" value="<?= e($estimate['device_model']) ?>"></label>
                        <label>Umur Perangkat
                            <select name="device_age">
                                <?php foreach (['<1 tahun','1-3 tahun','>3 tahun','tidak tahu'] as $age): ?>
                                    <option value="<?= e($age) ?>" <?= sel($estimate['device_age'], $age) ?>><?= e($age) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Kondisi Fisik
                            <select name="physical_condition">
                                <?php foreach (['Mulus','Sedang','Rusak / pernah bongkar'] as $condition): ?>
                                    <option value="<?= e($condition) ?>" <?= sel($estimate['physical_condition'], $condition) ?>><?= e($condition) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Garansi Resmi
                            <select name="official_warranty">
                                <?php foreach (['Tidak tahu','Ada','Tidak ada'] as $ow): ?>
                                    <option value="<?= e($ow) ?>" <?= sel($estimate['official_warranty'], $ow) ?>><?= e($ow) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                </details>

                <details open>
                    <summary>3. Layanan & Risiko</summary>
                    <label>Preset Layanan Utama
                        <select name="service_preset_id" id="servicePreset" required>
                            <?php foreach ($presets as $preset): ?>
                                <option value="<?= e($preset['id']) ?>" <?= sel($estimate['service_preset_id'], $preset['id']) ?>>
                                    <?= e($preset['category']) ?> - <?= e($preset['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small id="presetNote">Ubah preset dari cek awal ke layanan final jika sudah ketemu kerusakan.</small>
                    </label>

                    <div class="addon-box">
                        <strong>Tambahan Layanan</strong>
                        <div class="checkbox-grid">
                            <?php foreach ($addons as $addon): ?>
                                <label class="check-card">
                                    <input type="checkbox" name="addon_ids[]" value="<?= e($addon['id']) ?>" <?= chk(isset($addonNameLookup[(string)$addon['name']])) ?>>
                                    <span><?= e($addon['name']) ?><small>+<?= money($addon['price']) ?></small></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-grid">
                        <label>Tingkat Kesulitan
                            <select name="difficulty">
                                <?php foreach ($config['pricing_rules']['difficulty_multipliers'] as $key => $item): ?>
                                    <option value="<?= e($key) ?>" <?= sel($estimate['difficulty'], $key) ?>><?= e($item['label']) ?> ×<?= e($item['value']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Risiko
                            <select name="risk_level" id="riskLevel">
                                <?php foreach ($config['pricing_rules']['risk_fees'] as $key => $item): ?>
                                    <option value="<?= e($key) ?>" <?= sel($estimate['risk_level'], $key) ?>><?= e($item['label']) ?> +<?= money($item['fee']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Urgensi
                            <select name="urgency">
                                <?php foreach ($config['pricing_rules']['urgency_fees'] as $key => $item): ?>
                                    <option value="<?= e($key) ?>" <?= sel($estimate['urgency'], $key) ?>><?= e($item['label']) ?> +<?= e($item['percent']) ?>%</option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Garansi Pengerjaan
                            <select name="warranty">
                                <?php foreach ($config['pricing_rules']['warranty_fees'] as $key => $item): ?>
                                    <option value="<?= e($key) ?>" <?= sel($estimate['warranty'], $key) ?>><?= e($item['label']) ?> +<?= e($item['percent']) ?>%</option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                </details>

                <details open>
                    <summary>4. Biaya Modal</summary>
                    <div class="form-grid">
                        <label>Harga Sparepart <input name="sparepart_cost" type="number" min="0" step="1000" value="<?= e($estimate['sparepart_cost']) ?>"></label>
                        <label>Ongkir Sparepart <input name="shipping_cost" type="number" min="0" step="1000" value="<?= e($estimate['shipping_cost']) ?>"></label>
                        <label>Biaya Bahan <input name="material_cost" type="number" min="0" step="1000" value="<?= e($estimate['material_cost']) ?>"></label>
                        <label>Transport <input name="transport_cost" type="number" min="0" step="1000" value="<?= e($estimate['transport_cost']) ?>"></label>
                        <label>Parkir / Tol <input name="parking_cost" type="number" min="0" step="1000" value="<?= e($estimate['parking_cost']) ?>"></label>
                        <label>Biaya Pihak Ketiga <input name="third_party_cost" type="number" min="0" step="1000" value="<?= e($estimate['third_party_cost']) ?>"></label>
                    </div>
                </details>

                <details open>
                    <summary>5. Waktu, Margin, Diskon</summary>
                    <div class="form-grid">
                        <label>Rate Per Jam <input name="hourly_rate" type="number" min="0" step="1000" value="<?= e($estimate['hourly_rate']) ?>"></label>
                        <label>Estimasi Jam Kerja <input name="work_hours" id="workHours" type="number" min="0" step="0.25" value="<?= e($estimate['work_hours']) ?>"></label>
                        <label>Waktu Testing (menit) <input name="testing_minutes" type="number" min="0" step="5" value="<?= e($estimate['testing_minutes']) ?>"></label>
                        <label>Waktu Komunikasi (menit) <input name="communication_minutes" type="number" min="0" step="5" value="<?= e($estimate['communication_minutes']) ?>"></label>
                        <label>Margin Profit % <input name="margin_percent" type="number" min="0" step="1" value="<?= e($estimate['margin_percent']) ?>"></label>
                        <label>Margin Nego Minimum % <input name="minimum_margin_percent" type="number" min="0" step="1" value="<?= e($estimate['minimum_margin_percent']) ?>"></label>
                        <label>Diskon <input name="discount" type="number" min="0" step="1000" value="<?= e($estimate['discount']) ?>"></label>
                        <label>Pembulatan <input name="rounding_base" type="number" min="1000" step="1000" value="<?= e($estimate['rounding_base']) ?>"></label>
                    </div>
                </details>
            </form>

            <aside class="panel result-panel">
                <div class="result-sticky">
                    <div class="panel-head">
                        <div><span class="section-kicker">Output</span><h2>Hasil Revisi</h2></div>
                        <span class="status-badge" id="riskBadge">Risiko: -</span>
                    </div>
                    <div class="price-grid">
                        <div class="price-card safe"><span>Harga Minimal Aman</span><strong id="minimalPrice">Rp0</strong></div>
                        <div class="price-card ideal"><span>Harga Ideal</span><strong id="idealPrice">Rp0</strong></div>
                        <div class="price-card"><span>Range ke Client</span><strong id="clientRange">Rp0 - Rp0</strong></div>
                        <div class="price-card"><span>Batas Nego</span><strong id="negoPrice">Rp0</strong></div>
                    </div>
                    <div class="breakdown"><h3>Breakdown</h3><div id="breakdownList"></div></div>
                    <div class="profit-box"><div><span>Estimasi Profit</span><strong id="profitText">Rp0</strong></div><div><span>Saran DP</span><strong id="dpText">Rp0</strong></div></div>
                    <div class="warning-box" id="warningBox"></div>
                    <label class="message-box">Pesan WhatsApp Revisi<textarea id="waMessage" rows="7" readonly></textarea></label>
                    <div class="action-grid">
                        <button class="btn" type="button" id="saveBtn">Simpan Revisi Estimasi</button>
                        <button class="btn btn-ghost" type="button" id="copyBtn">Copy Pesan</button>
                        <button class="btn btn-soft" type="button" id="waBtn">Buka WhatsApp</button>
                    </div>
                    <p class="mini-note" id="saveNote">Revisi akan memperbarui estimasi ini<?= $serviceOrder ? ' dan sinkron ke job ticket terkait' : '' ?>.</p>
                </div>
            </aside>
        </div>
    </section>
</main>

<script>
window.APP_DATA = <?= json_encode([
    'config' => $config,
    'presets' => $presets,
    'addons' => $addons,
    'clients' => $clients,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.EDIT_ESTIMATE = {
    id: <?= (int)$estimate['id'] ?>,
    code: <?= json_encode($estimate['estimate_code']) ?>
};
</script>
<script src="assets/js/calculator.js?v=client-picker-fix-2"></script>
</body>
</html>
