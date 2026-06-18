# Stage 12 Fix: Edit/Delete Job Ticket + Client + Nota Profesional

Patch ini tidak mengubah desain utama aplikasi. Yang ditambah:

## Job Ticket
- Mode Edit khusus: `service_order_edit.php`
- Tombol Edit di daftar job ticket
- Tombol Hapus di daftar/detail/edit job ticket
- Detail job ticket sekarang lebih jelas sebagai halaman view
- Pembayaran, template chat, log aktivitas tetap ada

## Client
- Mode Edit client: `client_edit.php`
- Update data client: `client_update.php`
- Tombol Edit/Hapus di daftar client dan detail client
- Hapus client tidak menghapus riwayat job ticket. `client_id` dilepas, tapi data job tetap aman.

## Nota / Invoice
- Tampilan nota dibuat lebih profesional seperti nota perusahaan IT
- Header navy corporate
- Nomor invoice
- Data client dan ticket
- Tabel layanan
- Ringkasan pembayaran
- Stamp status bayar
- Catatan garansi
- Kolom tanda tangan
- Print-friendly

## Cara pasang
Upload dan replace file di patch ini ke folder project cPanel.

Tidak perlu upgrade database karena patch ini memakai tabel yang sudah ada.

Setelah upload:
1. Buka aplikasi.
2. Tekan `Ctrl + F5`.
3. Cek menu Service > Detail/Edit.
4. Cek menu Client > Detail/Edit.
5. Buka nota dari job ticket.

## File penting
- `service_order_view.php`
- `service_order_edit.php`
- `delete_service_order.php`
- `clients.php`
- `client_view.php`
- `client_edit.php`
- `client_update.php`
- `invoice.php`
- `assets/css/style.css`
