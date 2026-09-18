/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { User } from '@nextcloud/e2e-test-server'
import type { Page } from '@playwright/test'

import { readFile } from 'node:fs/promises'
import { join } from 'node:path'
import { login } from '../../support/database.ts'
import { test as base } from '../../support/fixtures/conversations.ts'
import { DEMO_PASSWORD } from '../demo-users-list.ts'

const AVATARS_DIR = join(process.cwd(), 'tests/playwright/docs-screenshots/demo-avatars')

export interface DemoUserSession {
	user: User
	page: Page
}

/**
 * Uploads `demo-avatars/<userId>.png` as the account's personal avatar.
 *
 * `OCS-APIRequest: true` skips the CSRF token core's avatar endpoint would
 * otherwise require for a session-cookie request — same trick Talk's own
 * OCS-based APIs get for free by extending OCSController.
 */
async function setDemoAvatar(page: Page, userId: string): Promise<void> {
	const buffer = await readFile(join(AVATARS_DIR, `${userId}.png`))
	await page.request.post('./avatar/', {
		headers: { 'OCS-APIRequest': 'true' },
		multipart: { 'files[]': { name: `${userId}.png`, mimeType: 'image/png', buffer } },
	})
}

/**
 * Test fixture exposing a `createDemoSession()` factory: logs in as one of
 * DEMO_USERS (seeded up front by global-setup.ts) instead of
 * `createSession()`'s throwaway random account, and sets their demo avatar.
 * Use this for any test whose pages a docs screenshot is taken of.
 */
export const test = base.extend<{
	createDemoSession: (userId: string) => Promise<DemoUserSession>
}>({
	createDemoSession: async ({ browser, baseURL }, use) => {
		const opened: Page[] = []

		await use(async (userId) => {
			const user: User = { userId, password: DEMO_PASSWORD, language: 'en' }
			const page = await browser.newPage({ storageState: undefined, baseURL })
			await login(page.request, user)
			await setDemoAvatar(page, userId)
			opened.push(page)
			return { user, page }
		})

		await Promise.all(opened.map((page) => page.close()))
	},
})
