NusaTech Care - Nota Mode 2 V2

Yang ditambah dari Nota Mode 2 lama:
- Link nota sekarang lebih rapi: pakai ticket code + signature + expired timestamp.
- Link nota punya masa berlaku otomatis 30 hari.
- Kalau link kedaluwarsa / salah signature, halaman error tampil rapi.
- Watermark/stamp otomatis:
  - LUNAS
  - DP / CICIL
  - BELUM LUNAS
- QR Code nota tampil di panel detail.
- Client bisa:
  - Download PNG
  - Cetak / Simpan PDF
  - Salin Link Nota
  - Kirim ke WhatsApp
- Nota 1 lama tetap tidak disentuh.
- Dari Job Ticket tetap ada:
  - Nota 1
  - Nota 2
  - Copy Link Nota 2

File yang diubah:
- lib/bootstrap.php
- service_order_view.php
- invoice_public.php

Cara pasang:
1. Upload isi patch ke folder project cPanel.
2. Replace file lama.
3. Buka Job Ticket.
4. Klik Nota 2 / Link Client.
5. Copy link dan kirim ke client.

Catatan:
- Tidak perlu upgrade database.
- Flow utama tidak diubah.
- Fitur lama tetap aman.
