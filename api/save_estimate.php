<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/repositories.php';
require_once __DIR__ . '/../lib/pricing.php';
require_once __DIR__ . '/../lib/service_workflow.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method tidak valid.', [], 405);
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    json_response(false, 'Payload tidak valid.', [], 400);
}

if (!verify_csrf($payload['csrf_token'] ?? '')) {
    json_response(false, 'Token keamanan tidak valid. Refresh halaman lalu coba lagi.', [], 403);
}

$input = $payload['input'] ?? [];
$clientName = clean_text($input['client_name'] ?? '', 120);
$deviceType = clean_text($input['device_type'] ?? '', 100);
$presetId = (int)($input['service_preset_id'] ?? 0);


function estimate_sync_client_from_input(array $input, string $clientName): int
{
    $selectedId = (int)($input['existing_client_id'] ?? 0);
    $phone = clean_text($input['client_phone'] ?? '', 80);
    $location = clean_text($input['location'] ?? '', 255);
    $type = clean_text($input['client_type'] ?? 'baru', 80);
    $notes = clean_text($input['client_notes'] ?? '', 1200);
    $wa = normalize_wa_number($phone);

    if ($selectedId > 0) {
        $stmt = db()->prepare('SELECT id FROM clients WHERE id = ? LIMIT 1');
        $stmt->execute([$selectedId]);

        if ($stmt->fetch()) {
            $stmt = db()->prepare('UPDATE clients SET
                name = ?,
                phone = COALESCE(NULLIF(?, \'\'), phone),
                whatsapp_normalized = COALESCE(NULLIF(?, \'\'), whatsapp_normalized),
                address = COALESCE(NULLIF(?, \'\'), address),
                client_type = COALESCE(NULLIF(?, \'\'), client_type),
                notes = CASE WHEN ? = \'\' THEN notes ELSE ? END,
                updated_at = NOW()
                WHERE id = ?');
            $stmt->execute([$clientName, $phone, $wa, $location, $type ?: 'langganan', $notes, $notes, $selectedId]);
            return $selectedId;
        }
    }

    return ensure_client($clientName, $phone, $location, $type ?: 'baru', $notes);
}


if ($clientName === '' || $deviceType === '' || $presetId <= 0) {
    json_response(false, 'Nama client, perangkat, dan layanan wajib diisi.', [], 422);
}

$preset = get_preset_by_id($presetId);
if (!$preset) {
    json_response(false, 'Preset layanan tidak ditemukan.', [], 404);
}

$addonIds = $input['addon_ids'] ?? [];
if (!is_array($addonIds)) {
    $addonIds = [];
}
$addons = get_addons_by_ids($addonIds);
$result = calculate_pricing($input, $preset, $addons);

try {
    db()->beginTransaction();

    $clientId = estimate_sync_client_from_input($input, $clientName);
    $code = 'EST-' . date('ymd-His') . '-' . strtoupper(bin2hex(random_bytes(3)));

    $stmt = db()->prepare('INSERT INTO estimates (
        estimate_code, client_name, client_phone, client_type, location, client_notes,
        device_type, device_brand, device_model, device_age, physical_condition, official_warranty,
        service_preset_id, service_name, addon_json, difficulty, risk_level, urgency, warranty,
        sparepart_cost, shipping_cost, material_cost, transport_cost, parking_cost, third_party_cost,
        hourly_rate, work_hours, testing_minutes, communication_minutes, margin_percent, minimum_margin_percent, discount, rounding_base,
        modal_total, jasa_dasar, time_cost, risk_fee, jasa_total, subtotal, urgency_fee, warranty_fee, before_margin, margin_profit,
        minimal_price, ideal_price, range_low, range_high, nego_price, profit, profit_percent, dp,
        whatsapp_message, warnings_json, status
    ) VALUES (
        :estimate_code, :client_name, :client_phone, :client_type, :location, :client_notes,
        :device_type, :device_brand, :device_model, :device_age, :physical_condition, :official_warranty,
        :service_preset_id, :service_name, :addon_json, :difficulty, :risk_level, :urgency, :warranty,
        :sparepart_cost, :shipping_cost, :material_cost, :transport_cost, :parking_cost, :third_party_cost,
        :hourly_rate, :work_hours, :testing_minutes, :communication_minutes, :margin_percent, :minimum_margin_percent, :discount, :rounding_base,
        :modal_total, :jasa_dasar, :time_cost, :risk_fee, :jasa_total, :subtotal, :urgency_fee, :warranty_fee, :before_margin, :margin_profit,
        :minimal_price, :ideal_price, :range_low, :range_high, :nego_price, :profit, :profit_percent, :dp,
        :whatsapp_message, :warnings_json, :status
    )');

    $stmt->execute([
        ':estimate_code' => $code,
        ':client_name' => $clientName,
        ':client_phone' => clean_text($input['client_phone'] ?? '', 80),
        ':client_type' => clean_text($input['client_type'] ?? '', 80),
        ':location' => clean_text($input['location'] ?? '', 160),
        ':client_notes' => clean_text($input['client_notes'] ?? '', 1000),
        ':device_type' => $deviceType,
        ':device_brand' => clean_text($input['device_brand'] ?? '', 120),
        ':device_model' => clean_text($input['device_model'] ?? '', 120),
        ':device_age' => clean_text($input['device_age'] ?? '', 80),
        ':physical_condition' => clean_text($input['physical_condition'] ?? '', 120),
        ':official_warranty' => clean_text($input['official_warranty'] ?? '', 80),
        ':service_preset_id' => $presetId,
        ':service_name' => $result['service_name'],
        ':addon_json' => json_encode($result['addon_names'], JSON_UNESCAPED_UNICODE),
        ':difficulty' => clean_text($input['difficulty'] ?? '', 40),
        ':risk_level' => clean_text($input['risk_level'] ?? '', 40),
        ':urgency' => clean_text($input['urgency'] ?? '', 40),
        ':warranty' => clean_text($input['warranty'] ?? '', 40),
        ':sparepart_cost' => (int)n($input['sparepart_cost'] ?? 0),
        ':shipping_cost' => (int)n($input['shipping_cost'] ?? 0),
        ':material_cost' => (int)n($input['material_cost'] ?? 0),
        ':transport_cost' => (int)n($input['transport_cost'] ?? 0),
        ':parking_cost' => (int)n($input['parking_cost'] ?? 0),
        ':third_party_cost' => (int)n($input['third_party_cost'] ?? 0),
        ':hourly_rate' => (int)n($input['hourly_rate'] ?? 0),
        ':work_hours' => n($input['work_hours'] ?? 0),
        ':testing_minutes' => (int)n($input['testing_minutes'] ?? 0),
        ':communication_minutes' => (int)n($input['communication_minutes'] ?? 0),
        ':margin_percent' => n($input['margin_percent'] ?? 0),
        ':minimum_margin_percent' => n($input['minimum_margin_percent'] ?? 0),
        ':discount' => (int)n($input['discount'] ?? 0),
        ':rounding_base' => (int)n($input['rounding_base'] ?? 5000),
        ':modal_total' => $result['modal_total'],
        ':jasa_dasar' => $result['jasa_dasar'],
        ':time_cost' => $result['time_cost'],
        ':risk_fee' => $result['risk_fee'],
        ':jasa_total' => $result['jasa_total'],
        ':subtotal' => $result['subtotal'],
        ':urgency_fee' => $result['urgency_fee'],
        ':warranty_fee' => $result['warranty_fee'],
        ':before_margin' => $result['before_margin'],
        ':margin_profit' => $result['margin_profit'],
        ':minimal_price' => $result['minimal_price'],
        ':ideal_price' => $result['ideal_price'],
        ':range_low' => $result['range_low'],
        ':range_high' => $result['range_high'],
        ':nego_price' => $result['nego_price'],
        ':profit' => $result['profit'],
        ':profit_percent' => $result['profit_percent'],
        ':dp' => $result['dp'],
        ':whatsapp_message' => $result['whatsapp_message'],
        ':warnings_json' => json_encode($result['warnings'], JSON_UNESCAPED_UNICODE),
        ':status' => 'draft',
    ]);

    $id = (int)db()->lastInsertId();
    update_client_stats($clientId);
    db()->commit();

    json_response(true, 'Estimasi berhasil disimpan ke database dan client tersimpan.', [
        'id' => $id,
        'client_id' => $clientId,
        'estimate_code' => $code,
        'result' => $result,
        'view_url' => 'estimate_view.php?id=' . $id,
    ]);
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    json_response(false, 'Gagal menyimpan estimasi/client: ' . $e->getMessage(), [], 500);
}
