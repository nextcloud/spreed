/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test as setup } from '@playwright/test'
import { runOcc } from '../support/database.ts'
import { DEMO_PASSWORD, DEMO_USERS } from './demo-users-list.ts'

/**
 * Runs once as the `docs-setup` project, which the `docs` project depends on.
 *
 * Seeds every {@link DEMO_USERS} account up front so individual docs
 * screenshot specs don't each pay for a `user:info` + `user:add` round trip.
 */
setup('seed demo users', async () => {
	for (const { userId, displayName } of DEMO_USERS) {
		const { exitCode } = await runOcc(['user:info', userId], { failOnError: false })
		if (exitCode === 0) {
			continue
		}

		await runOcc(['user:add', '--password-from-env', '--display-name', displayName, userId], {
			env: [`NC_PASS=${DEMO_PASSWORD}`],
		})
	}
})
