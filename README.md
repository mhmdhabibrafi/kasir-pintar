# KASPINDO

KASPINDO adalah aplikasi POS berbasis PHP dan MySQL untuk operasional kasir, shift, kas harian, stok, promo, member loyalitas, refund, notifikasi, backup, dan laporan toko.

Repository ini bernama **kasir-pintar**, sedangkan nama produk/aplikasinya tetap **KASPINDO**.

## Sorotan

- POS kasir untuk desktop dan mobile.
- Transaksi kasir dengan alur shift aktif.
- Scan SKU/barcode dari kolom pencarian kasir.
- Hold transaksi untuk menyimpan dan melanjutkan keranjang.
- Kontrol kas harian: kas masuk, kas keluar, sales cash, QRIS, refund, dan selisih kas.
- Promo dan pricing engine: diskon item, diskon order, voucher, pajak, service, dan pembulatan.
- Member pelanggan dan poin loyalitas otomatis.
- Inventory virtual dengan alert stok menipis.
- Refund/retur yang terhubung ke laporan kas.
- Laporan kas dan transaksi dengan export CSV/PDF.
- Notifikasi Telegram untuk aktivitas operasional penting.
- AI Assistant siap OpenAI Responses API untuk insight operasional dan workflow Codex-ready.
- Live support internal untuk toko dan superadmin.
- Panel superadmin untuk approval mitra, toko aktif, user tenant, custom domain, maintenance, dan health check.
- Backup database terjadwal dengan opsi integrasi penyimpanan eksternal.

## Role Aplikasi

- `admin`: mengelola operasional toko, master data, kas, laporan, member, stok, notifikasi, dan pengaturan toko.
- `bos`: memantau laporan, shift, kas, transaksi, stok, member, dan support.
- `karyawan`: fokus pada kasir, shift, dan riwayat transaksi sesuai izin yang diberikan.
- `superadmin`: mengelola tenant/toko, approval mitra, domain, user lintas toko, maintenance, backup, dan monitoring sistem.

## Teknologi

- PHP 8.x
- MySQL 5.7+ atau MariaDB
- PDO MySQL
- HTML/CSS/JavaScript
- Playwright untuk smoke test browser
- XAMPP, Laragon, LAMP, atau server PHP sejenis

Ekstensi PHP yang disarankan:

- `pdo_mysql`
- `curl`
- `openssl`
- `zlib`
- `fileinfo`

## Instalasi Lokal

1. Clone repository ke folder web server.

   ```bash
   git clone https://github.com/mhmdhabibrafi/kasir-pintar.git kaspindo
   ```

2. Buat database MySQL/MariaDB, misalnya:

   ```sql
   CREATE DATABASE kaspindo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. Import skema awal:

   ```bash
   mysql -u root -p kaspindo < database/schema.sql
   ```

4. Salin file konfigurasi environment:

   ```bash
   cp .env.example .env
   ```

   Di Windows PowerShell:

   ```powershell
   Copy-Item .env.example .env
   ```

5. Sesuaikan nilai database di `.env`.

6. Akses aplikasi dari browser:

   ```text
   http://localhost/kaspindo/public
   ```

## Konfigurasi Environment

Gunakan `.env.example` sebagai template. Nilai production seperti password database, token Telegram, credential backup, dan domain asli tidak boleh disimpan di repository.

Konfigurasi utama:

- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `APP_URL`
- `APP_BASE_PATH`
- `APP_FORCE_HTTPS`
- `APP_PLATFORM_HOSTS`
- `APP_DOMAIN_TARGET`
- `APP_SUPPORT_ENABLED`
- `TELEGRAM_BOT_TOKEN`
- `TELEGRAM_CHAT_ID`
- `AI_ASSISTANT_ENABLED`
- `OPENAI_API_KEY`
- `OPENAI_MODEL`
- `OPENAI_PROJECT`

Untuk mode SaaS/custom domain, arahkan domain mitra ke server pusat, lalu daftarkan domain tersebut dari panel superadmin. Detail operasional production sebaiknya disimpan di dokumentasi privat.

## AI Assistant dan Codex-Ready API

KASPINDO menyiapkan modul AI Assistant berbasis server-side API untuk membantu admin/bos/superadmin membaca insight operasional dari data transaksi, kas, stok, refund, dan performa produk.

Fitur yang tersedia:

- Halaman web: `admin_ai_assistant.php`
- Endpoint API internal: `POST /api/ai_assistant.php`
- Autentikasi endpoint memakai bearer token API KASPINDO.
- Credential OpenAI hanya dibaca dari `.env` di server.
- Snapshot data yang dikirim ke AI dibatasi pada ringkasan operasional, bukan password, token, credential backup, atau file private.
- Health check integrasi tampil di `system_health.php`.

Konfigurasi minimal:

```env
AI_ASSISTANT_ENABLED=1
OPENAI_API_KEY=
OPENAI_MODEL=gpt-5.5
OPENAI_PROJECT=
```

Modul ini mengikuti pola OpenAI Responses API untuk integrasi aplikasi dan siap dipakai bersama workflow Codex/code-generation pada project GitHub. Catatan: akses ChatGPT/Pro dan penggunaan OpenAI API dapat memiliki billing, credential, atau entitlement yang berbeda sesuai kebijakan OpenAI.

## Struktur Project

```text
app/        Core aplikasi: auth, helper, model, view, dan logic operasional
database/   Skema, migrasi, dan utilitas database
public/     Entry point web yang aman dijadikan document root
routes/     Routing aplikasi
tests/      Smoke test dan E2E test
storage/    Data runtime lokal, log, cache, dan credential private
backup/     Output backup runtime
```

Folder runtime seperti `storage/`, `backup/`, `public/uploads/`, `scratch/`, dan `node_modules/` tidak ikut dipush ke repository.

## Migrasi Database

Aplikasi memiliki helper migrasi otomatis untuk memastikan tabel dan kolom tambahan tersedia saat aplikasi berjalan. User database perlu memiliki izin `CREATE` dan `ALTER` pada environment yang menjalankan migrasi.

Untuk instalasi baru, tetap import `database/schema.sql` terlebih dahulu.

## Testing

Install dependency test:

```bash
npm install
npx playwright install chromium
```

Jalankan smoke test:

```bash
E2E_BASE_URL=http://localhost/kaspindo/public npm run test:e2e
```

Untuk test yang mengubah data, aktifkan flag khusus di environment test/staging saja.

## Backup dan Restore

Backup database bisa dijalankan dari panel admin/superadmin sesuai konfigurasi server. File dump, credential service account, log, dan konfigurasi backup runtime harus tetap berada di storage private dan tidak dipush ke repository.

Restore dilakukan dengan mengimpor file SQL backup ke database tujuan, lalu membuka aplikasi agar migrasi otomatis menyesuaikan struktur terbaru.

## Catatan Deploy

- Arahkan document root server ke folder `public/` jika memungkinkan.
- Jangan overwrite `.env` production saat deploy.
- Pastikan folder runtime dapat ditulis oleh web server.
- Simpan credential backup dan token integrasi di luar repository.
- Jalankan smoke test login, shift, kasir, refund, laporan, notifikasi, backup, dan custom domain setelah deploy.

## Keamanan Repository

Repository ini disiapkan agar aman untuk source control:

- `.env` dan varian environment lokal di-ignore.
- Log, cache, upload user, credential JSON, backup dump, dan file eksperimen lokal di-ignore.
- README publik hanya berisi dokumentasi penggunaan umum, bukan SOP detail production.
- Detail domain asli, token, credential, dan strategi operasional internal sebaiknya tetap disimpan di dokumentasi privat.

Jika repository dibuat publik, source code tetap dapat dipelajari oleh orang lain. Untuk melindungi kode dan gaya implementasi secara maksimal, gunakan repository private atau lisensi proprietary.

## Checklist Uji Cepat

- Login/logout untuk semua role.
- User tanpa izin tidak bisa membuka halaman admin.
- POS menolak transaksi jika shift belum aktif.
- Scan SKU/barcode menambahkan produk yang tepat.
- Hold transaksi bisa disimpan dan dilanjutkan.
- Kas masuk/keluar tercatat pada shift yang sesuai.
- Refund memengaruhi laporan kas.
- Member mendapat poin setelah transaksi sukses.
- Export CSV/PDF berjalan.
- Notifikasi Telegram terkirim sesuai konfigurasi.
- Superadmin dapat mengelola toko, user tenant, status toko, dan custom domain.
- Backup manual/terjadwal berhasil pada environment yang dikonfigurasi.

## Changelog

Lihat [CHANGELOG.md](CHANGELOG.md).

## Lisensi

Kode ini menggunakan lisensi proprietary. Lihat [LICENSE](LICENSE).
