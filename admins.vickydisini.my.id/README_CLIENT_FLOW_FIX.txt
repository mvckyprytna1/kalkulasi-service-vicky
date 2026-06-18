# Client Flow Fix Patch

Patch ini memperbaiki alur client:

1. Client baru otomatis masuk ke database `clients` saat estimasi disimpan.
2. Client langganan bisa dipilih dari database client saat bikin estimasi.
3. Pilih client lama akan auto-fill:
   - nama
   - nomor WhatsApp
   - lokasi
   - catatan
4. Revisi estimasi juga sinkron ke database client.
5. Tambah Job Ticket manual juga bisa pilih client lama.
6. Riwayat estimasi sekarang menampilkan tipe client dan tombol Revisi/Job.
7. Ada script sekali jalan untuk backfill client dari riwayat lama.

## Cara pasang

Upload isi patch ke folder project di cPanel dan replace file lama.

## Wajib jalankan sekali untuk data lama

Karena estimasi lama sebelumnya belum pernah masuk ke database client, buka:

https://domainmu.com/sync_clients_from_history.php?key=INSTALL_KEY_DARI_CONFIG

Kalau project di subfolder:

https://domainmu.com/kalkulator/sync_clients_from_history.php?key=INSTALL_KEY_DARI_CONFIG

Setelah sukses, hapus file:

sync_clients_from_history.php

## File yang diubah/ditambah

- calculator.php
- estimate_edit.php
- history.php
- service_order_new.php
- store_service_order.php
- api/save_estimate.php
- api/update_estimate.php
- api/client_search.php
- assets/js/calculator.js
- sync_clients_from_history.php

Tidak perlu upgrade database.
Design utama tidak diubah.
