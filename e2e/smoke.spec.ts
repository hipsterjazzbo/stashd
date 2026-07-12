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

	// Progress arrives over the shared Mercure stream. The stash view used to
	// refetch /jobs (and four unrelated endpoints) for every progress message;
	// a new download must now update the in-memory job without that request
	// storm. The fake downloader completes quickly, so this also exercises the
	// terminal event path.
	let jobListRequests = 0;
	page.on('request', (request) => {
		if (new URL(request.url()).pathname === '/api/v1/jobs') jobListRequests++;
	});
	// Let the add-input command's terminal resync and the first Mercure
	// connection settle before measuring this separate download.
	await page.waitForTimeout(3000);
	jobListRequests = 0;

	const download = await page.request.post('/api/v1/commands', {
		data: {
			type: 'item.download',
			options: { media_item_id: body.items[0].media_item_id, stash_id: page.url().split('/').pop() },
		},
	});
	expect(download.ok()).toBeTruthy();
	await page.waitForTimeout(1500);
	expect(jobListRequests).toBe(0);

	await page.goto('/vault/' + body.items[0].media_item_id);
	await expect(page.getByRole('heading', { name: 'Vault item' })).toBeVisible();
});
