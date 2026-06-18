<?php
require_once __DIR__ . '/bootstrap.php';

function get_active_presets(): array
{
    $stmt = db()->query('SELECT * FROM service_presets WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
    return $stmt->fetchAll();
}

function get_all_presets(): array
{
    $stmt = db()->query('SELECT * FROM service_presets ORDER BY sort_order ASC, id ASC');
    return $stmt->fetchAll();
}

function get_preset_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM service_presets WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_active_addons(): array
{
    $stmt = db()->query('SELECT * FROM addon_services WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
    return $stmt->fetchAll();
}

function get_all_addons(): array
{
    $stmt = db()->query('SELECT * FROM addon_services ORDER BY sort_order ASC, id ASC');
    return $stmt->fetchAll();
}

function get_addons_by_ids(array $ids): array
{
    $ids = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));
    if (!$ids) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT * FROM addon_services WHERE id IN ($placeholders) AND is_active = 1");
    $stmt->execute($ids);
    return $stmt->fetchAll();
}
