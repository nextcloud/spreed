/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { onUnmounted } from 'vue'

/**
 * Request a wake lock to prevent the screen from turning off
 */
export function useWakeLock() {
	if (!('wakeLock' in navigator)) {
		return
	}

	const wakeLockRequest = navigator.wakeLock
		.request('screen')
		.catch(() => {
			// Web Lock is not available, e.g. battery saving mode is enabled
			// Ignoring
		})

	onUnmounted(async () => {
		// Component unmount could happen before the WakeLock request is resolved
		// Wait for the WakeLock request before releasing it
		const wakeLock = await wakeLockRequest
		wakeLock?.release()
	})
}
