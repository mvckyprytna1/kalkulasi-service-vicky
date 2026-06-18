-- Stage 1 & 2 Upgrade: Service Orders, Clients, Payments, Warranty, Templates
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS clients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(80) NULL,
    whatsapp_normalized VARCHAR(40) NULL,
    address VARCHAR(255) NULL,
    client_type VARCHAR(80) NULL,
    notes TEXT NULL,
    total_jobs INT UNSIGNED NOT NULL DEFAULT 0,
    total_spent INT UNSIGNED NOT NULL DEFAULT 0,
    last_service_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_phone (phone),
    INDEX idx_whatsapp (whatsapp_normalized),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_code VARCHAR(40) NOT NULL UNIQUE,
    estimate_id BIGINT UNSIGNED NULL,
    client_id BIGINT UNSIGNED NULL,
    client_name VARCHAR(120) NOT NULL,
    client_phone VARCHAR(80) NULL,
    location VARCHAR(255) NULL,
    device_type VARCHAR(100) NOT NULL,
    device_brand VARCHAR(120) NULL,
    device_model VARCHAR(120) NULL,
    serial_number VARCHAR(160) NULL,
    device_password VARCHAR(160) NULL,
    physical_condition VARCHAR(120) NULL,
    official_warranty VARCHAR(80) NULL,
    accessories TEXT NULL,
    complaint TEXT NULL,
    service_name VARCHAR(180) NOT NULL,
    addon_json JSON NULL,
    estimate_amount INT UNSIGNED NOT NULL DEFAULT 0,
    final_amount INT UNSIGNED NOT NULL DEFAULT 0,
    dp_amount INT UNSIGNED NOT NULL DEFAULT 0,
    paid_amount INT UNSIGNED NOT NULL DEFAULT 0,
    service_status ENUM('masuk','dicek','menunggu_acc','dikerjakan','menunggu_sparepart','selesai','sudah_diambil','batal') NOT NULL DEFAULT 'masuk',
    payment_status ENUM('belum_bayar','dp','lunas','refund') NOT NULL DEFAULT 'belum_bayar',
    warranty_days INT UNSIGNED NOT NULL DEFAULT 0,
    warranty_start_at DATE NULL,
    warranty_end_at DATE NULL,
    warranty_note TEXT NULL,
    technician_notes TEXT NULL,
    customer_notes TEXT NULL,
    received_at DATETIME NULL,
    finished_at DATETIME NULL,
    picked_up_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ticket (ticket_code),
    INDEX idx_client (client_id),
    INDEX idx_estimate (estimate_id),
    INDEX idx_service_status (service_status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    amount INT UNSIGNED NOT NULL DEFAULT 0,
    method VARCHAR(80) NULL,
    note TEXT NULL,
    paid_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order (order_id),
    INDEX idx_paid_at (paid_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_order_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    log_type VARCHAR(40) NOT NULL,
    old_value VARCHAR(255) NULL,
    new_value VARCHAR(255) NULL,
    note TEXT NULL,
    created_by VARCHAR(120) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order (order_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(80) NOT NULL UNIQUE,
    title VARCHAR(160) NOT NULL,
    body TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO whatsapp_templates (template_key, title, body, is_active, sort_order) VALUES
('cek_selesai','Barang sudah dicek','Halo Kak {nama}, perangkat {perangkat} dengan ticket {ticket} sudah selesai dicek.

Kendala: {keluhan}
Layanan yang disarankan: {layanan}
Estimasi/total saat ini: {total}

Apakah boleh dilanjutkan pengerjaannya?',1,1),
('minta_acc','Minta ACC Harga','Halo Kak {nama}, untuk ticket {ticket}, estimasi pengerjaan {layanan} pada {perangkat} adalah {total}.

Pengerjaan akan dilanjutkan setelah harga disetujui. Jika setuju, mohon balas: ACC.',1,2),
('minta_dp','Minta DP','Halo Kak {nama}, untuk ticket {ticket} diperlukan DP sebesar {dp}.

DP dibutuhkan terutama untuk pembelian sparepart / mengunci pengerjaan. Sisa pembayaran: {sisa}.',1,3),
('sedang_dikerjakan','Barang Sedang Dikerjakan','Halo Kak {nama}, update untuk ticket {ticket}: perangkat {perangkat} sedang dikerjakan.

Status saat ini: {status}. Nanti akan dikabari lagi setelah proses testing selesai.',1,4),
('barang_selesai','Barang Selesai','Halo Kak {nama}, perangkat {perangkat} dengan ticket {ticket} sudah selesai.

Total: {total}
Sudah dibayar: {dibayar}
Sisa: {sisa}
Garansi: {garansi_hari} hari sampai {garansi_sampai}

Silakan konfirmasi jadwal pengambilan.',1,5),
('reminder_ambil','Reminder Ambil Barang','Halo Kak {nama}, mengingatkan bahwa perangkat {perangkat} dengan ticket {ticket} sudah selesai dan bisa diambil.

Sisa pembayaran: {sisa}. Terima kasih.',1,6),
('garansi_habis','Garansi Habis','Halo Kak {nama}, info untuk ticket {ticket}: masa garansi service {perangkat} berakhir pada {garansi_sampai}.

Jika ada kendala sebelum tanggal tersebut, silakan segera kabari.',1,7),
('invoice_ringkas','Nota Ringkas','NOTA SERVICE
Ticket: {ticket}
Client: {nama}
Perangkat: {perangkat}
Layanan: {layanan}
Total: {total}
Dibayar: {dibayar}
Sisa: {sisa}
Status: {status_bayar}

{brand}',1,8)
ON DUPLICATE KEY UPDATE title=VALUES(title), body=VALUES(body), is_active=VALUES(is_active), sort_order=VALUES(sort_order);
