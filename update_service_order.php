<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/service_workflow.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$orderId = (int)($_POST['id'] ?? 0);
$action = clean_text($_POST['action'] ?? 'update_order', 40);
$order = get_service_order($orderId);
if (!$order) {
    http_response_code(404);
    echo 'Job ticket tidak ditemukan.';
    exit;
}

try {
    if ($action === 'add_payment') {
        $amount = (int)n($_POST['amount'] ?? 0);
        if ($amount <= 0) {
            throw new RuntimeException('Nominal pembayaran wajib lebih dari 0.');
        }
        $method = clean_text($_POST['method'] ?? 'Cash', 80);
        $note = clean_text($_POST['note'] ?? '', 500);
        $paidAt = clean_text($_POST['paid_at'] ?? date('Y-m-d\TH:i'), 40);
        $paidAt = str_replace('T', ' ', $paidAt);

        $stmt = db()->prepare('INSERT INTO payments (order_id, amount, method, note, paid_at, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$orderId, $amount, $method, $note, $paidAt]);

        $newPaid = (int)$order['paid_amount'] + $amount;
        $newPaymentStatus = payment_status_from_amounts((int)$order['final_amount'], $newPaid, (int)$order['dp_amount']);
        $stmt = db()->prepare('UPDATE service_orders SET paid_amount = ?, payment_status = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$newPaid, $newPaymentStatus, $orderId]);

        order_log($orderId, 'payment', money($order['paid_amount']), money($newPaid), 'Pembayaran masuk: ' . money($amount) . ' via ' . $method . ($note ? ' - ' . $note : ''));
        update_client_stats((int)$order['client_id']);
        redirect('service_order_view.php?id=' . $orderId . '#payment');
    }

    $allowedStatuses = array_keys(service_statuses());
    $serviceStatus = clean_text($_POST['service_status'] ?? $order['service_status'], 40);
    if (!in_array($serviceStatus, $allowedStatuses, true)) {
        $serviceStatus = $order['service_status'];
    }

    $clientName = clean_text($_POST['client_name'] ?? $order['client_name'], 120);
    $clientPhone = clean_text($_POST['client_phone'] ?? $order['client_phone'], 80);
    $location = clean_text($_POST['location'] ?? $order['location'], 255);
    $clientId = ensure_client($clientName, $clientPhone, $location, '', '');

    $finalAmount = (int)n($_POST['final_amount'] ?? $order['final_amount']);
    $dpAmount = (int)n($_POST['dp_amount'] ?? $order['dp_amount']);
    $paidAmount = (int)n($_POST['paid_amount'] ?? $order['paid_amount']);
    $paymentStatus = payment_status_from_amounts($finalAmount, $paidAmount, $dpAmount);

    $warrantyDays = (int)n($_POST['warranty_days'] ?? $order['warranty_days']);
    $warrantyStart = clean_text($_POST['warranty_start_at'] ?? '', 20);
    if ($warrantyStart === '' && $serviceStatus === 'selesai' && $warrantyDays > 0) {
        $warrantyStart = date('Y-m-d');
    }
    $warrantyEnd = warranty_end_date($warrantyStart, $warrantyDays);

    $finishedAt = $order['finished_at'];
    $pickedUpAt = $order['picked_up_at'];
    if ($serviceStatus === 'selesai' && empty($finishedAt)) {
        $finishedAt = date('Y-m-d H:i:s');
    }
    if ($serviceStatus === 'sudah_diambil' && empty($pickedUpAt)) {
        $pickedUpAt = date('Y-m-d H:i:s');
        if (empty($finishedAt)) {
            $finishedAt = date('Y-m-d H:i:s');
        }
    }

    $stmt = db()->prepare('UPDATE service_orders SET
        client_id = :client_id,
        client_name = :client_name,
        client_phone = :client_phone,
        location = :location,
        device_type = :device_type,
        device_brand = :device_brand,
        device_model = :device_model,
        serial_number = :serial_number,
        device_password = :device_password,
        physical_condition = :physical_condition,
        official_warranty = :official_warranty,
        accessories = :accessories,
        complaint = :complaint,
        service_name = :service_name,
        final_amount = :final_amount,
        dp_amount = :dp_amount,
        paid_amount = :paid_amount,
        service_status = :service_status,
        payment_status = :payment_status,
        warranty_days = :warranty_days,
        warranty_start_at = :warranty_start_at,
        warranty_end_at = :warranty_end_at,
        warranty_note = :warranty_note,
        technician_notes = :technician_notes,
        customer_notes = :customer_notes,
        finished_at = :finished_at,
        picked_up_at = :picked_up_at,
        updated_at = NOW()
        WHERE id = :id');

    $stmt->execute([
        ':client_id' => $clientId,
        ':client_name' => $clientName,
        ':client_phone' => $clientPhone,
        ':location' => $location,
        ':device_type' => clean_text($_POST['device_type'] ?? '', 100),
        ':device_brand' => clean_text($_POST['device_brand'] ?? '', 120),
        ':device_model' => clean_text($_POST['device_model'] ?? '', 120),
        ':serial_number' => clean_text($_POST['serial_number'] ?? '', 160),
        ':device_password' => clean_text($_POST['device_password'] ?? '', 160),
        ':physical_condition' => clean_text($_POST['physical_condition'] ?? '', 120),
        ':official_warranty' => clean_text($_POST['official_warranty'] ?? '', 80),
        ':accessories' => clean_text($_POST['accessories'] ?? '', 1000),
        ':complaint' => clean_text($_POST['complaint'] ?? '', 1500),
        ':service_name' => clean_text($_POST['service_name'] ?? '', 180),
        ':final_amount' => $finalAmount,
        ':dp_amount' => $dpAmount,
        ':paid_amount' => $paidAmount,
        ':service_status' => $serviceStatus,
        ':payment_status' => $paymentStatus,
        ':warranty_days' => $warrantyDays,
        ':warranty_start_at' => $warrantyStart !== '' ? $warrantyStart : null,
        ':warranty_end_at' => $warrantyEnd,
        ':warranty_note' => clean_text($_POST['warranty_note'] ?? '', 1000),
        ':technician_notes' => clean_text($_POST['technician_notes'] ?? '', 2500),
        ':customer_notes' => clean_text($_POST['customer_notes'] ?? '', 1500),
        ':finished_at' => $finishedAt,
        ':picked_up_at' => $pickedUpAt,
        ':id' => $orderId,
    ]);

    if ($serviceStatus !== $order['service_status']) {
        order_log($orderId, 'status', $order['service_status'], $serviceStatus, clean_text($_POST['status_note'] ?? '', 800));
    } else {
        order_log($orderId, 'note', '', '', 'Detail job ticket diperbarui.');
    }

    update_client_stats($clientId);
    redirect('service_order_view.php?id=' . $orderId . '&updated=1');
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Gagal update job ticket: ' . e($e->getMessage());
}
