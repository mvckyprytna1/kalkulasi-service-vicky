<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/service_workflow.php';

$rawCode = clean_text($_GET['code'] ?? ($_GET['nota'] ?? ''), 120);
$id = (int)($_GET['id'] ?? 0);
$sig = (string)($_GET['sig'] ?? '');
$expiresAt = (int)($_GET['exp'] ?? 0);

if ($rawCode !== '') {
    $stmt = db()->prepare('SELECT id FROM service_orders WHERE ticket_code = ? LIMIT 1');
    $stmt->execute([$rawCode]);
    $id = (int)($stmt->fetchColumn() ?: 0);
}

$order = get_service_order($id);
$errorTitle = '';
$errorMessage = '';

if (!$order) {
    http_response_code(404);
    $errorTitle = 'Nota tidak ditemukan';
    $errorMessage = 'Link nota tidak cocok dengan data service.';
} else {
    $expectedSig = $expiresAt > 0
        ? public_invoice_signature($order, $expiresAt)
        : public_invoice_signature($order, null);

    if ($sig === '' || !hash_equals($expectedSig, $sig)) {
        http_response_code(403);
        $errorTitle = 'Link nota tidak valid';
        $errorMessage = 'Signature nota tidak cocok. Minta ulang link nota dari admin.';
    } elseif ($expiresAt > 0 && time() > $expiresAt) {
        http_response_code(410);
        $errorTitle = 'Link nota kedaluwarsa';
        $errorMessage = 'Masa aktif link nota sudah habis. Minta ulang link nota dari admin.';
    }
}

$config = app_config();
$app = $config['app'];
$currency = $config['app']['currency'] ?? 'Rp';

function invoice2_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $letters = '';
    foreach ($parts as $part) {
        if ($part !== '') {
            $letters .= mb_substr($part, 0, 1);
        }
        if (mb_strlen($letters) >= 2) {
            break;
        }
    }
    return mb_strtoupper($letters ?: 'NC');
}

if ($errorTitle !== ''):
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title><?= e($errorTitle) ?> - <?= e($app['name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-black text-white antialiased">
    <main class="flex min-h-screen items-center justify-center px-4 py-10">
        <section class="w-full max-w-md rounded-[2rem] border border-white/10 bg-white p-8 text-center text-slate-900 shadow-2xl">
            <div class="mx-auto mb-5 grid h-16 w-16 place-items-center rounded-3xl bg-rose-50 text-rose-600">
                <svg class="h-9 w-9" viewBox="0 0 24 24" fill="none"><path d="M12 9v4m0 4h.01M10.3 3.9 2.4 17.5A2 2 0 0 0 4.1 20h15.8a2 2 0 0 0 1.7-2.5L13.7 3.9a2 2 0 0 0-3.4 0Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <p class="text-xs font-black uppercase tracking-[.28em] text-slate-400"><?= e($app['name']) ?></p>
            <h1 class="mt-2 text-3xl font-black tracking-tight"><?= e($errorTitle) ?></h1>
            <p class="mt-3 leading-7 text-slate-500"><?= e($errorMessage) ?></p>
        </section>
    </main>
</body>
</html>
<?php
exit;
endif;

$statuses = service_statuses();
$paymentStatuses = payment_statuses();
$payments = function_exists('get_order_payments') ? get_order_payments((int)$order['id']) : [];
$addons = json_decode($order['addon_json'] ?? '[]', true);
$addons = is_array($addons) ? array_values(array_filter($addons)) : [];
$device = trim(implode(' ', array_filter([
    $order['device_type'] ?? '',
    $order['device_brand'] ?? '',
    $order['device_model'] ?? '',
])));
$invoiceNo = 'INV-' . preg_replace('/^SRV-/', '', (string)$order['ticket_code']);
$createdAt = !empty($order['created_at']) ? strtotime((string)$order['created_at']) : time();
$issuedAt = date('d M Y', $createdAt ?: time());
$expiredLabel = $expiresAt > 0 ? date('d M Y H:i', $expiresAt) : 'Tidak dibatasi';

$paymentDate = '-';
if (!empty($payments)) {
    $lastPayment = end($payments);
    if (!empty($lastPayment['paid_at'])) {
        $paymentDate = date('d M Y', strtotime((string)$lastPayment['paid_at']));
    }
    reset($payments);
}

$sisaBayar = max(0, (int)$order['final_amount'] - (int)$order['paid_amount']);
$statusBayar = $paymentStatuses[$order['payment_status']] ?? $order['payment_status'];
$statusServis = $statuses[$order['service_status']] ?? $order['service_status'];

$summaryLabel = 'Tagihan aktif';
$summaryAmount = $sisaBayar > 0 ? $sisaBayar : (int)$order['final_amount'];
$stampText = 'BELUM LUNAS';
$stampClass = 'border-rose-200 bg-rose-50 text-rose-600';
$indicatorClass = 'bg-rose-500';

if (($order['payment_status'] ?? '') === 'lunas') {
    $summaryLabel = 'Invoice paid';
    $summaryAmount = (int)$order['final_amount'];
    $stampText = 'LUNAS';
    $stampClass = 'border-emerald-200 bg-emerald-50 text-emerald-700';
    $indicatorClass = 'bg-emerald-500';
} elseif ((int)$order['paid_amount'] > 0 && $sisaBayar > 0) {
    $summaryLabel = 'Sisa pembayaran';
    $summaryAmount = $sisaBayar;
    $stampText = 'DP / CICIL';
    $stampClass = 'border-blue-200 bg-blue-50 text-blue-700';
    $indicatorClass = 'bg-blue-500';
}

$detailRows = [
    'Pelanggan' => $order['client_name'] ?: '-',
    'WhatsApp' => $order['client_phone'] ?: '-',
    'Perangkat' => $device ?: '-',
    'Serial / IMEI' => $order['serial_number'] ?: '-',
    'Layanan' => $order['service_name'] ?: '-',
    'Status Service' => $statusServis,
    'Status Bayar' => $statusBayar,
    'Keluhan' => $order['complaint'] ?: '-',
    'Diagnosa Teknisi' => $order['technician_notes'] ?: '-',
    'Catatan Client' => $order['customer_notes'] ?: '-',
];

$publicUrl = public_invoice_url($order, true);
$currentUrl = app_origin() . ($_SERVER['REQUEST_URI'] ?? app_url('invoice_public.php?id=' . $order['id']));
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title><?= e($invoiceNo) ?> - <?= e($app['name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#020617">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'sans-serif'],
                    },
                    boxShadow: {
                        premium: '0 24px 90px rgba(2, 6, 23, .46)',
                        soft: '0 12px 30px rgba(15, 23, 42, .14)',
                    }
                }
            }
        }
    </script>
    <style>
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .page-bg {
            background:
                radial-gradient(circle at 22% 12%, rgba(16,185,129,.14), transparent 18rem),
                radial-gradient(circle at 82% 8%, rgba(59,130,246,.12), transparent 20rem),
                linear-gradient(180deg, #000 0%, #020617 100%);
        }
        .stamp {
            transform: rotate(-8deg);
            letter-spacing: .14em;
        }
        #qrcode canvas, #qrcode img {
            width: 96px !important;
            height: 96px !important;
            border-radius: 14px;
        }
        @media print {
            body { background: #fff !important; }
            .no-print { display: none !important; }
            #invoiceCard { box-shadow: none !important; border: 1px solid #e2e8f0 !important; }
            .print-frame { padding: 0 !important; }
            @page { size: A4; margin: 10mm; }
        }
    </style>
</head>
<body class="page-bg min-h-screen text-white antialiased">
    <main class="mx-auto flex min-h-screen w-full max-w-7xl items-center justify-center px-4 py-8 sm:px-6 lg:px-8 print-frame">
        <div class="w-full max-w-3xl">
            <div class="mb-5 flex items-center justify-between gap-4 no-print">
                <div class="flex items-center gap-3">
                    <div class="grid h-11 w-11 place-items-center rounded-2xl border border-white/15 bg-white/5 text-sm font-black shadow-soft backdrop-blur">
                        <?= e(invoice2_initials($app['name'])) ?>
                    </div>
                    <div>
                        <p class="text-[11px] uppercase tracking-[.32em] text-emerald-300/80">Shareable Invoice</p>
                        <h1 class="text-lg font-bold tracking-tight text-white sm:text-xl"><?= e($app['name']) ?></h1>
                    </div>
                </div>
                <div class="hidden text-right text-sm text-white/65 sm:block">
                    <div><?= e($app['email']) ?></div>
                    <div><?= e($app['whatsapp']) ?></div>
                </div>
            </div>

            <section id="invoiceCard" class="overflow-hidden rounded-[2rem] border border-slate-200/10 bg-white text-slate-900 shadow-premium">
                <div id="captureArea" class="relative p-7 sm:p-9">
                    <div class="absolute right-6 top-6 hidden rounded-xl border px-3 py-2 text-xs font-black <?= e($stampClass) ?> stamp sm:block">
                        <?= e($stampText) ?>
                    </div>

                    <div class="mx-auto flex max-w-md flex-col items-center text-center">
                        <div class="mb-6 grid h-20 w-20 place-items-center rounded-3xl bg-slate-100 shadow-inner">
                            <svg viewBox="0 0 64 64" class="h-12 w-12" aria-hidden="true">
                                <rect x="18" y="10" width="28" height="36" rx="4" fill="#ffffff" stroke="#dbeafe" stroke-width="2"></rect>
                                <path d="M25 21h14M25 28h14M25 35h9" stroke="#cbd5e1" stroke-width="3" stroke-linecap="round"></path>
                                <circle cx="47" cy="46" r="10" fill="#10b981"></circle>
                                <path d="M42.5 46.5l3 3 5.5-6" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                        </div>

                        <div class="mb-2 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-bold text-slate-500 sm:hidden">
                            <span class="h-2 w-2 rounded-full <?= e($indicatorClass) ?>"></span>
                            <?= e($stampText) ?>
                        </div>

                        <p class="text-lg text-slate-500"><?= e($summaryLabel) ?></p>
                        <h2 class="mt-1 text-4xl font-black tracking-tight text-slate-900 sm:text-5xl"><?= e($currency) ?> <?= number_format($summaryAmount, 0, ',', '.') ?></h2>
                        <button id="toggleDetailBtn" type="button" class="mt-3 inline-flex items-center gap-2 text-sm font-medium text-slate-500 transition hover:text-slate-800 no-print">
                            <span>Lihat detail nota dan pembayaran</span>
                            <svg id="detailChevron" class="h-4 w-4 transition" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.23 14.79a.75.75 0 01.02-1.06L10.94 10 7.25 6.27a.75.75 0 111.06-1.06l4.22 4.27a.75.75 0 010 1.06l-4.22 4.27a.75.75 0 01-1.08-.02z" clip-rule="evenodd"/></svg>
                        </button>
                    </div>

                    <div class="mt-8 grid gap-3 text-sm text-slate-700 sm:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4"><div class="text-slate-400">Invoice number</div><div class="mt-1 text-base font-semibold text-slate-900"><?= e($invoiceNo) ?></div></div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4"><div class="text-slate-400">Payment date</div><div class="mt-1 text-base font-semibold text-slate-900"><?= e($paymentDate) ?></div></div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4"><div class="text-slate-400">Ticket service</div><div class="mt-1 text-base font-semibold text-slate-900"><?= e($order['ticket_code']) ?></div></div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4"><div class="text-slate-400">Link berlaku sampai</div><div class="mt-1 text-base font-semibold text-slate-900"><?= e($expiredLabel) ?></div></div>
                    </div>

                    <div id="detailPanel" class="mt-7 space-y-6 border-t border-slate-200 pt-7 hidden sm:block">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <?php foreach ($detailRows as $label => $value): ?>
                                <div class="rounded-2xl border border-slate-200/90 px-4 py-3">
                                    <div class="text-xs uppercase tracking-[.18em] text-slate-400"><?= e($label) ?></div>
                                    <div class="mt-2 whitespace-pre-line text-sm font-medium leading-6 text-slate-800"><?= nl2br(e($value)) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50/70 p-5">
                            <div class="mb-4 flex items-center justify-between gap-3">
                                <h3 class="text-sm font-black uppercase tracking-[.2em] text-slate-500">Ringkasan Biaya</h3>
                                <span class="rounded-full border px-3 py-1 text-xs font-bold <?= e($stampClass) ?>"><?= e($statusBayar) ?></span>
                            </div>
                            <div class="space-y-3 text-sm">
                                <div class="flex items-center justify-between gap-4"><span class="text-slate-500">Estimasi Awal</span><strong><?= money($order['estimate_amount']) ?></strong></div>
                                <div class="flex items-center justify-between gap-4"><span class="text-slate-500">Total Final</span><strong><?= money($order['final_amount']) ?></strong></div>
                                <div class="flex items-center justify-between gap-4"><span class="text-slate-500">DP / Bayar Masuk</span><strong><?= money($order['paid_amount']) ?></strong></div>
                                <div class="flex items-center justify-between gap-4 border-t border-dashed border-slate-300 pt-3"><span class="font-semibold text-slate-700">Sisa Bayar</span><strong class="text-lg text-slate-900"><?= money($sisaBayar) ?></strong></div>
                            </div>
                            <?php if ($addons): ?>
                                <div class="mt-4 border-t border-dashed border-slate-300 pt-4 text-sm text-slate-600">
                                    <div class="mb-1 font-semibold text-slate-700">Tambahan layanan</div>
                                    <div><?= e(implode(', ', $addons)) ?></div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="grid gap-4 rounded-[1.5rem] border border-slate-200 bg-white p-5 sm:grid-cols-[1fr_auto] sm:items-center">
                            <div>
                                <h3 class="text-sm font-black uppercase tracking-[.2em] text-slate-500">QR Nota</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-500">Scan untuk membuka link nota ini. Cocok ditempel di nota fisik atau dikirim sebagai gambar.</p>
                            </div>
                            <div class="mx-auto rounded-2xl border border-slate-200 bg-white p-3">
                                <div id="qrcode"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-200 px-7 py-6 sm:px-9 no-print">
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <button id="downloadPngBtn" type="button" class="inline-flex min-h-12 flex-1 items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 font-bold text-slate-800 shadow-sm transition hover:-translate-y-0.5 hover:bg-slate-50">
                            <span class="label">Download PNG</span>
                            <span class="loading hidden items-center gap-2"><span class="h-4 w-4 animate-spin rounded-full border-2 border-slate-400/50 border-t-slate-900"></span>Menyiapkan...</span>
                        </button>
                        <button id="printBtn" type="button" class="inline-flex min-h-12 flex-1 items-center justify-center rounded-2xl bg-slate-950 px-5 font-bold text-white shadow-soft transition hover:-translate-y-0.5 hover:bg-slate-800">
                            <span class="label">Cetak / Simpan PDF</span>
                            <span class="loading hidden items-center gap-2"><span class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>Memproses...</span>
                        </button>
                    </div>
                    <div class="mt-3 flex flex-col gap-3 sm:flex-row">
                        <button id="copyLinkBtn" type="button" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-2xl border border-emerald-200 bg-emerald-50 px-4 text-sm font-bold text-emerald-700 transition hover:bg-emerald-100">Salin Link Nota</button>
                        <?php if (!empty($order['client_phone'])): ?>
                            <a href="https://wa.me/<?= e(normalize_wa_number($order['client_phone'])) ?>?text=<?= rawurlencode('Halo, berikut link nota service ' . $invoiceNo . ': ' . $currentUrl) ?>" target="_blank" rel="noopener" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-2xl bg-emerald-500 px-4 text-sm font-bold text-white transition hover:bg-emerald-600">Kirim ke WhatsApp</a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <div class="mt-5 text-center text-xs text-white/45">
                Secure invoice link • <?= e($app['name']) ?> • <?= e($issuedAt) ?>
            </div>
        </div>
    </main>

    <script>
        const detailBtn = document.getElementById('toggleDetailBtn');
        const detailPanel = document.getElementById('detailPanel');
        const chevron = document.getElementById('detailChevron');
        const printBtn = document.getElementById('printBtn');
        const downloadPngBtn = document.getElementById('downloadPngBtn');
        const copyLinkBtn = document.getElementById('copyLinkBtn');
        const captureArea = document.getElementById('captureArea');
        const shareUrl = <?= json_encode($currentUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

        function setLoading(btn, isLoading) {
            if (!btn) return;
            btn.disabled = isLoading;
            btn.querySelector('.label')?.classList.toggle('hidden', isLoading);
            btn.querySelector('.loading')?.classList.toggle('hidden', !isLoading);
            btn.querySelector('.loading')?.classList.toggle('inline-flex', isLoading);
            btn.classList.toggle('opacity-80', isLoading);
        }

        function showDetail() {
            detailPanel?.classList.remove('hidden');
            chevron?.classList.add('rotate-90');
        }

        function hideDetail() {
            detailPanel?.classList.add('hidden');
            chevron?.classList.remove('rotate-90');
        }

        detailBtn?.addEventListener('click', () => {
            detailPanel?.classList.toggle('hidden');
            chevron?.classList.toggle('rotate-90');
        });

        if (typeof QRCode !== 'undefined') {
            new QRCode(document.getElementById('qrcode'), {
                text: shareUrl,
                width: 96,
                height: 96,
                colorDark: '#0f172a',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        } else {
            const qr = document.getElementById('qrcode');
            if (qr) qr.textContent = 'QR gagal dimuat';
        }

        printBtn?.addEventListener('click', () => {
            setLoading(printBtn, true);
            setTimeout(() => {
                window.print();
                setTimeout(() => setLoading(printBtn, false), 800);
            }, 180);
        });

        downloadPngBtn?.addEventListener('click', async () => {
            if (typeof html2canvas === 'undefined') {
                alert('Library PNG belum termuat. Coba refresh halaman.');
                return;
            }

            setLoading(downloadPngBtn, true);
            const wasHidden = detailPanel?.classList.contains('hidden');
            showDetail();

            try {
                const canvas = await html2canvas(captureArea, {
                    backgroundColor: '#ffffff',
                    scale: 2,
                    useCORS: true,
                    windowWidth: document.documentElement.scrollWidth,
                });
                const link = document.createElement('a');
                link.download = <?= json_encode($invoiceNo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> + '.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
            } catch (err) {
                alert('Gagal membuat PNG. Coba ulang lagi.');
            } finally {
                if (wasHidden) hideDetail();
                setLoading(downloadPngBtn, false);
            }
        });

        copyLinkBtn?.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(shareUrl);
                copyLinkBtn.textContent = 'Link nota tersalin';
                setTimeout(() => copyLinkBtn.textContent = 'Salin Link Nota', 1800);
            } catch (err) {
                alert('Gagal menyalin link.');
            }
        });
    </script>
</body>
</html>
