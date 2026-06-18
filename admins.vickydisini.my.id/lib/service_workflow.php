<?php
require_once __DIR__ . '/bootstrap.php';


if (!function_exists('n')) {
    function n($value): float
    {
        return is_numeric($value) ? (float)$value : 0.0;
    }
}

function normalize_wa_number($value): string
{
    $digits = preg_replace('/\D+/', '', (string)$value);
    if ($digits === '') {
        return '';
    }
    if (substr($digits, 0, 1) === '0') {
        $digits = '62' . substr($digits, 1);
    } elseif (substr($digits, 0, 1) === '8') {
        $digits = '62' . $digits;
    }
    return $digits;
}

function service_statuses(): array
{
    return [
        'masuk' => 'Masuk',
        'dicek' => 'Dicek',
        'menunggu_acc' => 'Menunggu ACC',
        'dikerjakan' => 'Dikerjakan',
        'menunggu_sparepart' => 'Menunggu Sparepart',
        'selesai' => 'Selesai',
        'sudah_diambil' => 'Sudah Diambil',
        'batal' => 'Batal',
    ];
}

function payment_statuses(): array
{
    return [
        'belum_bayar' => 'Belum Bayar',
        'dp' => 'DP / Cicil',
        'lunas' => 'Lunas',
        'refund' => 'Refund',
    ];
}

function payment_methods(): array
{
    return ['Cash', 'Transfer Bank', 'QRIS', 'E-Wallet', 'Lainnya'];
}

function warranty_days_from_key(?string $key): int
{
    return match ((string)$key) {
        '3_hari' => 3,
        '7_hari' => 7,
        '30_hari' => 30,
        default => 0,
    };
}

function warranty_end_date(?string $start, int $days): ?string
{
    if (!$start || $days <= 0) {
        return null;
    }
    try {
        $dt = new DateTime($start);
        $dt->modify('+' . $days . ' days');
        return $dt->format('Y-m-d');
    } catch (Throwable $e) {
        return null;
    }
}

function make_ticket_code(): string
{
    for ($i = 0; $i < 8; $i++) {
        $code = 'SRV-' . date('ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $stmt = db()->prepare('SELECT id FROM service_orders WHERE ticket_code = ? LIMIT 1');
        $stmt->execute([$code]);
        if (!$stmt->fetch()) {
            return $code;
        }
        usleep(100000);
    }
    return 'SRV-' . date('ymd-His') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

function ensure_client(string $name, string $phone = '', string $location = '', string $type = '', string $notes = ''): int
{
    $name = clean_text($name, 120);
    $phone = clean_text($phone, 80);
    $location = clean_text($location, 255);
    $type = clean_text($type, 80);
    $notes = clean_text($notes, 1200);
    $wa = normalize_wa_number($phone);

    if ($name === '') {
        $name = 'Client Tanpa Nama';
    }

    $existing = null;
    if ($wa !== '') {
        $stmt = db()->prepare('SELECT * FROM clients WHERE whatsapp_normalized = ? LIMIT 1');
        $stmt->execute([$wa]);
        $existing = $stmt->fetch();
    }

    if (!$existing && $phone !== '') {
        $stmt = db()->prepare('SELECT * FROM clients WHERE phone = ? LIMIT 1');
        $stmt->execute([$phone]);
        $existing = $stmt->fetch();
    }

    if ($existing) {
        $stmt = db()->prepare('UPDATE clients SET
            name = ?,
            phone = COALESCE(NULLIF(?, \'\'), phone),
            whatsapp_normalized = COALESCE(NULLIF(?, \'\'), whatsapp_normalized),
            address = COALESCE(NULLIF(?, \'\'), address),
            client_type = COALESCE(NULLIF(?, \'\'), client_type),
            notes = CASE WHEN ? = \'\' THEN notes ELSE ? END,
            updated_at = NOW()
            WHERE id = ?');
        $stmt->execute([$name, $phone, $wa, $location, $type, $notes, $notes, $existing['id']]);
        return (int)$existing['id'];
    }

    $stmt = db()->prepare('INSERT INTO clients (name, phone, whatsapp_normalized, address, client_type, notes, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([$name, $phone, $wa, $location, $type, $notes]);
    return (int)db()->lastInsertId();
}

function update_client_stats(int $clientId): void
{
    if ($clientId <= 0) {
        return;
    }
    $stmt = db()->prepare('SELECT COUNT(*) total_jobs, COALESCE(SUM(paid_amount),0) total_spent, MAX(created_at) last_service_at
        FROM service_orders WHERE client_id = ?');
    $stmt->execute([$clientId]);
    $stats = $stmt->fetch() ?: ['total_jobs' => 0, 'total_spent' => 0, 'last_service_at' => null];

    $stmt = db()->prepare('UPDATE clients SET total_jobs = ?, total_spent = ?, last_service_at = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([(int)$stats['total_jobs'], (int)$stats['total_spent'], $stats['last_service_at'], $clientId]);
}

function order_log(int $orderId, string $type, string $oldValue = '', string $newValue = '', string $note = ''): void
{
    if ($orderId <= 0) {
        return;
    }
    $admin = current_admin();
    $stmt = db()->prepare('INSERT INTO service_order_logs (order_id, log_type, old_value, new_value, note, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([
        $orderId,
        clean_text($type, 40),
        clean_text($oldValue, 255),
        clean_text($newValue, 255),
        clean_text($note, 1000),
        $admin['username'] ?? 'system',
    ]);
}

function get_service_order(int $id): ?array
{
    $stmt = db()->prepare('SELECT so.*, c.name client_db_name, c.phone client_db_phone, c.whatsapp_normalized, c.address client_address, c.notes client_db_notes
        FROM service_orders so
        LEFT JOIN clients c ON c.id = so.client_id
        WHERE so.id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_service_order_by_estimate(int $estimateId): ?array
{
    $stmt = db()->prepare('SELECT * FROM service_orders WHERE estimate_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$estimateId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function create_service_order_from_estimate(int $estimateId): int
{
    $stmt = db()->prepare('SELECT * FROM estimates WHERE id = ? LIMIT 1');
    $stmt->execute([$estimateId]);
    $est = $stmt->fetch();
    if (!$est) {
        throw new RuntimeException('Estimasi tidak ditemukan.');
    }

    $existing = get_service_order_by_estimate($estimateId);
    if ($existing) {
        return (int)$existing['id'];
    }

    $clientId = ensure_client(
        (string)$est['client_name'],
        (string)($est['client_phone'] ?? ''),
        (string)($est['location'] ?? ''),
        (string)($est['client_type'] ?? ''),
        (string)($est['client_notes'] ?? '')
    );

    $warrantyDays = warranty_days_from_key($est['warranty'] ?? '');
    $ticketCode = make_ticket_code();

    $stmt = db()->prepare('INSERT INTO service_orders (
        ticket_code, estimate_id, client_id, client_name, client_phone, location,
        device_type, device_brand, device_model, physical_condition, official_warranty,
        complaint, service_name, addon_json, estimate_amount, final_amount, dp_amount, paid_amount,
        service_status, payment_status, warranty_days, technician_notes, customer_notes, received_at, created_at
    ) VALUES (
        :ticket_code, :estimate_id, :client_id, :client_name, :client_phone, :location,
        :device_type, :device_brand, :device_model, :physical_condition, :official_warranty,
        :complaint, :service_name, :addon_json, :estimate_amount, :final_amount, :dp_amount, :paid_amount,
        :service_status, :payment_status, :warranty_days, :technician_notes, :customer_notes, NOW(), NOW()
    )');

    $complaint = trim((string)($est['client_notes'] ?? ''));
    if ($complaint === '') {
        $complaint = 'Keluhan dari estimasi: ' . ($est['service_name'] ?? '-');
    }

    $stmt->execute([
        ':ticket_code' => $ticketCode,
        ':estimate_id' => $estimateId,
        ':client_id' => $clientId,
        ':client_name' => $est['client_name'],
        ':client_phone' => $est['client_phone'],
        ':location' => $est['location'],
        ':device_type' => $est['device_type'],
        ':device_brand' => $est['device_brand'],
        ':device_model' => $est['device_model'],
        ':physical_condition' => $est['physical_condition'],
        ':official_warranty' => $est['official_warranty'],
        ':complaint' => $complaint,
        ':service_name' => $est['service_name'],
        ':addon_json' => $est['addon_json'],
        ':estimate_amount' => (int)$est['ideal_price'],
        ':final_amount' => (int)$est['ideal_price'],
        ':dp_amount' => (int)$est['dp'],
        ':paid_amount' => 0,
        ':service_status' => 'masuk',
        ':payment_status' => 'belum_bayar',
        ':warranty_days' => $warrantyDays,
        ':technician_notes' => '',
        ':customer_notes' => '',
    ]);

    $orderId = (int)db()->lastInsertId();
    order_log($orderId, 'status', '', 'masuk', 'Job ticket dibuat dari estimasi ' . $est['estimate_code']);
    update_client_stats($clientId);

    return $orderId;
}

function payment_status_from_amounts(int $finalAmount, int $paidAmount, int $dpAmount = 0): string
{
    if ($paidAmount <= 0) {
        return 'belum_bayar';
    }
    if ($finalAmount > 0 && $paidAmount >= $finalAmount) {
        return 'lunas';
    }
    return 'dp';
}

function get_order_payments(int $orderId): array
{
    $stmt = db()->prepare('SELECT * FROM payments WHERE order_id = ? ORDER BY paid_at DESC, id DESC');
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}

function get_order_logs(int $orderId): array
{
    $stmt = db()->prepare('SELECT * FROM service_order_logs WHERE order_id = ? ORDER BY created_at DESC, id DESC LIMIT 80');
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}

function get_whatsapp_templates(): array
{
    $stmt = db()->query('SELECT * FROM whatsapp_templates WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
    return $stmt->fetchAll();
}

function render_template_body(string $body, array $order): string
{
    $statusLabel = service_statuses()[$order['service_status'] ?? ''] ?? ($order['service_status'] ?? '-');
    $paymentLabel = payment_statuses()[$order['payment_status'] ?? ''] ?? ($order['payment_status'] ?? '-');
    $sisa = max(0, (int)($order['final_amount'] ?? 0) - (int)($order['paid_amount'] ?? 0));
    $device = trim(implode(' ', array_filter([
        $order['device_type'] ?? '',
        $order['device_brand'] ?? '',
        $order['device_model'] ?? '',
    ])));

    $replacements = [
        '{nama}' => $order['client_name'] ?? '-',
        '{client}' => $order['client_name'] ?? '-',
        '{ticket}' => $order['ticket_code'] ?? '-',
        '{kode}' => $order['ticket_code'] ?? '-',
        '{perangkat}' => $device ?: '-',
        '{layanan}' => $order['service_name'] ?? '-',
        '{keluhan}' => $order['complaint'] ?? '-',
        '{status}' => $statusLabel,
        '{status_bayar}' => $paymentLabel,
        '{total}' => money($order['final_amount'] ?? 0),
        '{estimasi}' => money($order['estimate_amount'] ?? 0),
        '{dp}' => money($order['dp_amount'] ?? 0),
        '{dibayar}' => money($order['paid_amount'] ?? 0),
        '{sisa}' => money($sisa),
        '{garansi_hari}' => (string)($order['warranty_days'] ?? 0),
        '{garansi_sampai}' => $order['warranty_end_at'] ?? '-',
        '{catatan}' => $order['customer_notes'] ?? '',
        '{brand}' => app_config()['app']['name'] ?? 'Service Center',
    ];

    return strtr($body, $replacements);
}
