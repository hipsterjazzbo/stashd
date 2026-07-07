import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './e2e',
    // e2e/manual/ hits live YouTube and real ffmpeg -- opt-in only
    // (npx playwright test e2e/manual/<file>), never part of a routine sweep.
    testIgnore: '**/manual/**',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    reporter: 'list',
    use: {
        baseURL: process.env.STASHD_BASE_URL ?? 'https://stashd.test',
        ignoreHTTPSErrors: true,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
