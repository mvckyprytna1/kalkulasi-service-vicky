# Estimate Revision Workflow Patch

Patch ini memperbaiki alur:
- Estimasi awal cek 30K tidak lagi mentok.
- Setelah barang dicek dan ketemu kerusakan, estimasi bisa direvisi.
- Revisi estimasi akan menghitung ulang harga.
- Kalau estimasi sudah punya Job Ticket, Job Ticket ikut disinkronkan:
  - layanan
  - addon
  - total/final amount
  - DP
  - status bayar
  - client/perangkat dasar

## File yang ditambahkan / diubah

Tambahan:
- estimate_edit.php
- api/update_estimate.php

Perubahan:
- assets/js/calculator.js
- estimate_view.php
- history.php
- service_order_view.php

## Cara pasang di cPanel

Upload semua isi patch ke folder project, lalu replace file lama.

Tidak perlu upgrade database.

Setelah upload:
1. Buka Riwayat Estimasi.
2. Klik Revisi pada estimasi cek awal.
3. Ubah preset layanan / tambahan / sparepart / harga.
4. Klik Simpan Revisi Estimasi.
5. Jika job ticket sudah dibuat, sistem otomatis sinkron ke job ticket.

## Alur yang benar

1. Client datang: buat Estimasi Cek Awal, misal Rp30.000.
2. Setelah dicek: klik Revisi Estimasi.
3. Ubah ke kerusakan asli, misal ganti keyboard / repair driver / install ulang.
4. Kirim pesan revisi ke client.
5. Jika client ACC, buat Job Ticket atau buka Job Ticket yang sudah ada.
6. Job Ticket dipakai untuk tracking barang, status pengerjaan, pembayaran, garansi, dan nota.

## Catatan

Patch ini tidak mengubah design utama.
