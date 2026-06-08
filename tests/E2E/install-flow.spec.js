const fs = require('node:fs/promises');
const path = require('node:path');
const { test, expect } = require('@playwright/test');

const rootPath = path.resolve(__dirname, '..', '..');
const envPath = path.join(rootPath, '.env');
const sqlitePath = path.join(rootPath, 'storage', 'database', 'quenza.db');

async function resetInstallState() {
    await fs.rm(envPath, { force: true });
    await fs.rm(sqlitePath, { force: true });
}

async function goToSiteStep(page) {
    await page.goto('/');
    await expect(page).toHaveURL(/\/install(?:\?step=1)?$/);

    await page.locator('button[name="locale"][value="id"]').click();
    await expect(page).toHaveURL(/\/install\?step=2$/);

    await page.getByRole('button', { name: /Submit and Validate Connection|Submit dan Validasi Koneksi/i }).click();
    await expect(page).toHaveURL(/\/install\?step=3$/);
}

test.describe('Installer black-box e2e', () => {
    test.beforeEach(async () => {
        await resetInstallState();
    });

    test('redirects first run to install wizard', async ({ page }) => {
        await page.goto('/');

        await expect(page).toHaveURL(/\/install(?:\?step=1)?$/);
        await expect(page.locator('button[name="locale"][value="id"]')).toBeVisible();
        await expect(page.locator('button[name="locale"][value="en"]')).toBeVisible();
    });

    test('completes sqlite install and opens seeded article', async ({ page }) => {
        await goToSiteStep(page);

        await page.locator('input[name="site_title"]').fill('Blog Quenza Otomatis');
        await page.locator('input[name="admin_username"]').fill('admin_e2e');
        await page.locator('input[name="admin_email"]').fill('admin.e2e@example.com');
        await page.locator('input[name="admin_password"]').fill('StrongPass!123');
        await page.getByRole('button', { name: /Install Quenza CMS/i }).click();

        await expect(page).toHaveURL(/\/install\/success$/);
        await page.getByRole('link', { name: /Lihat Situs|Visit Site/i }).click();

        await expect(page).toHaveURL(/\/$/);
        await expect(page.getByRole('heading', { name: 'Selamat Datang di Blog Quenza Anda' })).toBeVisible();

        await page.locator('a[href="/articles/selamat-datang-di-blog-quenza-anda"]').first().click();
        await expect(page).toHaveURL(/\/articles\/selamat-datang-di-blog-quenza-anda$/);
        await expect(page.getByRole('heading', { name: 'Selamat Datang di Blog Quenza Anda' })).toBeVisible();
        await expect(page.locator('article')).toContainText('Ini adalah artikel pertama Quenza CMS Anda.');
    });

    test('shows validation errors for invalid site and admin fields', async ({ page }) => {
        await goToSiteStep(page);

        await page.locator('input[name="site_title"]').fill('Blog Quenza');
        await page.locator('input[name="admin_username"]').fill('ab');
        await page.locator('input[name="admin_email"]').fill('invalid-email');
        await page.locator('input[name="admin_password"]').fill('Weakpass1');
        await page.getByRole('button', { name: /Install Quenza CMS/i }).click();

        await expect(page).toHaveURL(/\/install\?step=3$/);
        await expect(page.getByText(/Username admin wajib 3-40 karakter/i)).toBeVisible();
        await expect(page.getByText(/Email admin tidak valid/i)).toBeVisible();
        await expect(page.getByText(/Password admin minimal 10 karakter/i)).toBeVisible();
    });
});
