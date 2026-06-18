# Smart Pricing v2 Patch

Patch ini TIDAK mengubah desain. Yang diganti hanya logic kalkulasi:

- `lib/pricing.php`
- `assets/js/calculator.js`

## Yang diperbaiki

1. Jam kerja normal tidak lagi dihitung dobel.
   Harga preset dianggap sudah mencakup jam normal.
   Biaya jam hanya dihitung kalau melebihi estimasi normal.

2. Tambahan layanan dibundling otomatis.
   Kalau centang banyak tambahan driver/software, sistem tidak menjumlah brutal 100% semua.

3. Margin tidak dihajar penuh ke semua jasa.
   Untuk software ringan/driver, margin dibatasi supaya harga lebih waras.

4. Ada profil harga:
   - Software ringan / driver
   - Install / repair OS
   - Software umum
   - Backup / recovery data
   - Hardware / sparepart
   - On-site / panggilan

5. Ada cap harga wajar untuk software ringan/driver.
   Kasus driver biasa tidak lagi naik ke 400-500 ribuan kecuali ada urgent/onsite/sparepart/risiko berat.

## Cara pasang di cPanel

Upload dan replace file ini:

```text
lib/pricing.php
assets/js/calculator.js
```

Setelah upload:
1. Refresh halaman kalkulator pakai Ctrl + F5.
2. Coba hitung ulang kasus Bluetooth + brightness.
3. Simpan estimasi baru.

## Catatan penting

Jangan upload `config.php` dari full project kalau project sudah jalan di hosting,
karena bisa menimpa kredensial database.
Untuk aplikasi yang sudah terpasang, pakai ZIP patch ini saja.
