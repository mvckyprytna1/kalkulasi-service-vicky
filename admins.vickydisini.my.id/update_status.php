<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403); echo 'Forbidden'; exit;
}
$id = (int)($_POST['id'] ?? 0);
$status = clean_text($_POST['status'] ?? 'draft', 30);
$allowed = ['draft','sent','deal','rejected','revised','paid','done'];
if ($id > 0 && in_array($status, $allowed, true)) {
    $stmt = db()->prepare('UPDATE estimates SET status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);
}
redirect('estimate_view.php?id=' . $id);
