<?php
/**
 * Service Pricing Calculator MySQL - cPanel Ready
 * 1) Buat database + user MySQL di cPanel.
 * 2) Edit kredensial database di bawah.
 * 3) Buka install.php untuk membuat tabel dan admin pertama.
 */

return [
    'app' => [
        'name' => 'NusaTech Care',
        'tagline' => 'Kalkulator Harga Jasa Service',
        'description' => 'Kalkulator harga service berbasis database MySQL untuk teknisi laptop, PC, Android, software, hardware, backup data, dan jasa panggilan.',
        'base_url' => '', // kosongkan kalau langsung di domain/subfolder yang sama
        'timezone' => 'Asia/Jakarta',
        'currency' => 'Rp',
        'whatsapp' => '6281252580812',
        'email' => 'vickypriyatna56@gmail.com',
        'location' => 'Indonesia',
    ],

    'database' => [
        'host' => 'localhost',
        'name' => 'vicj7142_calc_service',
        'user' => 'vicj7142_user_calc_service',
        'pass' => 'supriyanto',
        'charset' => 'utf8mb4',
    ],

    'security' => [
        // Ganti nilai ini sebelum upload. Pakai saat buka /install.php?key=ISI_KEY_INI
        'install_key' => 'nusatechcare-setup',
        'session_name' => 'svc_price_calc_session',
    ],

    'defaults' => [
        'hourly_rate' => 50000,
        'margin_percent' => 25,
        'minimum_margin_percent' => 12,
        'rounding_base' => 5000,
    ],

    'pricing_rules' => [
        'difficulty_multipliers' => [
            'mudah' => ['label' => 'Mudah', 'value' => 1.00],
            'sedang' => ['label' => 'Sedang', 'value' => 1.20],
            'sulit' => ['label' => 'Sulit', 'value' => 1.45],
            'berat' => ['label' => 'Berat', 'value' => 1.75],
        ],
        'risk_fees' => [
            'rendah' => ['label' => 'Rendah', 'fee' => 0],
            'sedang' => ['label' => 'Sedang', 'fee' => 25000],
            'tinggi' => ['label' => 'Tinggi', 'fee' => 50000],
            'sangat_tinggi' => ['label' => 'Sangat Tinggi', 'fee' => 100000],
        ],
        'urgency_fees' => [
            'normal' => ['label' => 'Normal', 'percent' => 0],
            'cepat' => ['label' => 'Cepat', 'percent' => 15],
            'hari_ini' => ['label' => 'Hari Ini Selesai', 'percent' => 25],
            'darurat' => ['label' => 'Darurat / Malam', 'percent' => 40],
        ],
        'warranty_fees' => [
            'none' => ['label' => 'Tanpa Garansi', 'percent' => 0],
            '3_hari' => ['label' => 'Garansi 3 Hari', 'percent' => 5],
            '7_hari' => ['label' => 'Garansi 7 Hari', 'percent' => 8],
            '30_hari' => ['label' => 'Garansi 30 Hari', 'percent' => 12],
        ],
        'client_types' => [
            'baru' => 'Client Baru',
            'langganan' => 'Langganan',
            'kantor' => 'Kantor / Instansi',
        ],
        'device_types' => [
            'Laptop',
            'PC / Komputer',
            'Android / HP',
            'Printer',
            'Jaringan',
            'Lainnya',
        ],
    ],
];
