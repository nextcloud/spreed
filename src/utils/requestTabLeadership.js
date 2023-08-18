/**
 * @copyright Copyright (c) 2023 Maksim Sukharev <antreesy.web@gmail.com>
 *
 * @author Maksim Sukharev <antreesy.web@gmail.com>
 * @author Grigorii Shartsev <me@shgk.me>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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
