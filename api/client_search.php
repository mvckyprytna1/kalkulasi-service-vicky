<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_login();

$q = clean_text($_GET['q'] ?? '', 120);
$params = [];
$sql = 'SELECT id, name, phone, whatsapp_normalized, address, client_type, notes, total_jobs, total_spent, last_service_at
        FROM clients';

if ($q !== '') {
    $sql .= ' WHERE name LIKE ? OR phone LIKE ? OR whatsapp_normalized LIKE ? OR address LIKE ? OR notes LIKE ?';
    for ($i = 0; $i < 5; $i++) {
        $params[] = '%' . $q . '%';
    }
}

$sql .= ' ORDER BY last_service_at DESC, updated_at DESC, created_at DESC LIMIT 50';
$stmt = db()->prepare($sql);
$stmt->execute($params);

json_response(true, 'OK', [
    'clients' => $stmt->fetchAll(),
]);
