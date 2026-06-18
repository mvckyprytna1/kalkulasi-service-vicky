<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/repositories.php';
require_once __DIR__ . '/lib/service_workflow.php';
require_login();

$config = app_config();
$presets = get_active_presets();
$addons = get_active_addons();
$clients = db()->query('SELECT id, name, phone, whatsapp_normalized, address, client_type, notes, total_jobs, total_spent, last_service_at FROM clients ORDER BY last_service_at DESC, updated_at DESC, created_at DESC LIMIT 300')->fetchAll();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kalkulator - <?= e($config['app']['name']) ?></title>
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
                <div class="pill">MySQL Edition • cPanel Ready • Login Admin</div>
                <h1>Kalkulator harga service berbasis database.</h1>
                <p>Input semua kebutuhan service, sistem hitung harga aman, ideal, range client, batas nego, profit, DP, warning risiko, lalu simpan ke database MySQL.</p>
                <div class="hero-actions">
                    <a class="btn" href="#calculator">Mulai Hitung</a>
                    <a class="btn btn-ghost" href="history.php">Lihat Riwayat</a>
                </div>
            </div>
            <div class="hero-card">
                <div class="card-head"><span></span><span></span><span></span></div>
                <div class="mini-title">Output Cepat</div>
                <div class="mock-list">
                    <div><span>Harga Aman</span><b id="mockSafe">Rp0</b></div>
                    <div><span>Harga Ideal</span><b id="mockIdeal">Rp0</b></div>
                    <div><span>Range Client</span><b id="mockRange">Rp0</b></div>
                    <div><span>Status</span><b>Siap Simpan</b></div>
                </div>
            </div>
        </div>
    </section>

    <section class="calculator-section" id="calculator">
        <div class="container app-grid">
            <form class="panel form-panel" id="pricingForm">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <div class="panel-head">
                    <div><span class="section-kicker">Input</span><h2>Data Estimasi</h2></div>
                    <button class="btn btn-soft" type="button" id="resetBtn">Reset</button>
                </div>

                <details open>
                    <summary>1. Data Client</summary>
                    <div class="form-grid">
                        <label>Nama Client <input name="client_name" type="text" placeholder="Contoh: Budi" required></label>
                        <label>Nomor WhatsApp <input name="client_phone" type="text" placeholder="08xxxxxxxxxx"></label>
                        <label>Tipe Client
                            <select name="client_type" id="clientTypeSelect">
                                <?php foreach ($config['pricing_rules']['client_types'] as $key => $label): ?>
                                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small>Client baru akan otomatis masuk database client saat estimasi disimpan.</small>
                        </label>
                        <label>Lokasi <input name="location" type="text" placeholder="Contoh: Talang Bakung"></label>
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
                    <label>Catatan Client <textarea name="client_notes" rows="2" placeholder="Contoh: minta cepat, data penting, perangkat dipakai kerja..."></textarea></label>
                </details>

                <details open>
                    <summary>2. Data Perangkat</summary>
                    <div class="form-grid">
                        <label>Jenis Perangkat
                            <select name="device_type" required>
                                <?php foreach ($config['pricing_rules']['device_types'] as $device): ?>
                                    <option><?= e($device) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Brand <input name="device_brand" type="text" placeholder="ASUS / Acer / Samsung"></label>
                        <label>Model <input name="device_model" type="text" placeholder="X441U / A12 / dll"></label>
                        <label>Umur Perangkat
                            <select name="device_age">
                                <option value="<1 tahun">&lt; 1 tahun</option>
                                <option value="1-3 tahun">1 - 3 tahun</option>
                                <option value=">3 tahun">&gt; 3 tahun</option>
                                <option value="tidak tahu">Tidak tahu</option>
                            </select>
                        </label>
                        <label>Kondisi Fisik
                            <select name="physical_condition">
                                <option>Mulus</option>
                                <option>Sedang</option>
                                <option>Rusak / pernah bongkar</option>
                            </select>
                        </label>
                        <label>Garansi Resmi
                            <select name="official_warranty">
                                <option>Tidak tahu</option>
                                <option>Ada</option>
                                <option>Tidak ada</option>
                            </select>
                        </label>
                    </div>
                </details>

                <details open>
                    <summary>3. Layanan & Risiko</summary>
                    <label>Preset Layanan Utama
                        <select name="service_preset_id" id="servicePreset" required>
                            <?php foreach ($presets as $preset): ?>
                                <option value="<?= e($preset['id']) ?>"><?= e($preset['category']) ?> - <?= e($preset['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small id="presetNote">Pilih layanan untuk melihat catatan.</small>
                    </label>

                    <div class="addon-box">
                        <strong>Tambahan Layanan</strong>
                        <div class="checkbox-grid">
                            <?php foreach ($addons as $addon): ?>
                                <label class="check-card">
                                    <input type="checkbox" name="addon_ids[]" value="<?= e($addon['id']) ?>">
                                    <span><?= e($addon['name']) ?><small>+<?= money($addon['price']) ?></small></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-grid">
                        <label>Tingkat Kesulitan
                            <select name="difficulty">
                                <?php foreach ($config['pricing_rules']['difficulty_multipliers'] as $key => $item): ?>
                                    <option value="<?= e($key) ?>"><?= e($item['label']) ?> ×<?= e($item['value']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Risiko
                            <select name="risk_level" id="riskLevel">
                                <?php foreach ($config['pricing_rules']['risk_fees'] as $key => $item): ?>
                                    <option value="<?= e($key) ?>"><?= e($item['label']) ?> +<?= money($item['fee']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Urgensi
                            <select name="urgency">
                                <?php foreach ($config['pricing_rules']['urgency_fees'] as $key => $item): ?>
                                    <option value="<?= e($key) ?>"><?= e($item['label']) ?> +<?= e($item['percent']) ?>%</option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Garansi Pengerjaan
                            <select name="warranty">
                                <?php foreach ($config['pricing_rules']['warranty_fees'] as $key => $item): ?>
                                    <option value="<?= e($key) ?>"><?= e($item['label']) ?> +<?= e($item['percent']) ?>%</option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                </details>

                <details open>
                    <summary>4. Biaya Modal</summary>
                    <div class="form-grid">
                        <label>Harga Sparepart <input name="sparepart_cost" type="number" min="0" step="1000" value="0"></label>
                        <label>Ongkir Sparepart <input name="shipping_cost" type="number" min="0" step="1000" value="0"></label>
                        <label>Biaya Bahan <input name="material_cost" type="number" min="0" step="1000" value="0"></label>
                        <label>Transport <input name="transport_cost" type="number" min="0" step="1000" value="0"></label>
                        <label>Parkir / Tol <input name="parking_cost" type="number" min="0" step="1000" value="0"></label>
                        <label>Biaya Pihak Ketiga <input name="third_party_cost" type="number" min="0" step="1000" value="0"></label>
                    </div>
                </details>

                <details open>
                    <summary>5. Waktu, Margin, Diskon</summary>
                    <div class="form-grid">
                        <label>Rate Per Jam <input name="hourly_rate" type="number" min="0" step="1000" value="<?= e($config['defaults']['hourly_rate']) ?>"></label>
                        <label>Estimasi Jam Kerja <input name="work_hours" id="workHours" type="number" min="0" step="0.25" value="1"></label>
                        <label>Waktu Testing (menit) <input name="testing_minutes" type="number" min="0" step="5" value="15"></label>
                        <label>Waktu Komunikasi (menit) <input name="communication_minutes" type="number" min="0" step="5" value="10"></label>
                        <label>Margin Profit % <input name="margin_percent" type="number" min="0" step="1" value="<?= e($config['defaults']['margin_percent']) ?>"></label>
                        <label>Margin Nego Minimum % <input name="minimum_margin_percent" type="number" min="0" step="1" value="<?= e($config['defaults']['minimum_margin_percent']) ?>"></label>
                        <label>Diskon <input name="discount" type="number" min="0" step="1000" value="0"></label>
                        <label>Pembulatan <input name="rounding_base" type="number" min="1000" step="1000" value="<?= e($config['defaults']['rounding_base']) ?>"></label>
                    </div>
                </details>
            </form>

            <aside class="panel result-panel">
                <div class="result-sticky">
                    <div class="panel-head">
                        <div><span class="section-kicker">Output</span><h2>Hasil Harga</h2></div>
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
                    <label class="message-box">Pesan WhatsApp ke Client<textarea id="waMessage" rows="7" readonly></textarea></label>
                    <div class="action-grid">
                        <button class="btn" type="button" id="saveBtn">Simpan ke Database</button>
                        <button class="btn btn-ghost" type="button" id="copyBtn">Copy Pesan</button>
                        <button class="btn btn-soft" type="button" id="waBtn">Buka WhatsApp</button>
                    </div>
                    <p class="mini-note" id="saveNote">Data akan disimpan ke tabel <code>estimates</code>.</p>
                </div>
            </aside>
        </div>
    </section>
</main>

<footer class="footer"><div class="container footer-inner"><strong><?= e($config['app']['name']) ?></strong><span>© <?= date('Y') ?> - MySQL Edition</span></div></footer>

<script>
window.APP_DATA = <?= json_encode([
    'config' => $config,
    'presets' => $presets,
    'addons' => $addons,
    'clients' => $clients,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="assets/js/calculator.js?v=client-picker-fix-2"></script>
</body>
</html>
