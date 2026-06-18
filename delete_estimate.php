<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403); echo 'Forbidden'; exit;
}
$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    $stmt = db()->prepare('DELETE FROM estimates WHERE id = ?');
    $stmt->execute([$id]);
}
redirect('history.php');
