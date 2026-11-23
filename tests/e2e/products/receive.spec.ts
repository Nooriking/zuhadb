import { test, expect } from '@playwright/test';
import { login, waitForInertia, dismissSwalIfPresent } from '../helpers';

test.describe('Product Receive', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('receives stock for existing variant', async ({ page }) => {
        await page.goto('/products/receive');
        await waitForInertia(page);

        const variantTrigger = page.locator('[role="combobox"]').first();
        await variantTrigger.click();
        await page.getByRole('option').first().click();

        const qtyInput = page.locator('input[type="number"]').first();
        await qtyInput.fill('5');

        const amountInput = page
            .locator('label')
            .filter({ hasText: /purchase|unit|cost|amount/i })
            .first()
            .locator('xpath=ancestor::div[contains(@class,"space-y")][1]')
            .locator('input[type="number"]')
            .first();

        if (await amountInput.count()) {
            await amountInput.fill('7500');
        } else {
            const numbers = page.locator('input[type="number"]');
            if ((await numbers.count()) > 1) {
                await numbers.nth(1).fill('7500');
            }
        }

        const dateInput = page.locator('input[type="date"]');
        if (await dateInput.count()) {
            await dateInput.first().fill(new Date().toISOString().slice(0, 10));
        }

        await page.click('button[type="submit"]:has-text("Add stock"), button[type="submit"]:has-text("Save")');
        await waitForInertia(page);
        await dismissSwalIfPresent(page);

        await expect(page).toHaveURL(/products/);
    });
});
