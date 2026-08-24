/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect } from '@playwright/test'
import { test } from '../support/fixtures/users.ts'

test('Can open the Talk main app', async ({ createSession }) => {
	const { page } = await createSession()

	await page.goto('/apps/spreed')
	// The left sidebar (conversation list) only renders once the app has
	// mounted and the logged-in user has been resolved.
	await expect(page.locator('#content')).toBeVisible()
	await expect(page.getByRole('navigation', { name: /conversation list/i })).toBeVisible()
})
