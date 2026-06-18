<?php
require_once __DIR__ . '/lib/bootstrap.php';

$config = app_config();
$key = $_GET['key'] ?? '';
if (($config['security']['install_key'] ?? '') === 'GANTI_INSTALL_KEY_UNIK' || !hash_equals($config['security']['install_key'] ?? '', $key)) {
    http_response_code(403);
    echo 'Forbidden. Gunakan ?key=INSTALL_KEY dari config.php';
    exit;
}

function stage12_column_exists(string $table, string $column): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

try {
    $sql = file_get_contents(__DIR__ . '/migration_stage12.sql');
    $statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
    foreach ($statements as $statement) {
        if ($statement !== '') {
            db()->exec($statement);
        }
    }

    if (!stage12_column_exists('estimates', 'client_id')) {
        db()->exec('ALTER TABLE estimates ADD COLUMN client_id BIGINT UNSIGNED NULL AFTER id');
        db()->exec('CREATE INDEX idx_estimates_client_id ON estimates (client_id)');
    }

    echo '<h1>Stage 1 & 2 upgrade berhasil.</h1><p>Tabel clients, service_orders, payments, logs, dan whatsapp_templates siap dipakai.</p><p><b>Hapus file db_upgrade_stage12.php setelah berhasil.</b></p><p><a href="service_orders.php">Buka Job Ticket</a></p>';
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>Upgrade gagal</h1><pre>' . e($e->getMessage()) . '</pre>';
}
