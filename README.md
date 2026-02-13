# MY KASPIN

Aplikasi POS berbasis PHP + MySQL untuk operasional kasir, kontrol shift, kas harian, refund, member loyalitas, notifikasi Telegram, dan pelaporan.

## Ringkasan Fitur
- POS kasir mobile + desktop dengan transaksi atomic (`BEGIN/COMMIT/ROLLBACK`).
- Scan SKU/barcode dari kolom pencarian kasir (ketik/scan lalu `Enter`).
- Hold transaksi (simpan, lanjutkan, hapus) termasuk metadata diskon dan member.
- Shift harian dengan kontrol role.
- Kas harian otomatis: `cash_in/out`, sales cash, sales QRIS, refund, net saldo.
- Promo dan pricing engine: diskon item, diskon order, voucher, pajak, service, pembulatan.
- Member pelanggan + poin loyalitas otomatis saat transaksi sukses.
- Inventory virtual + alert stok menipis.
- Notifikasi Telegram: transaksi, refund, shift, stok menipis, rekap harian.
- Laporan kas harian + export CSV/PDF.
- Audit log operasional (file log + halaman web).

## Role dan Akses
- `admin`
  - Akses penuh dashboard, master data, kas harian, audit, pengaturan Telegram, member.
  - Bisa input kas (`cash_in`, `cash_out`) dan tutup shift dengan kas akhir.
- `bos`
  - Akses laporan, kas harian, audit, shift, kasir, member (view/monitor).
  - Bisa input kas (`cash_in`, `cash_out`) dan tutup shift dengan kas akhir.
- `karyawan`
  - Fokus operasional kasir + riwayat transaksi + absensi shift.
  - Hanya bisa mulai/selesai shift (tidak bisa input kas harian).

## Modul Penting
- `public/kasir.php`: POS transaksi.
- `public/shift.php`: absensi shift + kontrol kas berdasarkan role.
- `public/kasir_history.php`: riwayat transaksi kasir.
- `public/print_receipt.php`: print struk.
- `public/admin_cash_report.php`: laporan kas harian.
- `public/admin_cash_report_csv.php`: export CSV kas harian.
- `public/admin_cash_report_pdf.php`: export PDF kas harian.
- `public/admin_audit.php`: audit log berbasis web.
- `public/admin_refunds.php`: refund/retur.
- `public/admin_customers.php`: manajemen member pelanggan.
- `public/admin_notifications.php`: konfigurasi Telegram.
- `public/admin_inventory.php`: manajemen stok virtual.
- `public/admin_transactions.php`: kontrol/hapus data transaksi.

## Persyaratan
- PHP 8.x
- MySQL 5.7+ / MariaDB
- XAMPP/Laragon/LAMP
- Ekstensi PHP: `pdo_mysql`, `curl`, `fileinfo`

## Instalasi (XAMPP)
1. Taruh proyek ke `C:\xampp\htdocs\mykaspin`.
2. Buat database (ikuti nama di `app/config/database.php`, default saat ini `kasir_pintar`).
3. Import skema awal: `database/schema.sql`.
4. Atur koneksi DB di `app/config/database.php`.
5. Akses aplikasi: `http://localhost/mykaspin/public`.

## Migrasi Otomatis
Saat aplikasi berjalan, sistem akan memastikan tabel tambahan tersedia lewat helper migrasi.

Tabel tambahan yang dipakai:
- `shifts`, `shift_movements`
- `inventory_items`, `inventory_logs`
- `promo_settings`, `promo_vouchers`
- `refunds`, `refund_items`
- `transaction_meta`
- `customers`, `customer_point_logs`
- `notification_settings`

Pastikan user DB punya izin `CREATE` dan `ALTER`.

## Alur Member dan Poin
- Member dipilih saat checkout di halaman kasir (`customer_id`).
- Jika transaksi sukses, sistem menambah poin member otomatis.
- Formula poin default: `floor(total / 10000)`.
- Riwayat poin disimpan di `customer_point_logs`.
- Metadata member + poin transaksi ikut tersimpan di `transaction_meta`.
- Informasi member tampil di struk dan riwayat transaksi.

## Konfigurasi Telegram
Bisa diatur dari menu admin: `admin_notifications.php`.

Sumber konfigurasi:
1. Database (`notification_settings`) - prioritas utama.
2. Environment variable (fallback):
   - `TELEGRAM_BOT_TOKEN`
   - `TELEGRAM_CHAT_ID`

Contoh PowerShell sementara:
```powershell
$env:TELEGRAM_BOT_TOKEN="isi_token"
$env:TELEGRAM_CHAT_ID="-1001234567890"
```

Opsi notifikasi yang tersedia:
- Transaksi sukses.
- Refund.
- Shift (buka/tutup/pergerakan kas).
- Stok menipis setelah transaksi.
- Rekap harian otomatis (`HH:MM`).

## Kas Harian
Halaman: `admin_cash_report.php`

Isi laporan:
- Rekap per tanggal: `cash_in`, `cash_out`, `sales_cash`, `refund_cash`, `net_cash`, `sales_qris`, `refund_qris`, `net_qris`.
- Detail per shift: kas awal, arus kas, expected cash, kas akhir, selisih, sales QRIS.

Export:
- CSV: `admin_cash_report_csv.php`
- PDF: `admin_cash_report_pdf.php`

## Audit Log
- Halaman web: `admin_audit.php`
- File log: `storage/logs/audit.log`
- Security log: `storage/logs/security.log`

Log mencakup aksi penting seperti buka/tutup shift, pergerakan kas, refund, dan aktivitas admin lain.

## Reset Data (kecuali users/roles)
Gunakan:
- `database/clear_all_data.sql`

Jalankan via phpMyAdmin:
- Pilih database
- Import `database/clear_all_data.sql`

## Checklist Uji Cepat
- Login/logout semua role.
- Karyawan tidak bisa akses URL admin/bos.
- Karyawan tidak bisa input `cash_in/cash_out`.
- Admin/bos bisa input kas ke shift aktif.
- POS menolak transaksi jika shift belum dibuka.
- Scan SKU di kasir (`Enter`) menambah item yang tepat.
- Hold transaksi simpan dan load ulang dengan benar.
- Transaksi member menambah poin dan tampil di struk.
- Refund tercatat dan mempengaruhi laporan kas.
- Telegram kirim notifikasi sesuai toggle yang diaktifkan.
- Export CSV/PDF kas harian berhasil.
- Audit log muncul di `admin_audit.php`.

## Backup dan Restore
- Backup: export database via phpMyAdmin.
- Restore: import SQL ke database tujuan.

## Changelog
Lihat `CHANGELOG.md`.
