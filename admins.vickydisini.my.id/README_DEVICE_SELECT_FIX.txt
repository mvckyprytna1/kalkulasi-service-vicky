# Device Select Revision Fix

Masalah:
- Di halaman Revisi Estimasi, field "Jenis Perangkat" kosong.
- Penyebabnya `estimate_edit.php` mengambil config dari `$config['device_types']`.
- Struktur config yang benar adalah `$config['pricing_rules']['device_types']`.

Efek bug:
- Select jenis perangkat kosong.
- Saat simpan revisi, device_type bisa ikut kosong.
- Revisi estimasi bisa gagal / data jadi aneh.

Fix:
- `estimate_edit.php` sekarang mengambil device types dari:
  `$config['pricing_rules']['device_types']`
- Ada fallback kalau config lama beda struktur.
- Kalau data lama punya device_type yang tidak ada di list, tetap ditampilkan.

File yang perlu upload:
- estimate_edit.php

Cara pasang:
1. Upload `estimate_edit.php` ke folder project di cPanel.
2. Replace file lama.
3. Buka halaman Revisi Estimasi.
4. Tekan Ctrl + F5.
5. Field Jenis Perangkat harus muncul lagi, misalnya Laptop / PC / Android.

Tidak perlu upgrade database.
Tidak mengubah desain utama.
