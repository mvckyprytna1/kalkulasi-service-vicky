# Service Pricing Calculator MySQL - cPanel Ready

Kalkulator harga jasa service berbasis **PHP native + MySQL + JavaScript vanilla**.

Cocok untuk:

- Service laptop
- Service PC
- Service Android
- Install ulang
- Backup data
- Upgrade SSD/RAM
- Cleaning
- Flashing Android
- Setting printer/jaringan
- Jasa panggilan

## Fitur

- Login admin
- Kalkulator harga live
- Simpan estimasi ke database MySQL
- Riwayat estimasi
- Detail estimasi
- Update status: draft, sent, deal, rejected, revised, paid, done
- Hapus estimasi
- Settings preset layanan
- Settings tambahan layanan
- Template WhatsApp otomatis
- Hitung harga minimal aman
- Hitung harga ideal
- Hitung range harga client
- Hitung batas nego
- Hitung profit
- Hitung saran DP
- Warning risiko

## Struktur File

```text
service-pricing-calculator-mysql-cpanel/
├── index.php
├── login.php
├── logout.php
├── calculator.php
├── history.php
├── estimate_view.php
├── settings.php
├── update_status.php
├── delete_estimate.php
├── install.php
├── database.sql
├── config.php
├── partials_nav.php
├── api/
│   └── save_estimate.php
├── lib/
│   ├── bootstrap.php
│   ├── repositories.php
│   └── pricing.php
├── assets/
│   ├── css/style.css
│   └── js/calculator.js
└── .htaccess
```

## Cara Install di cPanel

### 1. Buat Database MySQL

Di cPanel:

1. Buka **MySQL Databases**.
2. Buat database, contoh:

```text
cpaneluser_servicecalc
```

3. Buat user database, contoh:

```text
cpaneluser_servicecalc
```

4. Tambahkan user ke database.
5. Beri permission **ALL PRIVILEGES**.

### 2. Upload File

1. Buka **File Manager**.
2. Masuk ke `public_html` atau subfolder, contoh:

```text
public_html/kalkulator
```

3. Upload ZIP.
4. Extract ZIP.

### 3. Edit config.php

Edit bagian database:

```php
'database' => [
    'host' => 'localhost',
    'name' => 'cpaneluser_servicecalc',
    'user' => 'cpaneluser_servicecalc',
    'pass' => 'PASSWORD_DATABASE',
    'charset' => 'utf8mb4',
],
```

Edit juga install key:

```php
'install_key' => 'GANTI_INSTALL_KEY_UNIK',
```

Contoh:

```php
'install_key' => 'setup-rahasia-2026',
```

### 4. Jalankan Installer

Buka URL:

```text
https://domainmu.com/kalkulator/install.php?key=setup-rahasia-2026
```

Isi:

- username admin
- password admin

Klik **Buat Tabel & Admin**.

Installer akan membuat:

- tabel `admins`
- tabel `service_presets`
- tabel `addon_services`
- tabel `estimates`
- data preset awal
- data addon awal

### 5. Hapus install.php

Setelah berhasil login, hapus file:

```text
install.php
```

Ini penting supaya installer tidak bisa dibuka lagi.

## Cara Login

Buka:

```text
https://domainmu.com/kalkulator/login.php
```

Masukkan username dan password yang dibuat saat install.

## Halaman Penting

```text
calculator.php       Kalkulator utama
history.php          Riwayat estimasi
estimate_view.php    Detail estimasi
settings.php         Edit preset layanan dan addon
```

## Ganti Nomor WhatsApp

Di `config.php`:

```php
'whatsapp' => '6281252580812',
```

Format wajib pakai kode negara.

Benar:

```text
628123456789
```

Salah:

```text
08123456789
```

## Cara Kerja Harga

Formula utama:

```text
modal_total = sparepart + ongkir + bahan + transport + parkir + pihak ketiga
jasa_dasar = preset + addon
biaya_waktu = total_jam × rate_per_jam
jasa_total = jasa_dasar × multiplier_kesulitan + biaya_waktu + risiko
subtotal = modal_total + jasa_total
harga_sebelum_margin = subtotal + urgent_fee + warranty_fee
harga_ideal = harga_sebelum_margin + margin - diskon
```

Sistem juga menghitung:

- harga minimal aman
- range harga client
- harga nego terendah
- profit
- DP
- warning risiko

## Catatan Keamanan

Versi ini sudah punya:

- login admin
- password hashing dengan `password_hash`
- session login
- CSRF token
- PDO prepared statements
- escape output HTML
- `.htaccess` basic security

Tetap disarankan:

- hapus `install.php` setelah setup
- gunakan password admin kuat
- jangan share akses cPanel
- backup database rutin

## Next Upgrade

Fitur lanjutan yang bisa ditambah nanti:

- Export PDF invoice
- Cetak nota
- Multi teknisi
- Statistik profit bulanan
- Status pengerjaan service
- Data pelanggan
- Reminder follow-up WhatsApp
- Upload foto perangkat
