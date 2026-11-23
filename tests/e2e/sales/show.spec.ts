import { test, expect } from '@playwright/test';
import { login, waitForInertia } from '../helpers';

test.describe('Sale Show / Receipt', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('shows sale details', async ({ page }) => {
        await page.goto('/sales');
        await waitForInertia(page);

        const firstRow = page.locator('table tbody tr, a[href*="/sales/"]').first();
        if (await firstRow.count()) {
            await firstRow.click();
            await waitForInertia(page);
            await expect(page.getByText(/INV-/i).first()).toBeVisible({ timeout: 15_000 });
        }
    });

    test('opens receipt page', async ({ page }) => {
        await page.goto('/sales/1/receipt');
        await waitForInertia(page);
        await expect(page.locator('body')).toBeVisible();
        // Receipt should show invoice or sale totals when sale exists
        await expect(page.locator('text=/INV-|Receipt|Total|AFN/i').first()).toBeVisible({ timeout: 15_000 });
    });
});
