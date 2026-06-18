<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/service_workflow.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$clientId = (int)($_POST['id'] ?? 0);
$action = clean_text($_POST['action'] ?? 'update', 30);

$stmt = db()->prepare('SELECT * FROM clients WHERE id = ? LIMIT 1');
$stmt->execute([$clientId]);
$client = $stmt->fetch();
if (!$client) {
    http_response_code(404);
    echo 'Client tidak ditemukan.';
    exit;
}

try {
    if ($action === 'delete') {
        db()->beginTransaction();

        // Job ticket tetap aman karena nama/nomor client juga tersimpan di tabel service_orders.
        // client_id dilepas supaya riwayat service tidak ikut hilang.
        $stmt = db()->prepare('UPDATE service_orders SET client_id = NULL WHERE client_id = ?');
        $stmt->execute([$clientId]);

        $stmt = db()->prepare('DELETE FROM clients WHERE id = ? LIMIT 1');
        $stmt->execute([$clientId]);

        db()->commit();
        redirect('clients.php?deleted=1');
    }

    $name = clean_text($_POST['name'] ?? '', 120);
    $phone = clean_text($_POST['phone'] ?? '', 80);
    $wa = normalize_wa_number($phone);
    $address = clean_text($_POST['address'] ?? '', 255);
    $clientType = clean_text($_POST['client_type'] ?? '', 80);
    $notes = clean_text($_POST['notes'] ?? '', 2000);

    if ($name === '') {
        throw new RuntimeException('Nama client wajib diisi.');
    }

    if ($wa !== '') {
        $stmt = db()->prepare('SELECT id, name FROM clients WHERE whatsapp_normalized = ? AND id <> ? LIMIT 1');
        $stmt->execute([$wa, $clientId]);
        $dup = $stmt->fetch();
        if ($dup) {
            throw new RuntimeException('Nomor WhatsApp sudah dipakai oleh client lain: ' . $dup['name']);
        }
    }

    $stmt = db()->prepare('UPDATE clients SET
        name = ?,
        phone = ?,
        whatsapp_normalized = ?,
        address = ?,
        client_type = ?,
        notes = ?,
        updated_at = NOW()
        WHERE id = ?');
    $stmt->execute([$name, $phone, $wa, $address, $clientType, $notes, $clientId]);

    // Sinkronkan data utama client ke job ticket yang masih terhubung.
    $stmt = db()->prepare('UPDATE service_orders SET
        client_name = ?,
        client_phone = ?,
        location = COALESCE(NULLIF(?, \'\'), location),
        updated_at = NOW()
        WHERE client_id = ?');
    $stmt->execute([$name, $phone, $address, $clientId]);

    update_client_stats($clientId);
    redirect('client_view.php?id=' . $clientId . '&updated=1');
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    http_response_code(500);
    echo 'Gagal memproses client: ' . e($e->getMessage());
}
