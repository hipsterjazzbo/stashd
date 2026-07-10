import { defineConfig } from '@playwright/test';
import baseConfig from './playwright.config';

// playwright.config.ts's testIgnore: '**/manual/**' excludes e2e/manual/**
// from collection entirely -- it turns out that also blocks explicitly
// naming a manual spec on the CLI (`npx playwright test e2e/manual/x.spec.ts`
// still resolves to zero tests), contradicting the doc comment in both
// preship-smoke.spec.ts and the preship-e2e-smoke skill that says to run it
// that way. This config is the actual way to run it: same baseURL/projects/
// reporter as the main config, scoped to e2e/manual, with no testIgnore.
export default defineConfig(baseConfig, {
	testDir: './e2e/manual',
	testIgnore: undefined,
});
