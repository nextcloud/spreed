/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { runOcc } from '../support/database.ts'
import { DEMO_PASSWORD, DEMO_USERS } from './demo-users-list.ts'

/**
 * Runs once against the already-healthy webServer, before any worker or
 * test starts — wired up only in playwright-docs.config.ts, via its
 * `globalSetup` option, never the normal e2e config.
 *
 * Seeds every {@link DEMO_USERS} account up front so individual docs
 * screenshot specs don't each pay for a `user:info` + `user:add` round trip.
 */
export default async function globalSetup() {
	for (const { userId, displayName } of DEMO_USERS) {
		const { exitCode } = await runOcc(['user:info', userId], { failOnError: false })
		if (exitCode === 0) {
			continue
		}

		await runOcc(['user:add', '--password-from-env', '--display-name', displayName, userId], {
			env: [`NC_PASS=${DEMO_PASSWORD}`],
		})
	}
}
