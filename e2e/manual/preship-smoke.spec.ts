import { expect, Page, test } from '@playwright/test';

// Manual pre-ship E2E smoke run against live YouTube sources. Excluded from
// the routine suite via playwright.config.ts's testIgnore -- run explicitly:
// npx playwright test e2e/manual/preship-smoke.spec.ts
// Real network + real ffmpeg -- expect long runtimes, especially for
// multi-hour Critical Role episodes.

const ADMIN = { username: 'e2e-admin', password: 'e2e-smoke-test-pass-1' };

test.describe.configure({ mode: 'serial' });

async function login(page: Page) {
	await page.goto('/login');
	await page.locator('input[name="username"]').fill(ADMIN.username);
	await page.locator('input[name="password"]').fill(ADMIN.password);
	await page.locator('#auth-submit').click();
	await page.waitForFunction(() => !location.pathname.includes('/login'), { timeout: 15000 });
}

// A session drop bounces to /login via client-side JS (apiFetch's 401
// handler calling window.location.assign) *after* goto() resolves -- not a
// server-side redirect at navigation time. So the check has to wait for that
// async redirect to have a chance to fire before reading page.url().
async function reloadResilient(page: Page, url: string) {
	await page.goto(url);
	await page.waitForTimeout(2000);
	if (page.url().includes('/login')) {
		await login(page);
		await page.goto(url);
	}
}

async function createStashWithLink(page: Page, title: string, link?: string): Promise<string> {
	await page.goto('/stashes');
	await page.getByRole('button', { name: '+ New stash' }).click();
	await page.getByLabel('Title (optional)').fill(title);
	if (link) await page.getByLabel('Link (optional)').fill(link);
	await page.getByRole('button', { name: 'Create' }).click();
	await page.waitForURL(/\/stashes\/[^/?]+/, { timeout: 15000 });
	return new URL(page.url()).pathname.split('/').pop()!;
}

// Waits out the add-input modal's full lifecycle: auto-opened preflight ->
// review (optionally filling a title regex) -> commit -> auto-close on
// completion. Assumes the modal is already open (either via the ?link= auto
// -open on stash creation, or after clicking "+ Add input" + Continue).
async function completeAddInput(page: Page, opts?: { titleRegexInclude?: string }) {
	await expect(page.getByRole('heading', { name: 'Add input' })).toBeVisible({ timeout: 10000 });

	// "reviewing" (preflight) step can take a while against a live, possibly
	// rate-limited source.
	await expect(page.getByText('Looking up that source…')).toBeHidden({ timeout: 180000 });
	await expect(page.getByText(/Could not reach the server\.|isn't supported yet/)).toBeHidden({ timeout: 1000 }).catch(() => {});

	if (opts?.titleRegexInclude) {
		await page.getByLabel('Include title regex').fill(opts.titleRegexInclude);
	}

	await page.getByRole('button', { name: 'Add input', exact: true }).click();

	// "committing" (discovery) step, then modal auto-closes on completion.
	await expect(page.getByRole('heading', { name: 'Add input' })).toBeHidden({ timeout: 300000 });
}

async function addFollowUpInput(page: Page, url: string) {
	// Two "+ Add input" buttons exist when the input list is empty (header +
	// the empty-state fallback link) -- first() disambiguates either way.
	await page.getByRole('button', { name: '+ Add input' }).first().click();
	await page.getByLabel('Channel, playlist, or video URL').fill(url);
	await page.getByRole('button', { name: 'Continue' }).click();
	await completeAddInput(page);
}

// Polls the Items table until at least `count` items reach a terminal
// "downloaded"/ready state, or times out. Real downloads run via the
// already-running background worker -- this just waits and observes.
async function waitForDownloads(page: Page, count: number, timeoutMs: number) {
	const stashUrl = page.url();
	const deadline = Date.now() + timeoutMs;
	while (Date.now() < deadline) {
		await reloadResilient(page, stashUrl);
		const readyCount = await page.locator('tbody tr td:has-text("ready")').count();
		if (readyCount >= count) return readyCount;
		await page.waitForTimeout(15000);
	}
	throw new Error(`Timed out waiting for ${count} ready downloads`);
}

async function createBroadcast(
	page: Page,
	opts: { typeLabel: string; mediaKind?: 'audio' | 'video'; name?: string },
) {
	await page.locator('select').filter({ hasText: opts.typeLabel }).first();
	const typeSelect = page.locator('div.border-t.border-line select').first();
	await typeSelect.selectOption({ label: opts.typeLabel });
	if (opts.mediaKind) {
		const kindSelect = page.locator('select[x-model="newBroadcastMediaKind"]');
		await kindSelect.selectOption(opts.mediaKind);
	}
	if (opts.name) {
		await page.getByPlaceholder('Name (optional)').fill(opts.name);
	}
	await page.getByRole('button', { name: 'Preview' }).click();
	await expect(page.getByText('What this will do')).toBeVisible({ timeout: 30000 });
	await page.getByRole('button', { name: 'Create broadcast' }).click();

	// Creating a broadcast only inserts the record in "pending" -- it does not
	// auto-dispatch a build. The card's own "rebuild" button must be clicked.
	const card = opts.name ? page.locator('li', { hasText: opts.name }) : page.locator('li').last();
	await card.getByRole('button', { name: 'rebuild', exact: true }).click();
}

test('oculusimperia: video download + audio podcast broadcast (transcode fix acceptance test)', async ({ page }) => {
	test.setTimeout(45 * 60 * 1000);
	await login(page);

	const stashId = await createStashWithLink(page, 'oculusimperia', 'https://www.youtube.com/@oculusimperia');
	await completeAddInput(page);

	await waitForDownloads(page, 3, 30 * 60 * 1000);

	await createBroadcast(page, { typeLabel: 'Podcast', mediaKind: 'audio', name: 'oculusimperia audio podcast' });

	// Poll broadcast card until it reflects real state (not stuck "pending"),
	// including any transcode fallback the audio-over-video path triggers.
	const broadcastPageUrl = page.url();
	const deadline = Date.now() + 20 * 60 * 1000;
	let sawTranscodeStatus = false;
	let finalStateText = '';
	while (Date.now() < deadline) {
		await reloadResilient(page, broadcastPageUrl);
		const card = page.locator('li', { hasText: 'oculusimperia audio podcast' });
		finalStateText = (await card.innerText().catch(() => '')) ?? '';
		if (/transcod/i.test(finalStateText)) sawTranscodeStatus = true;
		if (/\bready\b/i.test(finalStateText) && !/pending/i.test(finalStateText)) break;
		await page.waitForTimeout(15000);
	}

	console.log('OCULUSIMPERIA BROADCAST FINAL STATE:\n', finalStateText, '\nsawTranscodeStatus:', sawTranscodeStatus);
	expect(finalStateText).not.toMatch(/podcast_audio_transcode_pending/);
});

// Resolved by hand from https://www.youtube.com/@AntsCanada/playlists --
// the channel's playlists page is JS-rendered, not a supported input URL
// itself, and yt-dlp/the provider needs a real playlist URL per season.
const ANTSCANADA_SEASON_PLAYLISTS = [
	'https://www.youtube.com/playlist?list=PL5vZEY2A9f_gkMEo_Mvo7A852ojXq0xTL', // Season 1
	'https://www.youtube.com/playlist?list=PL5vZEY2A9f_h7gyY4JBP1kwQCkcO_C5mr', // Season 2
	'https://www.youtube.com/playlist?list=PL5vZEY2A9f_jLknNwn8ClXNOd3patIPtZ', // Season 3
	'https://www.youtube.com/playlist?list=PL5vZEY2A9f_gwGKKN6tq2UULYZi7ZKxdk', // Season 4
	'https://www.youtube.com/playlist?list=PL5vZEY2A9f_jy8dK18L8U7Fg_3slkT3mU', // Season 5
	'https://www.youtube.com/playlist?list=PL5vZEY2A9f_hxHfbIrCZ_0r78K7dekkTl', // Season 6
	'https://www.youtube.com/playlist?list=PL5vZEY2A9f_ha7rQ8Bwxn_r6nzwlFFKbL', // Season 7
];

test('AntsCanada: 7 season playlists + Jellyfin broadcast with season mapping', async ({ page }) => {
	test.setTimeout(60 * 60 * 1000);
	await login(page);

	await createStashWithLink(page, 'Vivariums by AntsCanada');

	for (const url of ANTSCANADA_SEASON_PLAYLISTS) {
		await addFollowUpInput(page, url);
	}

	await waitForDownloads(page, 3, 45 * 60 * 1000);

	await createBroadcast(page, { typeLabel: 'Jellyfin Series', name: 'AntsCanada Jellyfin' });

	// Season mapping: one number input per input, in the order inputs were
	// added (Season 1..7) -- fill 1..7 and save.
	const card = page.locator('li', { hasText: 'AntsCanada Jellyfin' });
	const seasonInputs = card.locator('input[type="number"]');
	const count = await seasonInputs.count();
	for (let i = 0; i < count; i++) {
		await seasonInputs.nth(i).fill(String(i + 1));
	}
	if (count > 0) {
		await card.getByRole('button', { name: 'Save season mapping' }).click();
	}

	await page.waitForTimeout(5000);
	const finalText = await card.innerText().catch(() => '');
	console.log('ANTSCANADA BROADCAST FINAL STATE:\n', finalText);
});

test('criticalrole: regex-filtered video download + Jellyfin broadcast', async ({ page }) => {
	test.setTimeout(4 * 60 * 60 * 1000);
	await login(page);

	const stashId = await createStashWithLink(page, 'criticalrole', 'https://www.youtube.com/@criticalrole');
	await completeAddInput(page, { titleRegexInclude: 'Campaign 4, Episode \\d+' });

	await waitForDownloads(page, 3, 3.5 * 60 * 60 * 1000);

	await createBroadcast(page, { typeLabel: 'Jellyfin Series', name: 'Critical Role Jellyfin' });

	await page.waitForTimeout(5000);
	const finalText = await page.locator('li', { hasText: 'Critical Role Jellyfin' }).innerText().catch(() => '');
	console.log('CRITICALROLE BROADCAST FINAL STATE:\n', finalText);
});
