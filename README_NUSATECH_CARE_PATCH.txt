# NusaTech Care Brand + Premium Invoice Patch

Isi patch:
- config.php
- invoice.php
- error_log kosong untuk menimpa log lama yang masih berisi nama brand lama

Yang diubah:
1. Brand aplikasi menjadi NusaTech Care.
2. Email brand menjadi support@nusatechcare.id.
3. Install key menjadi nusatechcare-setup.
4. base_url dikosongkan supaya aman dipakai di domain/subfolder mana pun.
5. Desain nota/invoice diganti menjadi lebih modern, clean, premium, mobile-first, dan print-friendly.
6. Nota memakai Tailwind CDN + Vanilla JS untuk efek loading saat cetak/simpan PDF.

Yang tidak diubah:
- Flow aplikasi
- Database credentials
- Nomor WhatsApp
- Kalkulator
- Job ticket
- Client workflow
- Settings
- Preset layanan
- CSS utama aplikasi

Cara pasang:
1. Upload isi patch ke folder project di cPanel.
2. Replace file lama.
3. Tekan Ctrl + F5.
4. Buka Job Ticket > Print Nota.
5. Kalau masih ada file error_log lama, patch ini akan menimpanya kosong. Boleh juga hapus error_log dari hosting.

Catatan:
Invoice page memakai CDN Tailwind:
https://cdn.tailwindcss.com
