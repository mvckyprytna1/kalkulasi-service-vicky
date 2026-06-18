<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/service_workflow.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$estimateId = (int)($_POST['estimate_id'] ?? 0);
try {
    $orderId = create_service_order_from_estimate($estimateId);
    redirect('service_order_view.php?id=' . $orderId);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Gagal membuat job ticket: ' . e($e->getMessage());
}
