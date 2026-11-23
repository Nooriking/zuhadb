import { test, expect } from '@playwright/test';
import {
    login,
    waitForInertia,
    selectByLabel,
    fillMoneyByLabel,
    dismissSwalIfPresent,
    E2E_BARCODE,
} from '../helpers';

test.describe('Product Create', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('creates a product with variant and purchase (store owner)', async ({ page }) => {
        await page.goto('/products/create');
        await waitForInertia(page);

        await page.fill('input[placeholder="e.g. Sauvage"]', 'Test Perfume E2E');
        await selectByLabel(page, /^Brand$/, /Dior/);
        await selectByLabel(page, /Gender/, /Unisex/);

        await page.locator('label').filter({ hasText: 'Volume (ml)' }).locator('..').locator('input[type="number"]').fill('100');
        await selectByLabel(page, /Concentration/, /Eau de Parfum \(EDP\)/);

        await fillMoneyByLabel(page, /Purchase cost per unit/, '7000');
        await fillMoneyByLabel(page, /Selling price per unit/, '10000');

        // Purchase quantity (default purchase row)
        const qtyLabel = page.locator('label').filter({ hasText: /^Quantity$/ }).first();
        await qtyLabel.locator('..').locator('input[type="number"]').fill('10');

        // Owner defaults to Shop — ensure Shop selected
        await selectByLabel(page, /^Owner/, /Shop/);

        await page.click('button[type="submit"]:has-text("Save Product")');
        await waitForInertia(page);
        await dismissSwalIfPresent(page);

        await expect(page).toHaveURL(/products/);
        await expect(page.getByText('Test Perfume E2E').first()).toBeVisible({ timeout: 15_000 });
    });

    test('creates a product with investor funding', async ({ page }) => {
        await page.goto('/products/create');
        await waitForInertia(page);

        await page.fill('input[placeholder="e.g. Sauvage"]', 'Investor Perfume E2E');
        await selectByLabel(page, /Gender/, /Unisex/);

        await page.locator('label').filter({ hasText: 'Volume (ml)' }).locator('..').locator('input[type="number"]').fill('50');
        await fillMoneyByLabel(page, /Purchase cost per unit/, '5000');
        await fillMoneyByLabel(page, /Selling price per unit/, '8000');

        const qtyLabel = page.locator('label').filter({ hasText: /^Quantity$/ }).first();
        await qtyLabel.locator('..').locator('input[type="number"]').fill('20');

        // Prefer "Ahmad" if seeded; otherwise first non-Shop investor option
        const ownerLabel = page.locator('label').filter({ hasText: /^Owner/ }).first();
        const ownerGroup = ownerLabel.locator('xpath=ancestor::div[contains(@class,"space-y")][1]');
        await ownerGroup.locator('[role="combobox"]').click();
        const ahmad = page.getByRole('option', { name: /Ahmad/i });
        if (await ahmad.count()) {
            await ahmad.first().click();
        } else {
            const investorOpt = page.getByRole('option').filter({ hasNotText: /Shop/i }).first();
            await expect(investorOpt).toBeVisible();
            await investorOpt.click();
        }

        await fillMoneyByLabel(page, /Investor profit \/ unit/, '500');

        await page.click('button[type="submit"]:has-text("Save Product")');
        await waitForInertia(page);
        await dismissSwalIfPresent(page);

        await expect(page).toHaveURL(/products/);
        await expect(page.getByText('Investor Perfume E2E').first()).toBeVisible({ timeout: 15_000 });
    });

    test('shows validation error when name is empty', async ({ page }) => {
        await page.goto('/products/create');
        await waitForInertia(page);

        await page.click('button[type="submit"]:has-text("Save Product")');
        await page.waitForTimeout(500);

        await expect(page.getByText(/Product name is required/i).first()).toBeVisible();
        await dismissSwalIfPresent(page);
    });

    test('blocks duplicate barcode', async ({ page }) => {
        await page.goto('/products/create');
        await waitForInertia(page);

        await page.fill('input[placeholder="e.g. Sauvage"]', 'Barcode Test');

        const barcodeInput = page.locator('input[placeholder*="Scan or type barcode"]').first();
        await barcodeInput.fill(E2E_BARCODE);
        await barcodeInput.blur();
        await page.waitForTimeout(1200);

        await expect(page.getByText(/already (exists|used|registered)/i).first()).toBeVisible({ timeout: 10_000 });
    });
});
