/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { User } from '@nextcloud/e2e-test-server'
import type { Page } from '@playwright/test'

import { createRandomUser, login } from '@nextcloud/e2e-test-server/playwright'
import { test as base } from '@playwright/test'

/**
 * The fixed admin account every throwaway Nextcloud instance ships with.
 */
export const ADMIN_USER: User = { userId: 'admin', password: 'admin', language: 'en' }

export interface UserSession {
	user: User
	page: Page
}

/**
 * Test fixture exposing a `createSession()` factory instead of a fixed
 * number of named users — call it once per user a test needs. With no
 * argument it creates a fresh throwaway account; pass e.g. `ADMIN_USER` to
 * log in as a specific one. Every page opened this way is closed
 * automatically once the test ends.
 */
export const test = base.extend<{
	createSession: (user?: User) => Promise<UserSession>
}>({
	createSession: async ({ browser, baseURL }, use) => {
		const opened: Page[] = []

		await use(async (user) => {
			const resolvedUser = user ?? await createRandomUser()
			// Important: make sure we authenticate in a clean environment by unsetting storage state.
			const page = await browser.newPage({
				storageState: undefined,
				baseURL,
			})
			await login(page.request, resolvedUser)
			opened.push(page)
			return { user: resolvedUser, page }
		})

		await Promise.all(opened.map((page) => page.close()))
	},
})
