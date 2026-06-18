<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/service_workflow.php';

$config = app_config();
$key = $_GET['key'] ?? '';

if ($key === '' || !hash_equals((string)$config['security']['install_key'], (string)$key)) {
    http_response_code(403);
    echo 'Install key salah. Buka dengan ?key=INSTALL_KEY_DARI_CONFIG';
    exit;
}

$estimateCount = 0;
$orderCount = 0;

try {
    db()->beginTransaction();

    $rows = db()->query("SELECT client_name, client_phone, client_type, location, client_notes FROM estimates WHERE client_name <> '' ORDER BY id ASC")->fetchAll();
    foreach ($rows as $row) {
        ensure_client(
            $row['client_name'] ?? '',
            $row['client_phone'] ?? '',
            $row['location'] ?? '',
            $row['client_type'] ?: 'baru',
            $row['client_notes'] ?? ''
        );
        $estimateCount++;
    }

    $orders = db()->query("SELECT id, client_name, client_phone, location, client_id FROM service_orders WHERE client_name <> '' ORDER BY id ASC")->fetchAll();
    foreach ($orders as $order) {
        $clientId = ensure_client(
            $order['client_name'] ?? '',
            $order['client_phone'] ?? '',
            $order['location'] ?? '',
            'langganan',
            ''
        );

        if (empty($order['client_id'])) {
            $stmt = db()->prepare('UPDATE service_orders SET client_id = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$clientId, $order['id']]);
        }

        update_client_stats($clientId);
        $orderCount++;
    }

    // update stats untuk semua client yang berhasil dibuat dari estimasi juga
    $clients = db()->query('SELECT id FROM clients')->fetchAll();
    foreach ($clients as $client) {
        update_client_stats((int)$client['id']);
    }

    db()->commit();
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    http_response_code(500);
    echo 'Gagal sync client: ' . e($e->getMessage());
    exit;
}
?>
<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><title>Sync Client Database</title><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="assets/css/style.css"></head>
<body>
<main class="history-page">
    <div class="container">
        <div class="panel wide-panel">
            <span class="section-kicker">Maintenance</span>
            <h1 class="history-title">Sync Client Selesai</h1>
            <div class="alert success">
                Client database berhasil dibuat/disinkronkan dari riwayat estimasi dan job ticket.
            </div>
            <div class="summary-cards">
                <div class="summary-card"><small>Estimasi diproses</small><strong><?= e($estimateCount) ?></strong></div>
                <div class="summary-card"><small>Job ticket diproses</small><strong><?= e($orderCount) ?></strong></div>
            </div>
            <p>Setelah sukses, hapus file <code>sync_clients_from_history.php</code> dari hosting.</p>
            <a class="btn" href="clients.php">Buka Database Client</a>
        </div>
    </div>
</main>
</body>
</html>
