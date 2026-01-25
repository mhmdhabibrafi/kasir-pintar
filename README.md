ALAMGIR TEA & COFFE - Kasir Pintar
=================================

Ringkas
-------
POS kasir berbasis PHP + MySQL dengan fokus mobile-first, keamanan, dan laporan siap jual.

Persyaratan
-----------
- PHP 8.x
- MySQL 5.7+ / MariaDB
- XAMPP/Laragon/LAMP
- Extension PHP: pdo_mysql, curl, fileinfo

Instalasi (XAMPP)
-----------------
1) Taruh proyek di `C:\xampp\htdocs\kasir-pintar`
2) Import database yang sudah ada (tanpa ubah skema):
   - `database` folder hanya contoh/seed jika tersedia
3) Atur koneksi DB di `app/config/database.php`
4) Akses aplikasi:
   - `http://localhost/kasir-pintar/public`

Konfigurasi Telegram
--------------------
Token Telegram tidak disimpan di repo. Set via environment variable:
- `TELEGRAM_BOT_TOKEN`
- `TELEGRAM_CHAT_ID`

Opsi `.env` (di root project):
- Buat file `.env` lalu isi:
  - `TELEGRAM_BOT_TOKEN="isi_token"`
  - `TELEGRAM_CHAT_ID="-123456789"`

Contoh (Apache httpd.conf / vhost):
SetEnv TELEGRAM_BOT_TOKEN "isi_token"
SetEnv TELEGRAM_CHAT_ID "isi_chat_id"

Contoh (PowerShell sementara):
$env:TELEGRAM_BOT_TOKEN="isi_token"
$env:TELEGRAM_CHAT_ID="isi_chat_id"

Catatan: restart Apache setelah set env di server.

Akun Demo
---------
Gunakan akun yang sudah ada di database. Jika perlu, buat manual lewat menu Admin:
- Admin: akses penuh
- Bos/Owner: laporan + export + telegram + kasir
- Karyawan: kasir + riwayat

Lokasi Log
----------
- `storage/logs/security.log` (login gagal, CSRF, telegram error)
- `storage/logs/audit.log` (aksi admin, void item)

Fitur Operasional
-----------------
- POS kasir mobile-first + desktop rapi
- Transaksi atomic (BEGIN/COMMIT/ROLLBACK)
- Anti double submit via token transaksi
- Print struk: `print_receipt.php?id=ID&paper=58|80`
- Export laporan: PDF + CSV
- Shift kasir + cash in/out + close shift (tanpa ubah DB)
- Diskon item/order, voucher, pajak, service, pembulatan
- Refund/retur tanpa menghapus transaksi (opsi restock)
- Stok virtual (JSON) + batas minimum

Penyimpanan Data Tambahan (Tanpa Ubah DB)
----------------------------------------
Disimpan di `storage/data/`:
- `inventory.json` (stok virtual + histori adjustment)
- `promos.json` (pajak/service/pembulatan + voucher)
- `refunds.json` (log refund/retur)
- `shifts.json` (shift kasir + cash in/out)
- `transactions_meta.json` (diskon/pajak/rounding per transaksi)

Menu Baru
---------
- Shift & Kas: `shift.php`
- Kelola Stok: `admin_inventory.php`
- Promo & Pajak: `admin_promos.php`
- Refund & Retur: `admin_refunds.php`

Checklist Test Manual
---------------------
Security:
- Login/logout semua role + timeout session
- Karyawan akses admin/bos via URL -> ditolak
- CSRF invalid -> ditolak
- Rate limit login bekerja

POS:
- Checkout 1 item, multi item, qty besar
- Diskon item/order, voucher, pajak, pembulatan sesuai input
- Pembayaran pas/lebih; kembalian benar
- Spam klik bayar -> tidak terjadi transaksi dobel
- Print struk dari riwayat & setelah transaksi
- Shift belum dibuka -> transaksi ditolak

Mobile:
- Tidak ada horizontal scroll (login/kasir/riwayat/bos/admin)
- Bottom nav tidak menutup konten + safe-area OK
- Modal bayar bisa discroll; tombol mudah ditekan

Reports/Admin:
- Filter laporan benar, export PDF/CSV berhasil
- Admin CRUD validasi input bekerja
- Refund tercatat dan mengurangi net revenue

Backup & Restore (Singkat)
--------------------------
- Backup: export database via phpMyAdmin
- Restore: import file SQL ke database tujuan

Changelog
---------
Lihat `CHANGELOG.md`.
