/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
	testDir: './tests/playwright/e2e',
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
	},
	projects: [
		{
			name: 'chromium',
			use: {
				...devices['Desktop Chrome'],
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
