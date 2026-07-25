/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import SessionStorage from './SessionStorage.js'

const X_NEXTCLOUD_TALK_SESSION_TAB_ID = 'x-nextcloud-talk-session-tab-id'
const BASE62_CHARACTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'

/**
 * Generate a random string of base62 characters with given length (64 by default)
 *
 * @param length
 */
function generateRandomId(length = 64): string {
	const BASE62_LEN = BASE62_CHARACTERS.length // 62

	// Create an array of 32-bit unsigned integers and fill it with random values
	const randomValues = window.crypto.getRandomValues(new Uint32Array(length))

	// Build an array of random characters
	const result: string[] = new Array(length)
	for (let i = 0; i < length; i++) {
		result[i] = BASE62_CHARACTERS.charAt(randomValues[i] % BASE62_LEN)
	}

	return result.join('')
}

/**
 * Check whether the session tab id is already set. If not, generate a new one and save in the session storage.
 * Then add an axios interceptor to add this id as a header for all requests.
 *
 * Note - sessionStorage persists:
 * - on page reloads (expected to keep the same session)
 * - on tab duplication (FIXME would need to generate a new id in this case, maybe detect with BroadcastChannel API)
 */
export function setTalkSessionUniqueTabIdHeader() {
	const forceNewTabId = SessionStorage.getItem('force-new-talk-session-tab-id') === 'true'
	if (forceNewTabId) {
		// FIXME Clear SessionStorage (was duplicated from parent tab)
		SessionStorage.clear()
	}

	let tabId = SessionStorage.getItem(X_NEXTCLOUD_TALK_SESSION_TAB_ID)
	if (!tabId) {
		tabId = generateRandomId(64)
		SessionStorage.setItem(X_NEXTCLOUD_TALK_SESSION_TAB_ID, tabId)
	}

	axios.interceptors.request.use((config) => {
		// Do not clobber an explicitly set tab id. A request targeting a
		// secondary Talk session (see getBrowseSessionRequestConfig) sets its
		// own id, which must reach the server unchanged so it is mapped to a
		// separate session instead of the primary one.
		if (!config.headers[X_NEXTCLOUD_TALK_SESSION_TAB_ID]) {
			config.headers[X_NEXTCLOUD_TALK_SESSION_TAB_ID] = tabId
		}
		return config
	})
}

/**
 * Secondary, ephemeral tab id used to open a second Talk session from the same
 * tab (e.g. to browse another conversation with live updates while a call is
 * ongoing). Since https://github.com/nextcloud/spreed/pull/17230 the server
 * maps requests to distinct sessions by this id, so a different id yields a
 * second session without dropping the primary one.
 */
let browseSessionTabId: string | null = null

/**
 * Get (creating it on first use) the secondary tab id for the browse session.
 */
export function getBrowseSessionTabId(): string {
	if (!browseSessionTabId) {
		browseSessionTabId = generateRandomId(64)
	}
	return browseSessionTabId
}

/**
 * Rotate the secondary tab id, so the next browse session is a fresh one on the
 * server. Call this after fully tearing down a browse session.
 */
export function resetBrowseSessionTabId(): void {
	browseSessionTabId = null
}

/**
 * Build an axios request config that routes the request through the secondary
 * browse Talk session instead of the primary one.
 *
 * @param config additional axios request config to merge
 */
export function getBrowseSessionRequestConfig(config: Record<string, unknown> = {}): Record<string, unknown> {
	return {
		...config,
		headers: {
			...(config.headers as Record<string, string> ?? {}),
			[X_NEXTCLOUD_TALK_SESSION_TAB_ID]: getBrowseSessionTabId(),
		},
	}
}
