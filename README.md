project:
  name: "KASPIN — Kasir Pintar"
  version: "1.0"
  author: "Muhammad Habib Rafi"
  type: "Point of Sale (POS)"
  stack:
    backend: "PHP 8.x"
    database: "MySQL 5.7+ / MariaDB"
    frontend: "Mobile-first responsive UI"
  tagline: "POS modern, aman, mobile-first, dan siap dijual"

overview:
  description: >
    KASPIN adalah sistem kasir (Point of Sale) berbasis PHP dan MySQL
    yang dirancang khusus untuk kebutuhan UMKM dan toko modern.
    Fokus utama KASPIN adalah kemudahan penggunaan di perangkat mobile,
    keamanan transaksi, dan laporan bisnis yang siap digunakan untuk
    pengambilan keputusan.
  target_users:
    - "Toko retail"
    - "Warung & UMKM"
    - "Coffee shop"
    - "Mini market"
    - "Bisnis offline yang membutuhkan POS stabil & ringan"

key_value_proposition:
  - "Mobile-first: nyaman dipakai di HP kasir"
  - "Keamanan tinggi tanpa framework berat"
  - "Transaksi anti double submit & atomic"
  - "Tanpa mengubah skema database existing"
  - "Laporan lengkap & siap dijual ke klien"

core_features:
  pos:
    - "Kasir mobile-first & desktop friendly"
    - "Transaksi atomic (BEGIN / COMMIT / ROLLBACK)"
    - "Anti double klik / double submit"
    - "Diskon item & order"
    - "Voucher promo"
    - "Pajak, service charge & pembulatan"
    - "Refund / retur tanpa menghapus transaksi"
    - "Stok virtual + batas minimum"
  print_and_export:
    - "Print struk ukuran 58mm & 80mm"
    - "Cetak ulang struk dari riwayat transaksi"
    - "Export laporan ke PDF & CSV"
  roles_and_access:
    admin:
      permissions:
        - "Akses penuh sistem"
        - "Manajemen data & konfigurasi"
    owner:
      permissions:
        - "Laporan penjualan"
        - "Export data"
        - "Notifikasi Telegram"
        - "Akses kasir"
    employee:
      permissions:
        - "Kasir"
        - "Riwayat transaksi"
  shift_and_cash:
    - "Buka & tutup shift kasir"
    - "Cash in & cash out"
    - "Transaksi ditolak jika shift belum dibuka"
    - "Semua aktivitas tercatat"

telegram_integration:
  enabled: true
  description: >
    Sistem mendukung notifikasi Telegram untuk error,
    keamanan, dan aktivitas penting. Token Telegram tidak
    disimpan di repository demi keamanan.
  environment_variables:
    - "TELEGRAM_BOT_TOKEN"
    - "TELEGRAM_CHAT_ID"

data_storage_without_db_changes:
  description: >
    Untuk menjaga kompatibilitas dengan database existing,
    data tambahan disimpan dalam file JSON tanpa
    mengubah skema database utama.
  files:
    inventory_json: "Stok virtual & histori penyesuaian"
    promos_json: "Pajak, service charge & voucher"
    refunds_json: "Log refund & retur"
    shifts_json: "Shift kasir & cash flow"
    transactions_meta_json: "Diskon, pajak & pembulatan per transaksi"

system_requirements:
  php: ">= 8.x"
  database: "MySQL 5.7+ / MariaDB"
  server: "XAMPP / Laragon / LAMP"
  php_extensions:
    - "pdo_mysql"
    - "curl"
    - "fileinfo"

installation_xampp:
  steps:
    - "Letakkan project di C:\\xampp\\htdocs\\kasir-pintar"
    - "Import database yang sudah ada (tanpa mengubah skema)"
    - "Atur koneksi database di app/config/database.php"
    - "Akses aplikasi via http://localhost/kasir-pintar/public"

logging:
  security_log: "storage/logs/security.log (login gagal, CSRF, Telegram error)"
  audit_log: "storage/logs/audit.log (aksi admin & void transaksi)"

manual_testing_checklist:
  security:
    - "Login & logout semua role"
    - "Proteksi akses admin & owner"
    - "CSRF protection berjalan"
    - "Rate limit login aktif"
  pos:
    - "Transaksi single & multi item"
    - "Diskon, voucher, pajak & pembulatan valid"
    - "Anti spam klik bayar"
    - "Print & reprint struk"
    - "Shift wajib aktif"
  mobile_ui:
    - "Tidak ada horizontal scroll"
    - "Bottom navigation tidak menutup konten"
    - "Modal pembayaran bisa discroll"

backup_and_restore:
  backup: "Export database via phpMyAdmin"
  restore: "Import file SQL ke database tujuan"

selling_points:
  - "Siap dijual ke UMKM tanpa biaya lisensi mahal"
  - "Cocok untuk POS custom atau white-label"
  - "Ringan, stabil, dan mudah dikembangkan"
  - "Ideal untuk portofolio, produk komersial, atau SaaS lokal"

future_potential:
  - "Konversi ke APK (Flutter / WebView)"
  - "Integrasi payment gateway"
  - "Cloud sync multi-outlet"
  - "Dashboard analytics lanjutan"
