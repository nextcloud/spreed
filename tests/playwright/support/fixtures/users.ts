/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { User } from '@nextcloud/e2e-test-server'
import type { Locator, Page } from '@playwright/test'

import { test as base } from '@playwright/test'
import { DEMO_PASSWORD, DEMO_USERS } from '../../docs-screenshots/demo-users-list.ts'
import { saveDocsScreenshot, setDemoAvatar } from '../../docs-screenshots/docs-screenshot.ts'
import { createRandomUser, login } from '../database.ts'

/**
 * The fixed admin account every throwaway Nextcloud instance ships with.
 */
export const ADMIN_USER: User = { userId: 'admin', password: 'admin', language: 'en' }

export interface UserSession {
	user: User
	page: Page
}

export interface DocsOptions {
	/** Set by the `docs` project: sessions use demo users, `docsScreenshot()` writes files */
	docsMode: boolean
}

/**
 * Test fixture exposing a `createSession()` factory instead of a fixed
 * number of named users — call it once per user a test needs. With no
 * argument it creates a fresh throwaway account (in docs mode: the next
 * of DEMO_USERS, with their avatar); pass e.g. `ADMIN_USER` to log in as
 * a specific one. Every page opened this way is closed automatically once
 * the test ends.
 *
 * `docsScreenshot()` saves a docs image in docs mode and does nothing otherwise.
 */
export const test = base.extend<DocsOptions & {
	createSession: (user?: User) => Promise<UserSession>
	docsScreenshot: (target: Page | Locator, name: string) => Promise<void>
}>({
	docsMode: [false, { option: true }],

	createSession: async ({ browser, baseURL, docsMode }, use) => {
		const opened: Page[] = []
		let demoIndex = 0

		await use(async (user) => {
			let resolvedUser = user
			let isDemoUser = false
			if (!resolvedUser && docsMode) {
				const demoUser = DEMO_USERS[demoIndex++]
				if (!demoUser) {
					throw new Error(`Only ${DEMO_USERS.length} demo users exist, add more to demo-users-list.ts`)
				}
				resolvedUser = { userId: demoUser.userId, password: DEMO_PASSWORD, language: 'en' }
				isDemoUser = true
			}
			resolvedUser ??= await createRandomUser()

			// Important: make sure we authenticate in a clean environment by unsetting storage state.
			const page = await browser.newPage({
				storageState: undefined,
				baseURL,
			})
			await login(page.request, resolvedUser)
			if (isDemoUser) {
				await setDemoAvatar(page, resolvedUser.userId)
			}
			opened.push(page)
			return { user: resolvedUser, page }
		})

		await Promise.all(opened.map((page) => page.close()))
	},

	docsScreenshot: async ({ docsMode }, use) => {
		await use(async (target, name) => {
			if (docsMode) {
				await saveDocsScreenshot(target, name)
			}
		})
	},
})
