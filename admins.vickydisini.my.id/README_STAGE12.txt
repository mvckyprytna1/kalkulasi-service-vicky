# Stage 1 & 2 Service Workflow Upgrade

Patch ini menambah fitur operasional teknisi tanpa mengubah desain utama:

## Tahap 1
- Status pengerjaan service: Masuk, Dicek, Menunggu ACC, Dikerjakan, Menunggu Sparepart, Selesai, Sudah Diambil, Batal.
- Job Ticket / Service Order dengan kode `SRV-...`.
- Catatan teknisi internal.
- Status pembayaran, DP, lunas, sisa bayar.

## Tahap 2
- Nota / invoice HTML siap print.
- Template WhatsApp banyak mode.
- Garansi service otomatis dengan tanggal akhir.
- Database client + riwayat service.

## Cara pasang di project yang sudah jalan

1. Upload semua file dari ZIP patch ke folder project di cPanel.
2. Replace file lama jika diminta.
3. Jalankan upgrade database:

```text
https://domainmu.com/db_upgrade_stage12.php?key=INSTALL_KEY_DARI_CONFIG
```

Kalau project ada di subfolder:

```text
https://domainmu.com/kalkulator/db_upgrade_stage12.php?key=INSTALL_KEY_DARI_CONFIG
```

4. Kalau muncul berhasil, hapus file:

```text
db_upgrade_stage12.php
```

5. Tekan `Ctrl + F5` di browser.

## Menu baru

- Service: daftar Job Ticket.
- Client: database pelanggan.
- Template: edit template WhatsApp.

## Alur pakai

1. Buat estimasi seperti biasa di Kalkulator.
2. Buka Detail Estimasi.
3. Klik `Buat Job Ticket`.
4. Kelola status service, pembayaran, garansi, catatan teknisi.
5. Kirim template WhatsApp sesuai tahap pengerjaan.
6. Print nota dari halaman Job Ticket.

## Penting

Jangan upload full project ke hosting yang sudah jalan kalau takut `config.php` ketimpa. Pakai ZIP patch saja.
