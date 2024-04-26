/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * @type {Promise|null}
 */
let requestingTabLeadershipPromise = null

/**
 * Assign the first window, which requested that, a 'fetching leader'.
 * It will fetch conversations with defined interval and cache to BrowserStorage
 * Other will update list with defined interval from the BrowserStorage
 * Once 'leader' is closed, next requested tab will be assigned, and so on
 */
export function requestTabLeadership() {
	if (!requestingTabLeadershipPromise) {
		requestingTabLeadershipPromise = new Promise((resolve) => {
			// Locks are supported only with HTTPS protocol,
			// so we don't lock anything for another cases
			if (navigator.locks === undefined) {
				resolve()
				return
			}

			navigator.locks.request('talk:leader', () => {
				// resolve a promise for the first requested tab
				resolve()

				// return an infinity promise, resource is blocked until 'leader' tab is closed
				return new Promise(() => {
				})
			})
		})
	}
	return requestingTabLeadershipPromise
}
