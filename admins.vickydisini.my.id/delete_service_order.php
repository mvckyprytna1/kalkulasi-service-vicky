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
$order = get_service_order($orderId);
if (!$order) {
    http_response_code(404);
    echo 'Job ticket tidak ditemukan.';
    exit;
}

try {
    db()->beginTransaction();

    $clientId = (int)($order['client_id'] ?? 0);

    $stmt = db()->prepare('DELETE FROM payments WHERE order_id = ?');
    $stmt->execute([$orderId]);

    $stmt = db()->prepare('DELETE FROM service_order_logs WHERE order_id = ?');
    $stmt->execute([$orderId]);

    $stmt = db()->prepare('DELETE FROM service_orders WHERE id = ? LIMIT 1');
    $stmt->execute([$orderId]);

    db()->commit();

    if ($clientId > 0) {
        update_client_stats($clientId);
    }

    redirect('service_orders.php?deleted=1');
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    http_response_code(500);
    echo 'Gagal menghapus job ticket: ' . e($e->getMessage());
}
