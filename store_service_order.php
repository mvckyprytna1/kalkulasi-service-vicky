<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/service_workflow.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

try {
    $clientName = clean_text($_POST['client_name'] ?? '', 120);
    $deviceType = clean_text($_POST['device_type'] ?? '', 100);
    $serviceName = clean_text($_POST['service_name'] ?? '', 180);

    if ($clientName === '' || $deviceType === '' || $serviceName === '') {
        throw new RuntimeException('Nama client, jenis perangkat, dan layanan wajib diisi.');
    }

    $clientId = ensure_client(
        $clientName,
        clean_text($_POST['client_phone'] ?? '', 80),
        clean_text($_POST['location'] ?? '', 255),
        clean_text($_POST['client_type'] ?? '', 80),
        clean_text($_POST['client_notes'] ?? '', 1200)
    );

    $finalAmount = (int)n($_POST['final_amount'] ?? 0);
    $dpAmount = (int)n($_POST['dp_amount'] ?? 0);
    $paidAmount = (int)n($_POST['paid_amount'] ?? 0);
    $paymentStatus = payment_status_from_amounts($finalAmount, $paidAmount, $dpAmount);
    $warrantyDays = (int)n($_POST['warranty_days'] ?? 0);
    $warrantyStart = clean_text($_POST['warranty_start_at'] ?? '', 20);
    $warrantyEnd = warranty_end_date($warrantyStart, $warrantyDays);

    $stmt = db()->prepare('INSERT INTO service_orders (
        ticket_code, client_id, client_name, client_phone, location, device_type, device_brand, device_model,
        serial_number, device_password, physical_condition, official_warranty, accessories, complaint, service_name,
        estimate_amount, final_amount, dp_amount, paid_amount, service_status, payment_status,
        warranty_days, warranty_start_at, warranty_end_at, warranty_note, technician_notes, customer_notes,
        received_at, created_at
    ) VALUES (
        :ticket_code, :client_id, :client_name, :client_phone, :location, :device_type, :device_brand, :device_model,
        :serial_number, :device_password, :physical_condition, :official_warranty, :accessories, :complaint, :service_name,
        :estimate_amount, :final_amount, :dp_amount, :paid_amount, :service_status, :payment_status,
        :warranty_days, :warranty_start_at, :warranty_end_at, :warranty_note, :technician_notes, :customer_notes,
        NOW(), NOW()
    )');

    $ticket = make_ticket_code();
    $stmt->execute([
        ':ticket_code' => $ticket,
        ':client_id' => $clientId,
        ':client_name' => $clientName,
        ':client_phone' => clean_text($_POST['client_phone'] ?? '', 80),
        ':location' => clean_text($_POST['location'] ?? '', 255),
        ':device_type' => $deviceType,
        ':device_brand' => clean_text($_POST['device_brand'] ?? '', 120),
        ':device_model' => clean_text($_POST['device_model'] ?? '', 120),
        ':serial_number' => clean_text($_POST['serial_number'] ?? '', 160),
        ':device_password' => clean_text($_POST['device_password'] ?? '', 160),
        ':physical_condition' => clean_text($_POST['physical_condition'] ?? '', 120),
        ':official_warranty' => clean_text($_POST['official_warranty'] ?? '', 80),
        ':accessories' => clean_text($_POST['accessories'] ?? '', 1000),
        ':complaint' => clean_text($_POST['complaint'] ?? '', 1500),
        ':service_name' => $serviceName,
        ':estimate_amount' => $finalAmount,
        ':final_amount' => $finalAmount,
        ':dp_amount' => $dpAmount,
        ':paid_amount' => $paidAmount,
        ':service_status' => clean_text($_POST['service_status'] ?? 'masuk', 40),
        ':payment_status' => $paymentStatus,
        ':warranty_days' => $warrantyDays,
        ':warranty_start_at' => $warrantyStart !== '' ? $warrantyStart : null,
        ':warranty_end_at' => $warrantyEnd,
        ':warranty_note' => clean_text($_POST['warranty_note'] ?? '', 1000),
        ':technician_notes' => clean_text($_POST['technician_notes'] ?? '', 2000),
        ':customer_notes' => clean_text($_POST['customer_notes'] ?? '', 1200),
    ]);

    $orderId = (int)db()->lastInsertId();
    order_log($orderId, 'status', '', clean_text($_POST['service_status'] ?? 'masuk', 40), 'Job ticket dibuat manual.');
    update_client_stats($clientId);
    redirect('service_order_view.php?id=' . $orderId);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Gagal menyimpan job ticket: ' . e($e->getMessage());
}
