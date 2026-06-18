# Client Picker Estimasi Fix

Masalah:
- Di kalkulator estimasi, tipe client sudah ada.
- Tapi field "Pilih Client Langganan" bisa tidak muncul karena sebelumnya hidden default dan bergantung JS.
- Kalau JS lama masih ke-cache, dropdown tetap hilang.

Fix:
- Picker client langganan/kantor sekarang selalu tampil.
- Pilih client lama tetap auto-fill:
  - nama
  - nomor WhatsApp
  - lokasi
  - catatan
- Memilih client lama otomatis mengubah tipe client menjadi "langganan".
- JS diberi query version supaya browser tidak pakai cache lama.

File yang diubah:
- calculator.php
- estimate_edit.php
- service_order_new.php
- assets/js/calculator.js

Cara pasang:
1. Upload isi patch ke folder project di cPanel.
2. Replace file lama.
3. Buka kalkulator.
4. Tekan Ctrl + F5.
5. Kalau dropdown masih kosong datanya, jalankan sync:
   sync_clients_from_history.php?key=INSTALL_KEY_DARI_CONFIG

Catatan:
Patch ini tidak mengubah database dan tidak mengubah desain utama.
