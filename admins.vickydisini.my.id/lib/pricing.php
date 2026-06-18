<?php
require_once __DIR__ . '/bootstrap.php';

function n($value): float
{
    return is_numeric($value) ? (float)$value : 0.0;
}

function round_up_price(float $value, float $base): int
{
    $base = max(1000, $base ?: 5000);
    return (int)(ceil($value / $base) * $base);
}

function smart_text_pool(array $input, array $preset, array $addons): string
{
    $addonNames = [];
    foreach ($addons as $addon) {
        $addonNames[] = $addon['name'] ?? '';
    }

    return strtolower(implode(' ', array_filter([
        $preset['category'] ?? '',
        $preset['name'] ?? '',
        $preset['code'] ?? '',
        $preset['note'] ?? '',
        clean_text($input['device_type'] ?? '', 100),
        clean_text($input['client_notes'] ?? '', 400),
        implode(' ', $addonNames),
    ])));
}

function smart_contains_any(string $text, array $needles): bool
{
    foreach ($needles as $needle) {
        if ($needle !== '' && strpos($text, strtolower($needle)) !== false) {
            return true;
        }
    }
    return false;
}

function smart_pricing_profile(array $input, array $preset, array $addons): array
{
    $text = smart_text_pool($input, $preset, $addons);
    $modal = n($input['sparepart_cost'] ?? 0)
        + n($input['shipping_cost'] ?? 0)
        + n($input['material_cost'] ?? 0)
        + n($input['transport_cost'] ?? 0)
        + n($input['parking_cost'] ?? 0)
        + n($input['third_party_cost'] ?? 0);

    $hasOnsiteCost = n($input['transport_cost'] ?? 0) > 0 || n($input['parking_cost'] ?? 0) > 0;

    $profiles = [
        'driver_repair' => [
            'label' => 'Software ringan / driver',
            'addon_cap' => 95000,
            'addon_factors' => [1.00, 0.65, 0.35, 0.25],
            'included_hours_extra' => 0.50,
            'risk_factor' => 0.45,
            'margin_cap_percent' => 8,
            'warranty_cap' => 12000,
            'normal_cap' => 250000,
            'urgent_cap' => 350000,
            'range_low' => 0.88,
            'range_high' => 1.12,
            'min_profit_percent' => 8,
        ],
        'install_os' => [
            'label' => 'Install / repair OS',
            'addon_cap' => 150000,
            'addon_factors' => [1.00, 0.75, 0.45, 0.30],
            'included_hours_extra' => 0.75,
            'risk_factor' => 0.60,
            'margin_cap_percent' => 10,
            'warranty_cap' => 20000,
            'normal_cap' => 350000,
            'urgent_cap' => 450000,
            'range_low' => 0.90,
            'range_high' => 1.15,
            'min_profit_percent' => 10,
        ],
        'general_software' => [
            'label' => 'Software umum',
            'addon_cap' => 130000,
            'addon_factors' => [1.00, 0.70, 0.40, 0.30],
            'included_hours_extra' => 0.50,
            'risk_factor' => 0.55,
            'margin_cap_percent' => 10,
            'warranty_cap' => 18000,
            'normal_cap' => 300000,
            'urgent_cap' => 400000,
            'range_low' => 0.90,
            'range_high' => 1.15,
            'min_profit_percent' => 10,
        ],
        'data_recovery' => [
            'label' => 'Backup / recovery data',
            'addon_cap' => 450000,
            'addon_factors' => [1.00, 0.85, 0.65, 0.50],
            'included_hours_extra' => 0.25,
            'risk_factor' => 1.00,
            'margin_cap_percent' => 18,
            'warranty_cap' => 0,
            'normal_cap' => 0,
            'urgent_cap' => 0,
            'range_low' => 0.90,
            'range_high' => 1.25,
            'min_profit_percent' => 15,
        ],
        'hardware' => [
            'label' => 'Hardware / sparepart',
            'addon_cap' => 500000,
            'addon_factors' => [1.00, 0.90, 0.75, 0.60],
            'included_hours_extra' => 0.25,
            'risk_factor' => 0.85,
            'margin_cap_percent' => 15,
            'warranty_cap' => 35000,
            'normal_cap' => 0,
            'urgent_cap' => 0,
            'range_low' => 0.92,
            'range_high' => 1.18,
            'min_profit_percent' => 12,
        ],
        'onsite' => [
            'label' => 'On-site / panggilan',
            'addon_cap' => 450000,
            'addon_factors' => [1.00, 0.85, 0.65, 0.50],
            'included_hours_extra' => 0.25,
            'risk_factor' => 0.70,
            'margin_cap_percent' => 12,
            'warranty_cap' => 25000,
            'normal_cap' => 450000,
            'urgent_cap' => 600000,
            'range_low' => 0.90,
            'range_high' => 1.18,
            'min_profit_percent' => 12,
        ],
    ];

    if ($hasOnsiteCost || smart_contains_any($text, ['on-site', 'onsite', 'panggilan', 'transport', 'jaringan', 'router', 'wifi router', 'kantor'])) {
        return $profiles['onsite'];
    }

    if ($modal >= 150000 || smart_contains_any($text, ['ganti lcd', 'lcd', 'keyboard', 'baterai', 'battery', 'ssd', 'hdd', 'ram', 'fan', 'thermal', 'cleaning', 'engsel', 'casing', 'motherboard', 'short', 'konslet', 'hardware', 'sparepart'])) {
        return $profiles['hardware'];
    }

    if (smart_contains_any($text, ['recovery', 'bad sector', 'backup data besar', 'data besar', 'partisi error', 'clone drive risky'])) {
        return $profiles['data_recovery'];
    }

    if (smart_contains_any($text, ['install ulang', 'install windows', 'windows siap pakai', 'repair windows', 'boot windows', 'linux', 'dual boot', 'os'])) {
        return $profiles['install_os'];
    }

    if (smart_contains_any($text, ['driver', 'bluetooth', 'brightness', 'kontras', 'display', 'vga', 'wifi', 'audio', 'touchpad', 'hotkey', 'chipset', 'unknown device', 'device manager', 'pairing'])) {
        return $profiles['driver_repair'];
    }

    return $profiles['general_software'];
}

function smart_addon_total(array $addons, array $profile): array
{
    $prices = [];
    $names = [];

    foreach ($addons as $addon) {
        $prices[] = n($addon['price'] ?? 0);
        $names[] = $addon['name'] ?? '-';
    }

    rsort($prices, SORT_NUMERIC);

    $factors = $profile['addon_factors'] ?? [1, 0.7, 0.4, 0.3];
    $total = 0.0;

    foreach ($prices as $i => $price) {
        $factor = $factors[$i] ?? end($factors);
        $total += $price * $factor;
    }

    $cap = n($profile['addon_cap'] ?? 0);
    if ($cap > 0) {
        $total = min($total, $cap);
    }

    return [
        'total' => $total,
        'names' => $names,
        'raw_total' => array_sum($prices),
    ];
}

function smart_difficulty_fee(string $key, float $serviceBase, array $profile): float
{
    $profileLabel = $profile['label'] ?? '';

    if ($key === 'mudah') {
        return 0;
    }

    if (stripos($profileLabel, 'driver') !== false) {
        return match ($key) {
            'sedang' => max(10000, $serviceBase * 0.06),
            'sulit' => max(30000, $serviceBase * 0.14),
            'berat' => max(55000, $serviceBase * 0.25),
            default => 0,
        };
    }

    if (stripos($profileLabel, 'Install') !== false || stripos($profileLabel, 'Software') !== false) {
        return match ($key) {
            'sedang' => max(15000, $serviceBase * 0.08),
            'sulit' => max(40000, $serviceBase * 0.18),
            'berat' => max(75000, $serviceBase * 0.32),
            default => 0,
        };
    }

    return match ($key) {
        'sedang' => max(20000, $serviceBase * 0.10),
        'sulit' => max(60000, $serviceBase * 0.22),
        'berat' => max(120000, $serviceBase * 0.38),
        default => 0,
    };
}

function smart_cap_price(float $price, float $minimal, array $profile, string $urgencyKey, float $modalTotal): float
{
    if ($modalTotal > 0) {
        return max($minimal, $price);
    }

    $cap = ($urgencyKey === 'normal')
        ? n($profile['normal_cap'] ?? 0)
        : n($profile['urgent_cap'] ?? 0);

    if ($cap <= 0) {
        return max($minimal, $price);
    }

    return max($minimal, min($price, $cap));
}

function calculate_pricing(array $input, array $preset, array $addons): array
{
    $cfg = app_config();
    $rules = $cfg['pricing_rules'];

    $profile = smart_pricing_profile($input, $preset, $addons);

    $difficultyKey = clean_text($input['difficulty'] ?? 'mudah', 40);
    $riskKey = clean_text($input['risk_level'] ?? ($preset['risk_level'] ?? 'rendah'), 40);
    $urgencyKey = clean_text($input['urgency'] ?? 'normal', 40);
    $warrantyKey = clean_text($input['warranty'] ?? 'none', 40);

    $difficulty = $rules['difficulty_multipliers'][$difficultyKey] ?? ['label' => 'Mudah', 'value' => 1];
    $risk = $rules['risk_fees'][$riskKey] ?? ['label' => 'Rendah', 'fee' => 0];
    $urgency = $rules['urgency_fees'][$urgencyKey] ?? ['label' => 'Normal', 'percent' => 0];
    $warranty = $rules['warranty_fees'][$warrantyKey] ?? ['label' => 'Tanpa Garansi', 'percent' => 0];

    $sparepartCost = n($input['sparepart_cost'] ?? 0);
    $shippingCost = n($input['shipping_cost'] ?? 0);
    $materialCost = n($input['material_cost'] ?? 0);
    $transportCost = n($input['transport_cost'] ?? 0);
    $parkingCost = n($input['parking_cost'] ?? 0);
    $thirdPartyCost = n($input['third_party_cost'] ?? 0);

    $modalTotal = $sparepartCost + $shippingCost + $materialCost + $transportCost + $parkingCost + $thirdPartyCost;

    $addonCalc = smart_addon_total($addons, $profile);
    $addonTotal = $addonCalc['total'];
    $addonRawTotal = $addonCalc['raw_total'];
    $addonNames = $addonCalc['names'];

    $presetBase = n($preset['base_price'] ?? 0);
    $jasaDasar = $presetBase + $addonTotal;

    $workHours = n($input['work_hours'] ?? ($preset['default_hours'] ?? 1));
    $testingHours = n($input['testing_minutes'] ?? 0) / 60;
    $communicationHours = n($input['communication_minutes'] ?? 0) / 60;
    $totalHours = $workHours + $testingHours + $communicationHours;

    $includedHours = max(0.5, n($preset['default_hours'] ?? 1) + n($profile['included_hours_extra'] ?? 0.5));
    $extraHours = max(0, $totalHours - $includedHours);

    $hourlyRate = n($input['hourly_rate'] ?? ($cfg['defaults']['hourly_rate'] ?? 50000));
    $timeCost = $extraHours * $hourlyRate;

    $difficultyFee = smart_difficulty_fee($difficultyKey, $jasaDasar, $profile);
    $riskFee = n($risk['fee'] ?? 0) * n($profile['risk_factor'] ?? 1);

    $serviceSubtotal = $jasaDasar + $difficultyFee + $timeCost + $riskFee;
    $subtotal = $modalTotal + $serviceSubtotal;

    $urgencyFee = $serviceSubtotal * (n($urgency['percent'] ?? 0) / 100);
    $warrantyFee = $serviceSubtotal * (n($warranty['percent'] ?? 0) / 100);
    $warrantyCap = n($profile['warranty_cap'] ?? 0);
    if ($warrantyCap > 0) {
        $warrantyFee = min($warrantyFee, $warrantyCap);
    }

    $beforeMargin = $subtotal + $urgencyFee + $warrantyFee;

    $marginPercent = n($input['margin_percent'] ?? ($cfg['defaults']['margin_percent'] ?? 25));
    $minimumMarginPercent = n($input['minimum_margin_percent'] ?? ($cfg['defaults']['minimum_margin_percent'] ?? 12));
    $discount = n($input['discount'] ?? 0);
    $roundingBase = n($input['rounding_base'] ?? ($cfg['defaults']['rounding_base'] ?? 5000));

    $serviceMarginPercent = min($marginPercent, n($profile['margin_cap_percent'] ?? 10));
    $modalMarginPercent = $modalTotal > 0 ? min($marginPercent, 18) : 0;

    $marginProfit = ($serviceSubtotal * ($serviceMarginPercent / 100)) + ($modalTotal * ($modalMarginPercent / 100));

    $minimalRaw = $modalTotal + $serviceSubtotal;
    $minimalPrice = round_up_price($minimalRaw, $roundingBase);

    $idealRaw = max(0, $beforeMargin + $marginProfit - $discount);
    $idealRaw = smart_cap_price($idealRaw, $minimalPrice, $profile, $urgencyKey, $modalTotal);
    $idealPrice = round_up_price($idealRaw, $roundingBase);

    $negoBase = max($minimalPrice * (1 + ($minimumMarginPercent / 100)), $idealPrice * 0.82);
    $negoPrice = round_up_price(min($negoBase, $idealPrice), $roundingBase);

    $rangeLow = round_up_price(max($negoPrice, $idealPrice * n($profile['range_low'] ?? 0.9)), $roundingBase);
    $rangeHighRaw = $idealPrice * n($profile['range_high'] ?? 1.15);
    $rangeHighRaw = smart_cap_price($rangeHighRaw, $rangeLow, $profile, $urgencyKey, $modalTotal);
    $rangeHigh = round_up_price(max($rangeHighRaw, $rangeLow), $roundingBase);

    $dp = $modalTotal >= 150000 ? round_up_price(max($modalTotal, $sparepartCost * 0.70), $roundingBase) : 0;
    $profit = max(0, $idealPrice - $modalTotal);
    $profitPercent = $idealPrice > 0 ? ($profit / $idealPrice) * 100 : 0;

    $serviceName = (string)($preset['name'] ?? 'Layanan Service');
    $warnings = [];

    if ($addonRawTotal > 0 && $addonTotal < $addonRawTotal) {
        $warnings[] = 'Smart bundle aktif: beberapa tambahan layanan didiskon otomatis supaya harga tidak dobel hitung.';
    }
    if ($extraHours <= 0 && $totalHours > 0) {
        $warnings[] = 'Jam kerja normal sudah dianggap masuk ke harga preset. Biaya jam hanya dihitung kalau melewati estimasi normal.';
    }
    if ($idealPrice <= $minimalPrice) {
        $warnings[] = 'Harga ideal dekat dengan harga aman. Jangan kasih diskon terlalu besar.';
    }
    if ($sparepartCost >= 250000) {
        $warnings[] = 'Modal sparepart besar. Sebaiknya minta DP sebelum beli part.';
    }
    if (in_array($riskKey, ['tinggi', 'sangat_tinggi'], true) && $warrantyKey === '30_hari') {
        $warnings[] = 'Risiko tinggi dengan garansi 30 hari. Naikkan harga atau batasi scope garansi.';
    }
    if (stripos($serviceName, 'recovery') !== false || stripos($serviceName, 'data') !== false) {
        $warnings[] = 'Untuk data/recovery, jangan janjikan data 100% kembali. Sampaikan sebagai estimasi kemungkinan.';
    }
    if ($profitPercent < n($profile['min_profit_percent'] ?? 10)) {
        $warnings[] = 'Profit tipis untuk tipe pekerjaan ini. Cek modal, diskon, atau jam kerja tambahan.';
    }

    $clientName = clean_text($input['client_name'] ?? '', 80);
    $device = trim(implode(' ', array_filter([
        clean_text($input['device_type'] ?? 'perangkat', 80),
        clean_text($input['device_brand'] ?? '', 100),
        clean_text($input['device_model'] ?? '', 100),
    ])));

    $clientGreeting = $clientName !== '' ? 'Kak ' . $clientName : 'Kak';
    $dpLine = $dp > 0 ? "\n\nUntuk pekerjaan ini disarankan DP sekitar " . money($dp) . ", terutama jika perlu pembelian sparepart atau risiko pengerjaan cukup tinggi." : '';

    $waMessage = "Halo {$clientGreeting}, untuk kendala {$device} dengan kebutuhan {$serviceName}, estimasi awal pengerjaan ada di kisaran " . money($rangeLow) . " - " . money($rangeHigh) . ".\n\nHarga final tetap menunggu pengecekan langsung, terutama untuk memastikan kondisi perangkat, driver/sistem, data, dan sparepart jika diperlukan." . $dpLine . "\n\nEstimasi ini dibuat berdasarkan tingkat kesulitan, risiko, dan scope pekerjaan supaya harga tetap masuk akal.";

    return [
        'service_name' => $serviceName,
        'service_category' => $preset['category'] ?? '-',
        'pricing_profile' => $profile['label'] ?? 'Umum',
        'addon_names' => $addonNames,
        'addon_total' => (int)round($addonTotal),
        'addon_raw_total' => (int)round($addonRawTotal),
        'jasa_dasar' => (int)round($jasaDasar),
        'sparepart_cost' => (int)round($sparepartCost),
        'modal_total' => (int)round($modalTotal),
        'time_cost' => (int)round($timeCost),
        'included_hours' => round($includedHours, 2),
        'extra_hours' => round($extraHours, 2),
        'total_hours' => round($totalHours, 2),
        'difficulty_label' => $difficulty['label'] ?? $difficultyKey,
        'difficulty_multiplier' => n($difficulty['value'] ?? 1),
        'difficulty_fee' => (int)round($difficultyFee),
        'risk_label' => $risk['label'] ?? $riskKey,
        'risk_fee' => (int)round($riskFee),
        'urgency_label' => $urgency['label'] ?? $urgencyKey,
        'urgency_fee' => (int)round($urgencyFee),
        'warranty_label' => $warranty['label'] ?? $warrantyKey,
        'warranty_fee' => (int)round($warrantyFee),
        'jasa_total' => (int)round($serviceSubtotal),
        'subtotal' => (int)round($subtotal),
        'before_margin' => (int)round($beforeMargin),
        'margin_profit' => (int)round($marginProfit),
        'discount' => (int)round($discount),
        'minimal_price' => $minimalPrice,
        'ideal_price' => $idealPrice,
        'range_low' => $rangeLow,
        'range_high' => $rangeHigh,
        'nego_price' => $negoPrice,
        'profit' => (int)round($profit),
        'profit_percent' => round($profitPercent, 2),
        'dp' => $dp,
        'warnings' => $warnings,
        'whatsapp_message' => $waMessage,
    ];
}
