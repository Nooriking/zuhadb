import { test, expect } from '@playwright/test';
import { login, waitForInertia, fillMoneyByLabel } from '../helpers';

test.describe('Product Show / Edit', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('shows product details with batches and prices', async ({ page }) => {
        await page.goto('/products');
        await waitForInertia(page);

        const firstLink = page.locator('a[href*="/products/"]').filter({ hasNotText: /create|receive|trash|edit/i }).first();
        if (await firstLink.count()) {
            await firstLink.click();
        } else {
            await page.goto('/products/1');
        }
        await waitForInertia(page);

        await expect(page.locator('h1').first()).toBeVisible();
        await expect(page.locator('text=/ml|edp|AFN|batch|price/i').first()).toBeVisible();
    });

    test('edits product name', async ({ page }) => {
        await page.goto('/products/1/edit');
        await waitForInertia(page);

        const nameInput = page.locator('input[placeholder="e.g. Sauvage"], input').filter({ hasNot: page.locator('[type="hidden"]') }).first();
        // Prefer labeled Product Name field
        const labeled = page.locator('label').filter({ hasText: /Product Name/i });
        if (await labeled.count()) {
            await labeled.first().locator('..').locator('input').fill('Updated Product Name E2E');
        } else {
            await nameInput.fill('Updated Product Name E2E');
        }

        await page.click('button[type="submit"]:has-text("Update Product")');
        await waitForInertia(page);

        await expect(page.getByText('Updated Product Name E2E').first()).toBeVisible({ timeout: 15_000 });
    });

    test('edits prices and persists', async ({ page }) => {
        await page.goto('/products/1/edit');
        await waitForInertia(page);

        const saleLabel = page.locator('label').filter({ hasText: /Selling price/i });
        if (await saleLabel.count()) {
            await fillMoneyByLabel(page, /Selling price/, '12000');
        } else {
            const priceInputs = page.locator('input[type="number"]');
            expect(await priceInputs.count()).toBeGreaterThan(0);
            await priceInputs.first().fill('12000');
        }

        await page.click('button[type="submit"]:has-text("Update Product")');
        await waitForInertia(page);

        await page.goto('/products/1');
        await waitForInertia(page);
        await expect(page.locator('text=/12,?000|12000/').first()).toBeVisible({ timeout: 15_000 });
    });
});
