/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { DocsOptions } from './tests/playwright/support/fixtures/users.ts'

import { defineConfig, devices } from '@playwright/test'

export default defineConfig<DocsOptions>({
	timeout: 90_000,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 1 : 0,
	workers: process.env.CI ? 1 : undefined,
	reporter: process.env.CI ? [['blob'], ['dot'], ['github']] : 'html',
	use: {
		// Trailing slash matters: fixtures resolve relative paths (e.g. './login')
		// against this, and without it the last segment ("index.php") gets
		// replaced instead of appended.
		baseURL: 'http://localhost:8089/index.php/',
		trace: 'on-first-retry',
		screenshot: 'only-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			testDir: './tests/playwright/e2e',
			use: {
				...devices['Desktop Chrome'],
				// Uses the runner's pre-installed system Chrome instead of
				// Playwright's own bundled Chromium — playwright.yml's CI
				// workflow relies on this to skip a browser install step.
				channel: 'chrome',
			},
		},
		// Docs screenshots: run only via `npm run playwright:docs`, reviewed by a human, not asserted in CI
		{
			name: 'docs-setup',
			testDir: './tests/playwright/docs-screenshots',
			testMatch: 'global.setup.ts',
		},
		{
			name: 'docs',
			// Same specs as e2e, in docs mode: demo users, and docsScreenshot() writes files
			testDir: './tests/playwright/e2e',
			grep: /@docs/,
			dependencies: ['docs-setup'],
			// Pixel-consistent images and shared demo accounts: no retries, no parallel workers
			retries: 0,
			workers: 1,
			fullyParallel: false,
			use: {
				...devices['Desktop Chrome'],
				viewport: { width: 1280, height: 800 },
				deviceScaleFactor: 2,
				docsMode: true,
				screenshot: 'off',
				trace: 'off',
			},
		},
	],
	webServer: {
		command: 'node tests/playwright/start-nextcloud-server.mjs',
		env: {
			NEXTCLOUD_PORT: '8089',
		},
		stderr: 'pipe',
		stdout: 'pipe',
		gracefulShutdown: {
			signal: 'SIGTERM',
			timeout: 10000,
		},
		reuseExistingServer: !process.env.CI,
		timeout: 5 * 60 * 1000,
		wait: {
			stdout: /Nextcloud is now ready to use/,
		},
	},
})
