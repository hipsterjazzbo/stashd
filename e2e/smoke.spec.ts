import { expect, test } from '@playwright/test';

test.describe.configure({ mode: 'serial' });

test('signed-out visit to / redirects to the login page', async ({ page }) => {
    const response = await page.goto('/');

    expect(response?.ok()).toBeTruthy();
    await page.waitForURL('**/login');
    await expect(page).toHaveTitle(/stashd_/);
});

test('login page renders the owner auth form', async ({ page }) => {
    await page.goto('/login');

    await expect(page.locator('#auth-form')).toBeVisible();
    await expect(page.locator('input[name="username"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
	await expect(page.locator('#auth-submit')).toBeVisible();
});

test('owner can create a Fake-provider stash and open its Vault item', async ({ page }) => {
	await page.goto('/login');
	await page.locator('input[name="username"]').fill('e2e-owner');
	await page.locator('input[name="password"]').fill('e2e-password');
	await page.locator('#auth-submit').click();
	await page.waitForURL('**/');

	await page.goto('/stashes');
	await page.getByRole('button', { name: '+ New stash' }).click();
	await page.getByLabel('Title (optional)').fill('Browser Fake Stash');
	await page.getByRole('button', { name: 'Create' }).click();
	await page.waitForURL(/\/stashes\/[^/]+$/);

	await page.getByRole('button', { name: '+ Add input' }).first().click();
	await page.getByLabel('Channel, playlist, or video URL').fill('fake://channel/e2e');
	await page.getByRole('button', { name: 'Continue' }).click();
	await page.getByRole('button', { name: 'Add input', exact: true }).click();
	await expect(page.getByRole('heading', { name: 'Add input' })).toBeHidden({ timeout: 30000 });
	await expect(page.getByText('fake://channel/e2e')).toBeVisible();

	const items = await page.request.get(new URL('/api/v1/stashes/' + page.url().split('/').pop() + '/items', page.url()).toString());
	expect(items.ok()).toBeTruthy();
	const body = await items.json() as { items: { media_item_id: string }[] };
	expect(body.items).not.toHaveLength(0);

	await page.goto('/vault/' + body.items[0].media_item_id);
	await expect(page.getByRole('heading', { name: 'Vault item' })).toBeVisible();
});

test('selected broadcast type is sent through preview and creation', async ({ page }) => {
	await page.goto('/login');
	await page.locator('input[name="username"]').fill('e2e-owner');
	await page.locator('input[name="password"]').fill('e2e-password');
	await page.locator('#auth-submit').click();
	await page.waitForURL('**/');

	await page.goto('/stashes');
	await page.getByRole('button', { name: '+ New stash' }).click();
	await page.getByLabel('Title (optional)').fill('Broadcast Type Selection');
	await page.getByRole('button', { name: 'Create' }).click();
	await page.waitForURL(/\/stashes\/[^/]+$/);

	await page.getByText('+ Add broadcast').click();
	const typeSelect = page.locator('select[x-model="newBroadcastType"]');
	await expect(typeSelect).toHaveValue('podcast');

	const previewRequest = page.waitForRequest((request) =>
		request.method() === 'POST' && request.url().endsWith('/broadcasts/preview'),
	);
	await page.getByRole('button', { name: 'Preview', exact: true }).click();
	expect((await previewRequest).postDataJSON()).toMatchObject({ type: 'podcast' });
	await expect(page.getByText('What this will do')).toBeVisible();

	const createResponse = page.waitForResponse((response) =>
		response.request().method() === 'POST'
		&& /\/api\/v1\/stashes\/[^/]+\/broadcasts$/.test(new URL(response.url()).pathname),
	);
	await page.getByRole('button', { name: 'Create broadcast' }).click();
	const response = await createResponse;
	expect(response.ok()).toBeTruthy();
	const body = await response.json() as { broadcast: { type: string } };
	expect(body.broadcast.type).toBe('podcast');
});
