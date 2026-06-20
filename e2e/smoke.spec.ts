import { expect, test } from '@playwright/test';

test('signed-out visit to / redirects to the login page', async ({ page }) => {
    const response = await page.goto('/');

    expect(response?.ok()).toBeTruthy();
    await page.waitForURL('**/login');
    await expect(page).toHaveTitle(/stashd_/);
});

test('login page renders the owner auth form', async ({ page }) => {
    await page.goto('/login');

    await expect(page.locator('#auth-form')).toBeVisible();
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
    await expect(page.locator('#auth-submit')).toBeVisible();
});
