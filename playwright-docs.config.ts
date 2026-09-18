/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineConfig, devices } from '@playwright/test'
import baseConfig from './playwright.config.ts'

/**
 * Regenerates the screenshots.
 * Kept out of the default testDir so a normal `playwright test` run (and CI)
 * never picks these up: a doc screenshot is meant to be reviewed by a human
 * before it's committed, not asserted on automatically.
 * Run explicitly with `npm run playwright:docs`.
 */
export default defineConfig(baseConfig, {
	testDir: './tests/playwright/docs-screenshots',
	globalSetup: './tests/playwright/docs-screenshots/global-setup.ts',
	// Docs images must be pixel-consistent across runs/machines, and demo
	// accounts are shared state — no sharding, no parallel workers, no retries.
	retries: 0,
	workers: 1,
	fullyParallel: false,
	reporter: 'list',
	use: {
		...baseConfig.use,
		// Screenshots are the test's product, not a failure artifact.
		screenshot: 'off',
		trace: 'off',
	},
	projects: [
		{
			name: 'chromium',
			use: {
				// Set here, not in the top-level `use` above: a project's `use`
				// fully replaces matching keys from the top level rather than
				// deep-merging them, so devices['Desktop Chrome']'s own
				// viewport/deviceScaleFactor would otherwise silently win.
				...devices['Desktop Chrome'],
				viewport: { width: 1280, height: 800 },
				deviceScaleFactor: 2,
			},
		},
	],
})
