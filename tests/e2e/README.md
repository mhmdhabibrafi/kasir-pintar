# KASPINDO E2E Tests

Tes browser ini dipakai untuk smoke test dan alur klik nyata di staging/VPS.

## Install

```bash
npm install
npx playwright install chromium
```

## Smoke Test Aman

```bash
E2E_BASE_URL=https://domain-anda.test npm run test:e2e
```

Default hanya menjalankan test non-destruktif: halaman publik, redirect auth, dan halaman yang bisa dicek tanpa mengubah data.

## Test Login Role

```bash
E2E_BASE_URL=https://domain-anda.test \
E2E_SUPERADMIN_USER=superadmin \
E2E_SUPERADMIN_PASS=isi-password \
E2E_ADMIN_USER=admin-toko \
E2E_ADMIN_PASS=isi-password \
npm run test:e2e
```

## Test Operasional yang Mengubah Data

Jalankan hanya di staging atau saat memang siap membuat data transaksi baru.

```bash
E2E_RUN_OPERATIONAL=1 \
E2E_ADMIN_USER=admin-toko \
E2E_ADMIN_PASS=isi-password \
npm run test:e2e
```

## Test Integrasi

Test backup Drive, Telegram, support chat, dan custom domain sengaja dipisah agar tidak terpencet di production.

```bash
E2E_RUN_INTEGRATIONS=1 \
E2E_SUPERADMIN_USER=superadmin \
E2E_SUPERADMIN_PASS=isi-password \
E2E_CUSTOM_DOMAIN_TEST=kaspindo-test.example.com \
npm run test:e2e
```
