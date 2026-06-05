# KASPINDO – Sistem POS UMKM & Retail

KASPINDO adalah sistem Point of Sale (POS) berbasis PHP dan MySQL untuk UMKM dan toko retail di Indonesia. Aplikasi ini membantu mengelola transaksi kasir, kontrol shift, manajemen stok, poin loyalitas, pengembalian barang, laporan penjualan (CSV/PDF), notifikasi Telegram, backup data, dan modul assistant AI yang siap diintegrasikan dengan API Codex.

## Fitur Utama

- **Transaksi Kasir**: Pencatatan penjualan dengan dukungan diskon dan metode pembayaran ganda.
- **Shift & Kontrol Kas**: Pengaturan shift kasir dan pelaporan arus kas harian.
- **Manajemen Stok**: Pemantauan stok barang dan peringatan saat stok menipis.
- **Poin Loyalitas**: Sistem poin untuk pelanggan setia.
- **Pengembalian & Refund**: Penanganan retur barang dan pengembalian dana.
- **Laporan CSV/PDF**: Pembuatan laporan penjualan dalam format CSV dan PDF.
- **Notifikasi Telegram**: Notifikasi otomatis ke Telegram untuk aktivitas penting.
- **Backup Workflow**: Fitur backup data untuk menjaga keamanan informasi.
- **Modul Assistant AI**: Modul siap pakai untuk integrasi dengan OpenAI Codex/ChatGPT.

## Tech Stack

- **Bahasa Pemrograman**: PHP 8.x
- **Database**: MySQL / MariaDB
- **Framework**: Laravel
- **Frontend**: Blade, Bootstrap
- **Testing**: Playwright

## Screenshot

Silakan lihat folder `docs/screenshots` untuk screenshot tampilan aplikasi. Setelah Anda menambahkan gambar dashboard, kasir, laporan, dan stok, tempatkan file di direktori tersebut dan perbarui referensi berikut:

![Dashboard](docs/screenshots/dashboard.png)
![Kasir](docs/screenshots/kasir.png)
![Laporan](docs/screenshots/laporan.png)
![Stok](docs/screenshots/stok.png)

## Instalasi Lokal

1. Clone repo ini:
   ```
   git clone https://github.com/mhmdhabibrafi/kasir-pintar.git
   cd kasir-pintar
   ```
2. Instal dependensi menggunakan Composer dan NPM:
   ```
   composer install
   npm install && npm run build
   ```
3. Salin file `.env.example` menjadi `.env` dan konfigurasi variabel lingkungan.
4. Generate key Laravel:
   ```
   php artisan key:generate
   ```
5. Konfigurasi database di `.env` dengan host, nama database, user, dan password.
6. Jalankan migrasi dan seeder (opsional):
   ```
   php artisan migrate --seed
   ```
7. Jalankan server pengembangan:
   ```
   php artisan serve
   ```

## Konfigurasi Database

Pastikan MySQL/MariaDB berjalan dan database sudah dibuat. Perbarui variabel `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` pada file `.env`.

## Cara Menjalankan Aplikasi

Setelah instalasi selesai, akses aplikasi melalui `http://localhost:8000` di browser. Login dengan akun default yang dihasilkan oleh seeder atau buat akun melalui fitur registrasi.

## Roadmap

Lihat file [ROADMAP.md](ROADMAP.md) untuk rencana pengembangan masa depan.

## Cara Berkontribusi

Kontribusi sangat kami hargai! Silakan baca panduan kontribusi di [CONTRIBUTING.md](CONTRIBUTING.md) untuk instruksi lengkap sebelum mengirim pull request.

## Lisensi

Proyek ini dilisensikan di bawah lisensi MIT. Silakan lihat file [LICENSE](LICENSE) untuk detailnya.
