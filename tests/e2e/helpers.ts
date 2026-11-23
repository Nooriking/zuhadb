import { Page, expect, Locator } from '@playwright/test';

/** Seeded Super Admin (DatabaseSeeder / SuperAdminSeeder). */
export const E2E_USER = {
    email: process.env.E2E_EMAIL || 'zuhaperfume.af@gmail.com',
    password: process.env.E2E_PASSWORD || 'Super@123',
};

export const E2E_BARCODE = process.env.E2E_BARCODE || '8900000000001';

export async function login(page: Page, email = E2E_USER.email, password = E2E_USER.password) {
    await page.goto('/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/dashboard/, { timeout: 20_000 });
}

export async function waitForInertia(page: Page) {
    await page.waitForLoadState('domcontentloaded');
    await page.waitForLoadState('networkidle').catch(() => undefined);
}

/** Open a Radix Select near a visible label text, then pick an option by accessible name. */
export async function selectByLabel(page: Page, labelText: string | RegExp, optionText: string | RegExp) {
    const label = page.locator('label').filter({ hasText: labelText }).first();
    await expect(label).toBeVisible();
    const group = label.locator('xpath=ancestor::div[contains(@class,"space-y")][1]');
    const trigger = group.locator('[role="combobox"]').first();
    await trigger.click();
    await page.getByRole('option', { name: optionText }).first().click();
}

export async function selectOption(page: Page, trigger: Locator | string, optionText: string | RegExp) {
    const el = typeof trigger === 'string' ? page.locator(trigger).first() : trigger;
    await el.click();
    await page.getByRole('option', { name: optionText }).first().click();
}

/** Fill MoneyField amount next to a label (Purchase cost / Selling price / etc.). */
export async function fillMoneyByLabel(page: Page, labelText: string | RegExp, amount: string) {
    const label = page.locator('label').filter({ hasText: labelText }).first();
    const group = label.locator('xpath=ancestor::div[contains(@class,"space-y")][1]');
    await group.locator('input[type="number"]').first().fill(amount);
}

export async function dismissSwalIfPresent(page: Page) {
    const confirm = page.locator('.swal2-confirm');
    if (await confirm.isVisible().catch(() => false)) {
        await confirm.click();
    }
}
