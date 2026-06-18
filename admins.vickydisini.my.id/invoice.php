<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/service_workflow.php';

require_login();

$id = (int)($_GET['id'] ?? 0);
$order = get_service_order($id);

if (!$order) {
    http_response_code(404);
    echo 'Nota tidak ditemukan.';
    exit;
}

$config = app_config();
$app = $config['app'];
$statuses = service_statuses();
$paymentStatuses = payment_statuses();
$payments = get_order_payments((int)$order['id']);

$sisa = max(0, (int)$order['final_amount'] - (int)$order['paid_amount']);
$device = trim(implode(' ', array_filter([
    $order['device_type'] ?? '',
    $order['device_brand'] ?? '',
    $order['device_model'] ?? '',
])));
$invoiceNo = 'INV-' . preg_replace('/^SRV-/', '', (string)$order['ticket_code']);
$issuedAt = date('d/m/Y H:i');
$statusBayar = $paymentStatuses[$order['payment_status']] ?? $order['payment_status'];
$serviceStatus = $statuses[$order['service_status']] ?? $order['service_status'];
$wa = normalize_wa_number($order['client_phone'] ?? '');
$addons = json_decode($order['addon_json'] ?? '[]', true);
$addons = is_array($addons) ? array_values(array_filter($addons)) : [];

$warrantyText = ((int)$order['warranty_days']) . ' hari';
if (!empty($order['warranty_end_at'])) {
    $warrantyText .= ' s/d ' . date('d/m/Y', strtotime($order['warranty_end_at']));
}

$paymentBadgeClass = match ($order['payment_status']) {
    'lunas' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    'dp' => 'bg-blue-50 text-blue-700 ring-blue-200',
    'refund' => 'bg-amber-50 text-amber-700 ring-amber-200',
    default => 'bg-rose-50 text-rose-700 ring-rose-200',
};

$serviceBadgeClass = match ($order['service_status']) {
    'selesai', 'sudah_diambil' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    'dikerjakan', 'dicek', 'menunggu_sparepart' => 'bg-blue-50 text-blue-700 ring-blue-200',
    'menunggu_acc' => 'bg-amber-50 text-amber-700 ring-amber-200',
    'batal' => 'bg-rose-50 text-rose-700 ring-rose-200',
    default => 'bg-slate-50 text-slate-700 ring-slate-200',
};

$lineDescParts = [];
if (!empty($order['complaint'])) {
    $lineDescParts[] = 'Keluhan: ' . $order['complaint'];
}
if ($addons) {
    $lineDescParts[] = 'Tambahan: ' . implode(', ', $addons);
}
$lineDescription = implode("\n", $lineDescParts) ?: 'Jasa service/perbaikan perangkat';

$lineItems = [
    [
        'name' => $order['service_name'] ?: 'Jasa Service Perangkat',
        'desc' => $lineDescription,
        'qty' => 1,
        'price' => (int)$order['final_amount'],
    ],
];

function invoice_initials(string $name): string
{
    $words = preg_split('/\s+/', trim($name));
    $letters = '';
    foreach ($words as $word) {
        if ($word !== '') {
            $letters .= mb_substr($word, 0, 1);
        }
        if (mb_strlen($letters) >= 2) {
            break;
        }
    }
    return mb_strtoupper($letters ?: 'NC');
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Nota <?= e($invoiceNo) ?> - <?= e($app['name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f172a">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'sans-serif'],
                    },
                    boxShadow: {
                        premium: '0 24px 80px rgba(15, 23, 42, .14)',
                        soft: '0 12px 36px rgba(15, 23, 42, .08)',
                    }
                }
            }
        }
    </script>
    <style>
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .invoice-paper { width: min(1080px, calc(100% - 24px)); }
        .invoice-grid-bg {
            background-image:
                radial-gradient(circle at 18% 10%, rgba(37, 99, 235, .18), transparent 28rem),
                radial-gradient(circle at 88% 12%, rgba(16, 185, 129, .16), transparent 26rem),
                linear-gradient(135deg, #0f172a 0%, #111827 45%, #1e3a8a 100%);
        }
        .invoice-watermark {
            position: absolute;
            inset: auto 24px 18px auto;
            font-size: clamp(48px, 9vw, 112px);
            line-height: 1;
            letter-spacing: -.08em;
            font-weight: 950;
            opacity: .055;
            user-select: none;
            pointer-events: none;
        }
        .signature-script {
            font-family: "Segoe Script", "Brush Script MT", cursive;
        }
        @media print {
            @page { size: A4; margin: 10mm; }
            body { background: #fff !important; }
            .no-print { display: none !important; }
            .invoice-paper { width: 100% !important; margin: 0 !important; box-shadow: none !important; border-radius: 0 !important; border: 0 !important; }
            .print-avoid { break-inside: avoid; page-break-inside: avoid; }
            .print-compact { padding: 18px !important; }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-950 antialiased">
    <div class="no-print sticky top-0 z-50 border-b border-slate-200/80 bg-white/85 backdrop-blur-xl">
        <div class="mx-auto flex w-[min(1080px,calc(100%-24px))] flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-[.22em] text-blue-700">Preview Nota</p>
                <h1 class="text-xl font-black tracking-tight text-slate-950 sm:text-2xl"><?= e($app['name']) ?> Invoice</h1>
                <p class="text-sm text-slate-500">Cek detail nota sebelum dicetak atau disimpan sebagai PDF.</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <button id="printBtn" type="button" class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-blue-600 px-5 font-black text-white shadow-soft transition hover:-translate-y-0.5 hover:bg-blue-700 active:translate-y-0">
                    <span class="btn-label">Cetak / Simpan PDF</span>
                    <span class="btn-loading hidden items-center gap-2">
                        <span class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                        Menyiapkan...
                    </span>
                </button>
                <a href="service_order_view.php?id=<?= e($order['id']) ?>" class="inline-flex min-h-12 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 font-black text-slate-700 shadow-sm transition hover:-translate-y-0.5 hover:bg-slate-50 active:translate-y-0">
                    Kembali
                </a>
            </div>
        </div>
    </div>

    <main class="py-6 sm:py-10">
        <section class="invoice-paper relative mx-auto overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-premium">
            <div class="invoice-watermark"><?= e(strtoupper($order['payment_status'] === 'lunas' ? 'PAID' : 'INVOICE')) ?></div>

            <header class="invoice-grid-bg print-compact relative overflow-hidden px-6 py-8 text-white sm:px-10 sm:py-10">
                <div class="absolute -right-24 -top-24 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
                <div class="absolute -bottom-32 left-1/3 h-72 w-72 rounded-full bg-emerald-400/10 blur-3xl"></div>

                <div class="relative grid gap-8 lg:grid-cols-[1.1fr_.9fr] lg:items-start">
                    <div>
                        <div class="flex items-start gap-4">
                            <div class="grid h-16 w-16 shrink-0 place-items-center rounded-3xl border border-white/20 bg-white/10 text-xl font-black shadow-soft backdrop-blur">
                                <?= e(invoice_initials($app['name'])) ?>
                            </div>
                            <div>
                                <h2 class="text-2xl font-black tracking-tight sm:text-3xl"><?= e($app['name']) ?></h2>
                                <p class="mt-1 max-w-xl text-sm leading-6 text-white/72"><?= e($app['tagline']) ?></p>
                                <div class="mt-4 flex flex-wrap gap-2 text-xs font-bold text-white/80">
                                    <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1">WhatsApp: <?= e($app['whatsapp']) ?></span>
                                    <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1"><?= e($app['email']) ?></span>
                                    <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1"><?= e($app['location']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[1.5rem] border border-white/15 bg-white/10 p-5 shadow-soft backdrop-blur">
                        <p class="text-xs font-black uppercase tracking-[.22em] text-blue-100">Nota Service</p>
                        <h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl"><?= e($invoiceNo) ?></h1>
                        <div class="mt-5 grid gap-3 text-sm">
                            <div class="flex justify-between gap-4 border-b border-white/10 pb-3">
                                <span class="text-white/65">Tanggal</span>
                                <b><?= e($issuedAt) ?></b>
                            </div>
                            <div class="flex justify-between gap-4 border-b border-white/10 pb-3">
                                <span class="text-white/65">Ticket</span>
                                <b><?= e($order['ticket_code']) ?></b>
                            </div>
                            <div class="flex flex-wrap justify-end gap-2">
                                <span class="rounded-full bg-white px-3 py-1 text-xs font-black text-slate-900"><?= e($statusBayar) ?></span>
                                <span class="rounded-full bg-emerald-400/15 px-3 py-1 text-xs font-black text-emerald-100 ring-1 ring-emerald-300/20"><?= e($serviceStatus) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="print-compact p-6 sm:p-10">
                <section class="grid gap-4 lg:grid-cols-3">
                    <div class="rounded-3xl border border-slate-200 bg-slate-50/80 p-5">
                        <p class="text-xs font-black uppercase tracking-[.18em] text-blue-700">Pelanggan</p>
                        <h3 class="mt-3 text-lg font-black tracking-tight"><?= e($order['client_name']) ?></h3>
                        <div class="mt-3 space-y-2 text-sm text-slate-600">
                            <p><span class="font-bold text-slate-900">WA:</span> <?= e($order['client_phone'] ?: '-') ?></p>
                            <?php if ($wa): ?><p><span class="font-bold text-slate-900">Format:</span> <?= e($wa) ?></p><?php endif; ?>
                            <p><span class="font-bold text-slate-900">Lokasi:</span> <?= e($order['location'] ?: '-') ?></p>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-slate-50/80 p-5">
                        <p class="text-xs font-black uppercase tracking-[.18em] text-blue-700">Perangkat</p>
                        <h3 class="mt-3 text-lg font-black tracking-tight"><?= e($device ?: '-') ?></h3>
                        <div class="mt-3 space-y-2 text-sm text-slate-600">
                            <p><span class="font-bold text-slate-900">Kondisi:</span> <?= e($order['physical_condition'] ?: '-') ?></p>
                            <p><span class="font-bold text-slate-900">Garansi resmi:</span> <?= e($order['official_warranty'] ?: '-') ?></p>
                            <p><span class="font-bold text-slate-900">Kelengkapan:</span> <?= e($order['accessories'] ?: '-') ?></p>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-slate-50/80 p-5">
                        <p class="text-xs font-black uppercase tracking-[.18em] text-blue-700">Status</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="rounded-full px-3 py-1 text-xs font-black ring-1 <?= e($serviceBadgeClass) ?>"><?= e($serviceStatus) ?></span>
                            <span class="rounded-full px-3 py-1 text-xs font-black ring-1 <?= e($paymentBadgeClass) ?>"><?= e($statusBayar) ?></span>
                        </div>
                        <div class="mt-4 space-y-2 text-sm text-slate-600">
                            <p><span class="font-bold text-slate-900">Diterima:</span> <?= e($order['received_at'] ?: '-') ?></p>
                            <p><span class="font-bold text-slate-900">Selesai:</span> <?= e($order['finished_at'] ?: '-') ?></p>
                        </div>
                    </div>
                </section>

                <section class="print-avoid mt-6 grid gap-4 lg:grid-cols-2">
                    <div class="rounded-3xl border border-slate-200 p-5">
                        <p class="text-xs font-black uppercase tracking-[.18em] text-slate-500">Detail Kerusakan / Keluhan</p>
                        <p class="mt-3 whitespace-pre-line text-sm leading-7 text-slate-700"><?= e($order['complaint'] ?: 'Belum ada detail keluhan tertulis.') ?></p>
                    </div>
                    <div class="rounded-3xl border border-slate-200 p-5">
                        <p class="text-xs font-black uppercase tracking-[.18em] text-slate-500">Diagnosa / Catatan Teknisi</p>
                        <p class="mt-3 whitespace-pre-line text-sm leading-7 text-slate-700"><?= e($order['technician_notes'] ?: 'Diagnosa teknisi belum ditambahkan.') ?></p>
                    </div>
                </section>

                <section class="print-avoid mt-6 overflow-hidden rounded-3xl border border-slate-200">
                    <div class="grid grid-cols-12 bg-slate-950 px-5 py-4 text-xs font-black uppercase tracking-[.14em] text-white">
                        <div class="col-span-7">Layanan</div>
                        <div class="col-span-1 text-center">Qty</div>
                        <div class="col-span-2 text-right">Harga</div>
                        <div class="col-span-2 text-right">Subtotal</div>
                    </div>
                    <?php foreach ($lineItems as $item): ?>
                        <div class="grid grid-cols-12 gap-y-2 border-b border-slate-100 px-5 py-5 text-sm last:border-b-0">
                            <div class="col-span-12 sm:col-span-7">
                                <b class="text-base text-slate-950"><?= e($item['name']) ?></b>
                                <p class="mt-1 whitespace-pre-line leading-6 text-slate-500"><?= e($item['desc']) ?></p>
                            </div>
                            <div class="col-span-4 text-left font-bold text-slate-700 sm:col-span-1 sm:text-center"><?= e($item['qty']) ?></div>
                            <div class="col-span-4 text-center font-bold text-slate-700 sm:col-span-2 sm:text-right"><?= money($item['price']) ?></div>
                            <div class="col-span-4 text-right font-black text-slate-950 sm:col-span-2"><?= money($item['price'] * $item['qty']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </section>

                <section class="mt-6 grid gap-6 lg:grid-cols-[1fr_360px]">
                    <div class="space-y-4">
                        <div class="rounded-3xl border border-amber-200 bg-amber-50 p-5">
                            <p class="text-xs font-black uppercase tracking-[.18em] text-amber-700">Catatan untuk Pelanggan</p>
                            <p class="mt-3 whitespace-pre-line text-sm leading-7 text-amber-900"><?= e($order['customer_notes'] ?: 'Terima kasih sudah menggunakan layanan NusaTech Care. Simpan nota ini sebagai bukti transaksi dan klaim garansi jika ada.') ?></p>
                        </div>
                        <div class="rounded-3xl border border-slate-200 bg-white p-5 text-xs leading-6 text-slate-500">
                            <b class="text-slate-900">Syarat garansi:</b>
                            garansi mengikuti scope pengerjaan dan catatan teknisi. Garansi tidak berlaku untuk kerusakan baru, cairan, jatuh, human error, modifikasi pihak lain, atau sparepart di luar scope service.
                            <?php if (!empty($order['warranty_note'])): ?>
                                <br><b class="text-slate-900">Catatan garansi:</b> <?= nl2br(e($order['warranty_note'])) ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <aside class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                        <p class="text-xs font-black uppercase tracking-[.18em] text-blue-700">Ringkasan Biaya</p>
                        <div class="mt-4 space-y-3 text-sm">
                            <div class="flex justify-between gap-4 border-b border-dashed border-slate-200 pb-3">
                                <span class="text-slate-500">Total Service</span>
                                <b><?= money($order['final_amount']) ?></b>
                            </div>
                            <div class="flex justify-between gap-4 border-b border-dashed border-slate-200 pb-3">
                                <span class="text-slate-500">DP Disarankan</span>
                                <b><?= money($order['dp_amount']) ?></b>
                            </div>
                            <div class="flex justify-between gap-4 border-b border-dashed border-slate-200 pb-3">
                                <span class="text-slate-500">Sudah Dibayar</span>
                                <b><?= money($order['paid_amount']) ?></b>
                            </div>
                            <div class="flex justify-between gap-4 border-b border-dashed border-slate-200 pb-3">
                                <span class="text-slate-500">Sisa Bayar</span>
                                <b class="<?= $sisa > 0 ? 'text-rose-600' : 'text-emerald-600' ?>"><?= money($sisa) ?></b>
                            </div>
                            <div class="flex justify-between gap-4 border-b border-dashed border-slate-200 pb-3">
                                <span class="text-slate-500">Garansi</span>
                                <b class="text-right"><?= e($warrantyText) ?></b>
                            </div>
                            <div class="flex items-end justify-between gap-4 pt-2">
                                <span class="text-base font-black text-slate-900">Grand Total</span>
                                <b class="text-2xl font-black tracking-tight text-blue-700"><?= money($order['final_amount']) ?></b>
                            </div>
                            <div class="mt-4 inline-flex rounded-full px-4 py-2 text-xs font-black ring-1 <?= e($paymentBadgeClass) ?>">
                                <?= e(strtoupper($statusBayar)) ?>
                            </div>
                        </div>

                        <?php if ($payments): ?>
                            <div class="mt-5 rounded-2xl bg-white p-4">
                                <p class="text-xs font-black uppercase tracking-[.14em] text-slate-500">Riwayat Pembayaran</p>
                                <div class="mt-3 space-y-2 text-xs text-slate-600">
                                    <?php foreach ($payments as $pay): ?>
                                        <div class="flex justify-between gap-3">
                                            <span><?= e($pay['paid_at']) ?> • <?= e($pay['method'] ?: '-') ?></span>
                                            <b class="text-slate-950"><?= money($pay['amount']) ?></b>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </aside>
                </section>

                <section class="print-avoid mt-10 grid gap-6 sm:grid-cols-2">
                    <div class="rounded-3xl border border-slate-200 p-5 text-center">
                        <p class="text-xs font-black uppercase tracking-[.18em] text-slate-500">Pelanggan</p>
                        <div class="mx-auto mt-8 h-16 max-w-[220px] border-b border-slate-300"></div>
                        <p class="mt-3 font-black"><?= e($order['client_name']) ?></p>
                    </div>
                    <div class="rounded-3xl border border-blue-100 bg-blue-50/50 p-5 text-center">
                        <p class="text-xs font-black uppercase tracking-[.18em] text-blue-700">Teknisi / Admin</p>
                        <div class="signature-script mt-6 text-3xl text-blue-700"><?= e($app['name']) ?></div>
                        <div class="mx-auto mt-2 h-8 max-w-[220px] border-b border-blue-200"></div>
                        <p class="mt-3 font-black"><?= e($app['name']) ?></p>
                    </div>
                </section>

                <footer class="mt-8 flex flex-col gap-2 border-t border-slate-200 pt-5 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                    <span><?= e($app['name']) ?> • <?= e($app['description']) ?></span>
                    <span class="font-bold text-slate-700"><?= e($invoiceNo) ?></span>
                </footer>
            </div>
        </section>
    </main>

    <script>
        const printBtn = document.querySelector('#printBtn');
        printBtn?.addEventListener('click', () => {
            const label = printBtn.querySelector('.btn-label');
            const loading = printBtn.querySelector('.btn-loading');

            printBtn.disabled = true;
            label?.classList.add('hidden');
            loading?.classList.remove('hidden');
            loading?.classList.add('inline-flex');

            setTimeout(() => {
                window.print();
                setTimeout(() => {
                    printBtn.disabled = false;
                    label?.classList.remove('hidden');
                    loading?.classList.add('hidden');
                    loading?.classList.remove('inline-flex');
                }, 500);
            }, 450);
        });
    </script>
</body>
</html>
