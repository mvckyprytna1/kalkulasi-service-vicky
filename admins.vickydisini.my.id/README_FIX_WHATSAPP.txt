# Fix WhatsApp Client Phone

Patch ini memperbaiki bug tombol "Buka WhatsApp" yang sebelumnya membuka nomor toko/admin dari config.php.

Sekarang:
- estimate_view.php membuka WhatsApp ke nomor client dari kolom client_phone.
- assets/js/calculator.js membuka WhatsApp ke nomor client dari input form.
- Nomor 08xxxx otomatis diubah menjadi 628xxxx.
- Nomor 812xxxx otomatis diubah menjadi 62812xxxx.

## File yang harus diupload dan replace

1. estimate_view.php
2. assets/js/calculator.js

## Cara pasang di cPanel

Upload file sesuai struktur:

estimate_view.php
assets/js/calculator.js

Setelah upload:
1. Buka halaman kalkulator.
2. Tekan Ctrl + F5.
3. Buka detail estimasi.
4. Klik Buka WhatsApp.

Jangan upload full project kalau config.php hosting sudah berisi database asli.
