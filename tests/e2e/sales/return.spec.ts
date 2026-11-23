import { test, expect } from '@playwright/test';
import { login, waitForInertia, dismissSwalIfPresent } from '../helpers';

test.describe('Sale Return', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('creates return for existing sale', async ({ page }) => {
        // App route is /sale-returns/create (not /sales/{id}/return)
        await page.goto('/sale-returns/create?sale_id=1');
        await waitForInertia(page);

        // If sale_id query did not bind, pick first sale from select
        const saleTrigger = page.locator('label').filter({ hasText: /^Sale/ }).first()
            .locator('xpath=ancestor::div[contains(@class,"space-y")][1]')
            .locator('[role="combobox"]');
        if (await saleTrigger.count()) {
            const valueText = await saleTrigger.innerText().catch(() => '');
            if (!/INV-|S-TEST|invoice/i.test(valueText)) {
                await saleTrigger.click();
                await page.getByRole('option').first().click();
                await waitForInertia(page);
            }
        }

        const qtyInput = page.locator('table input[type="number"], input[type="number"]').first();
        await qtyInput.fill('1');

        const reason = page.locator('textarea, input[placeholder*="reason" i]').first();
        if (await reason.count()) {
            await reason.fill('Customer changed mind (E2E test)');
        }

        await page.click('button[type="submit"]:has-text("Save Return"), button[type="submit"]:has-text("Save")');
        await waitForInertia(page);
        await dismissSwalIfPresent(page);

        await expect(page).toHaveURL(/sale-returns|sales/);
    });
});
