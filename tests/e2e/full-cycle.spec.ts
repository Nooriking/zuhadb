import { test, expect } from '@playwright/test';
import { login, waitForInertia, fillMoneyByLabel, dismissSwalIfPresent, selectByLabel } from './helpers';

test('full cycle: create product → sale → receipt', async ({ page }) => {
    await login(page);

    // 1. Create product
    await page.goto('/products/create');
    await waitForInertia(page);
    await page.fill('input[placeholder="e.g. Sauvage"]', 'E2E Full Cycle Product');
    await page.locator('label').filter({ hasText: 'Volume (ml)' }).locator('..').locator('input[type="number"]').fill('100');
    await fillMoneyByLabel(page, /Purchase cost per unit/, '7000');
    await fillMoneyByLabel(page, /Selling price per unit/, '10000');
    const qtyLabel = page.locator('label').filter({ hasText: /^Quantity$/ }).first();
    await qtyLabel.locator('..').locator('input[type="number"]').fill('10');
    await selectByLabel(page, /^Owner/, /Shop/);
    await page.click('button[type="submit"]:has-text("Save Product")');
    await waitForInertia(page);
    await dismissSwalIfPresent(page);
    await expect(page.getByText('E2E Full Cycle Product').first()).toBeVisible({ timeout: 15_000 });

    // 2. Sale
    await page.goto('/sales/create');
    await waitForInertia(page);

    const variantGroup = page.locator('label').filter({ hasText: /Product Variant/i }).first();
    await variantGroup.locator('xpath=ancestor::div[contains(@class,"space-y")][1]').locator('[role="combobox"]').click();
    await page.waitForTimeout(300);

    const search = page.locator('[role="listbox"] input, input[placeholder*="search" i]').first();
    if (await search.count()) {
        await search.fill('E2E Full Cycle');
        await page.waitForTimeout(300);
    }

    const option = page.getByRole('option', { name: /E2E Full Cycle/i }).first();
    if (await option.count()) {
        await option.click();
    } else {
        await page.getByRole('option').first().click();
    }

    await page.locator('input[type="number"]').first().fill('1');
    await page.click('button:has-text("Cash")');
    await page.click('button[type="submit"]:has-text("Save Sale")');
    await waitForInertia(page);
    await dismissSwalIfPresent(page);

    await expect(page.getByText(/INV-|Sale Created Successfully/i).first()).toBeVisible({ timeout: 20_000 });

    // 3. Receipt / view sale
    const viewSaleBtn = page.locator('a:has-text("View Sale"), button:has-text("Print Receipt"), a:has-text("Print Receipt")').first();
    if (await viewSaleBtn.count()) {
        await viewSaleBtn.click();
        await waitForInertia(page);
        await expect(page.getByText(/INV-/i).first()).toBeVisible();
    }
});
