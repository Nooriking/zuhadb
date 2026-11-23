import { test, expect } from '@playwright/test';
import { login, waitForInertia, dismissSwalIfPresent, E2E_BARCODE } from '../helpers';

test.describe('Sale Create', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('creates cash sale with one item', async ({ page }) => {
        await page.goto('/sales/create');
        await waitForInertia(page);

        // Walk-in customer
        const customerTrigger = page.getByRole('button', { name: /Select or search customer|Create new customer|walk-in/i }).first()
            .or(page.locator('button').filter({ hasText: /Select or search customer/i }).first());
        if (await customerTrigger.isVisible().catch(() => false)) {
            await customerTrigger.click();
            const walkIn = page.getByText(/No customer \(walk-in\)/i).first();
            if (await walkIn.count()) {
                await walkIn.click();
            } else {
                await page.keyboard.press('Escape');
            }
        }

        // Product variant
        const variantGroup = page.locator('label').filter({ hasText: /Product Variant/i }).first();
        await variantGroup.locator('xpath=ancestor::div[contains(@class,"space-y")][1]').locator('[role="combobox"]').click();
        await page.getByRole('option').first().click();

        const qtyInput = page.locator('label').filter({ hasText: /^Qty$|^Quantity$/i }).first()
            .locator('xpath=ancestor::div[contains(@class,"space-y")][1]')
            .locator('input[type="number"]')
            .first();
        if (await qtyInput.count()) {
            await qtyInput.fill('2');
        } else {
            await page.locator('input[type="number"]').first().fill('2');
        }

        await page.click('button:has-text("Cash")');
        await page.click('button[type="submit"]:has-text("Save Sale")');
        await waitForInertia(page);
        await dismissSwalIfPresent(page);

        await expect(page.getByText(/Sale Created Successfully|INV-/i).first()).toBeVisible({ timeout: 20_000 });
    });

    test('creates credit sale with new customer', async ({ page }) => {
        await page.goto('/sales/create');
        await waitForInertia(page);

        const customerTrigger = page.locator('button').filter({ hasText: /Select or search customer|Create new/i }).first();
        await customerTrigger.click();
        await page.getByText(/Create new customer/i).first().click();

        await page.fill('input[placeholder="Customer name"]', 'E2E Test Customer');
        await page.fill('input[placeholder*="07XX"]', '0701234567');

        const variantGroup = page.locator('label').filter({ hasText: /Product Variant/i }).first();
        await variantGroup.locator('xpath=ancestor::div[contains(@class,"space-y")][1]').locator('[role="combobox"]').click();
        await page.getByRole('option').first().click();

        await page.click('button:has-text("Credit")');
        await page.click('button[type="submit"]:has-text("Save Sale")');
        await waitForInertia(page);
        await dismissSwalIfPresent(page);

        await expect(page.getByText(/INV-|Sale Created/i).first()).toBeVisible({ timeout: 20_000 });
    });

    test('blocks sale when stock is insufficient', async ({ page }) => {
        await page.goto('/sales/create');
        await waitForInertia(page);

        const variantGroup = page.locator('label').filter({ hasText: /Product Variant/i }).first();
        await variantGroup.locator('xpath=ancestor::div[contains(@class,"space-y")][1]').locator('[role="combobox"]').click();
        const options = page.getByRole('option');
        expect(await options.count()).toBeGreaterThan(0);
        await options.first().click();

        const qty = page.locator('input[type="number"]').first();
        await qty.fill('99999');

        await page.click('button[type="submit"]:has-text("Save Sale")');
        await page.waitForTimeout(1500);
        await expect(page.getByText(/Not enough stock|stock|not enough/i).first()).toBeVisible({ timeout: 10_000 });
        await dismissSwalIfPresent(page);
    });

    test('barcode scanner adds item', async ({ page }) => {
        await page.goto('/sales/create');
        await waitForInertia(page);

        const barcodeInput = page.locator('input[placeholder*="barcode" i]').first();
        await barcodeInput.fill(E2E_BARCODE);
        await barcodeInput.press('Enter');
        await page.waitForTimeout(1200);

        // Item row / selected variant visible
        const hasItem =
            (await page.locator('text=/Dior|Chanel|Creed|E2E|Sauvage|Bleu|Aventus/i').count()) > 0 ||
            (await page.locator('[role="combobox"]').filter({ hasText: /.+/ }).count()) > 0;

        expect(hasItem).toBeTruthy();
    });
});
