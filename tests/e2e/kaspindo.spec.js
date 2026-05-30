const { test, expect } = require('@playwright/test');

const baseURL = process.env.E2E_BASE_URL || 'http://localhost/kaspindo/public';

const credentials = {
  superadmin: {
    username: process.env.E2E_SUPERADMIN_USER || '',
    password: process.env.E2E_SUPERADMIN_PASS || ''
  },
  admin: {
    username: process.env.E2E_ADMIN_USER || '',
    password: process.env.E2E_ADMIN_PASS || ''
  },
  bos: {
    username: process.env.E2E_BOS_USER || '',
    password: process.env.E2E_BOS_PASS || ''
  },
  kasir: {
    username: process.env.E2E_KASIR_USER || '',
    password: process.env.E2E_KASIR_PASS || ''
  }
};

const runOperational = process.env.E2E_RUN_OPERATIONAL === '1';
const runIntegrations = process.env.E2E_RUN_INTEGRATIONS === '1';

function appUrl(path = '') {
  const normalizedBase = baseURL.endsWith('/') ? baseURL : `${baseURL}/`;
  return new URL(path.replace(/^\//, ''), normalizedBase).toString();
}

function hasCredential(role) {
  return credentials[role].username !== '' && credentials[role].password !== '';
}

async function login(page, role) {
  const credential = credentials[role];
  await page.goto(appUrl('login.php'));
  await page.locator('input[name="username"]').fill(credential.username);
  await page.locator('input[name="password"]').fill(credential.password);
  await page.locator('button[type="submit"]').click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('input[name="password"]')).toHaveCount(0);
}

async function ensureShiftOpen(page) {
  await page.goto(appUrl('shift.php'));
  const openButton = page.getByTestId('shift-open-submit').first();
  if (await openButton.isVisible().catch(() => false)) {
    const openingCash = page.locator('input[name="opening_cash"]');
    if (await openingCash.isVisible().catch(() => false)) {
      await openingCash.fill('0');
    }
    await openButton.click();
    await page.waitForLoadState('networkidle');
  }
  await expect(page.getByText(/Shift aktif/i)).toBeVisible();
}

test.describe('public pages', () => {
  test('registration form is clear and not promotional', async ({ page }) => {
    await page.goto(appUrl('daftar-mitra'));

    await expect(page).toHaveTitle(/Pendaftaran Toko KASPINDO/i);
    await expect(page.getByRole('heading', { name: 'Pendaftaran Toko' })).toBeVisible();
    await expect(page.locator('input[name="referral_code"]')).toBeVisible();
    await expect(page.locator('input[name="store_name"]')).toBeVisible();
    await expect(page.locator('textarea[name="store_address"]')).toBeVisible();
    await expect(page.locator('input[name="admin_username"]')).toBeVisible();
    await expect(page.getByRole('button', { name: /Kirim Pengajuan|Maintenance Aktif/i })).toBeVisible();

    await expect(page.getByText(/Official partner intake|Referral access|Secure form|Kenapa memakai|Onboarding lebih/i)).toHaveCount(0);
  });

  test('protected page redirects unauthenticated users to login', async ({ page }) => {
    await page.goto(appUrl('kasir.php'));
    await expect(page).toHaveTitle(/Login KASPINDO/i);
    await expect(page.locator('input[name="username"]')).toBeVisible();
  });
});

test.describe('authenticated smoke', () => {
  test('superadmin can open referral and active store panels', async ({ page }) => {
    test.skip(!hasCredential('superadmin'), 'Set E2E_SUPERADMIN_USER and E2E_SUPERADMIN_PASS.');

    await login(page, 'superadmin');
    await page.goto(appUrl('superadmin_store_requests.php?focus=referrals'));
    await expect(page.getByText(/Generate Referral|Cari Referral|Referral/i).first()).toBeVisible();

    await page.goto(appUrl('superadmin_store_requests.php?focus=stores'));
    await expect(page.getByText(/Toko Aktif|Custom Domain|Tenant/i).first()).toBeVisible();
  });

  test('admin can open POS, refunds, Telegram, and backup pages', async ({ page }) => {
    test.skip(!hasCredential('admin'), 'Set E2E_ADMIN_USER and E2E_ADMIN_PASS.');

    await login(page, 'admin');

    await page.goto(appUrl('kasir.php'));
    await expect(page.getByText(/POS Kasir|Shift belum dibuka|Shift Aktif/i).first()).toBeVisible();

    await page.goto(appUrl('admin_refunds.php'));
    await expect(page.getByText(/Refund|Retur/i).first()).toBeVisible();

    await page.goto(appUrl('admin_notifications.php'));
    await expect(page.getByText(/Telegram/i).first()).toBeVisible();
  });
});

test.describe('operational flows', () => {
  test('admin can open shift, checkout one item, and close shift', async ({ page }) => {
    test.skip(!runOperational, 'Set E2E_RUN_OPERATIONAL=1 to run data-changing POS tests.');
    test.skip(!hasCredential('admin'), 'Set E2E_ADMIN_USER and E2E_ADMIN_PASS.');

    await login(page, 'admin');
    await ensureShiftOpen(page);

    await page.goto(appUrl('kasir.php'));
    await expect(page.getByText(/POS Kasir/i)).toBeVisible();

    const firstQtyPlus = page.locator('[data-testid="qty-plus"]:not([disabled])').first();
    await firstQtyPlus.click();
    await page.getByRole('button', { name: /Ringkasan|Bayar/i }).first().click();
    await page.getByTestId('cash-received').fill('999999');
    await page.getByTestId('pay-button').click();
    await page.getByTestId('confirm-pay-button').click();
    await page.waitForLoadState('networkidle');
    await expect(page.getByText(/Transaksi|Print Struk|berhasil/i).first()).toBeVisible();

    await page.goto(appUrl('shift.php'));
    const closeButton = page.getByTestId('shift-close-submit').first();
    await expect(closeButton).toBeVisible();
    await closeButton.click();
    await page.waitForLoadState('networkidle');
    await expect(page.getByText(/Shift belum aktif|berhasil|Riwayat Shift/i).first()).toBeVisible();
  });
});

test.describe('integration flows', () => {
  test('superadmin integration pages are reachable before running external actions', async ({ page }) => {
    test.skip(!runIntegrations, 'Set E2E_RUN_INTEGRATIONS=1 to run integration smoke tests.');
    test.skip(!hasCredential('superadmin'), 'Set E2E_SUPERADMIN_USER and E2E_SUPERADMIN_PASS.');

    await login(page, 'superadmin');

    await page.goto(appUrl('admin_backup.php'));
    await expect(page.getByText(/Backup|Google Drive|Cron/i).first()).toBeVisible();

    await page.goto(appUrl('admin_notifications.php'));
    await expect(page.getByText(/Telegram/i).first()).toBeVisible();

    await page.goto(appUrl('support_chat.php'));
    await expect(page.getByText(/Support|dinonaktifkan|Live/i).first()).toBeVisible();

    await page.goto(appUrl('superadmin_store_requests.php?focus=stores'));
    await expect(page.getByText(/Custom Domain|Toko Aktif|Tenant/i).first()).toBeVisible();
  });

  test('superadmin can submit a custom domain test value when explicitly configured', async ({ page }) => {
    const customDomain = process.env.E2E_CUSTOM_DOMAIN_TEST || '';
    test.skip(!runIntegrations || customDomain === '', 'Set E2E_RUN_INTEGRATIONS=1 and E2E_CUSTOM_DOMAIN_TEST.');
    test.skip(!hasCredential('superadmin'), 'Set E2E_SUPERADMIN_USER and E2E_SUPERADMIN_PASS.');

    await login(page, 'superadmin');
    await page.goto(appUrl('superadmin_store_requests.php?focus=stores'));

    const domainInput = page.locator('input[name="custom_domain"]').first();
    await expect(domainInput).toBeVisible();
    await domainInput.fill(customDomain);
    await domainInput.locator('xpath=ancestor::form').locator('button[type="submit"]').click();
    await page.waitForLoadState('networkidle');
    await expect(page.getByText(/domain|berhasil|sudah digunakan/i).first()).toBeVisible();
  });
});
