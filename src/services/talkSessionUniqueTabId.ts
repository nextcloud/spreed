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
	let tabId = SessionStorage.getItem(X_NEXTCLOUD_TALK_SESSION_TAB_ID)
	if (!tabId) {
		tabId = generateRandomId(64)
		SessionStorage.setItem(X_NEXTCLOUD_TALK_SESSION_TAB_ID, tabId)
	}

	axios.interceptors.request.use((config) => {
		config.headers[X_NEXTCLOUD_TALK_SESSION_TAB_ID] = tabId
		return config
	})
}
