/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect } from '@playwright/test'
import { ADMIN_USER, test } from '../support/fixtures/users.ts'

test('Can open the Talk admin settings', async ({ createSession }) => {
	const { page } = await createSession(ADMIN_USER)

	await page.goto('/settings/admin/talk')
	await expect(page.locator('#admin_settings')).toBeVisible()
})
