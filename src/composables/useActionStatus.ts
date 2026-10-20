/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { onScopeDispose, reactive } from 'vue'

export type ActionStatus = 'idle' | 'pending' | 'success' | 'error'

const RESET_DELAY = 2000

/**
 * Track the status of async actions (save, check, test, …) to show feedback in the UI.
 * The status falls back to 'idle' after a delay once the action is settled.
 *
 * @param resetDelay - time in ms to show 'success' or 'error' status
 */
export function useActionStatus(resetDelay: number = RESET_DELAY) {
	const statuses = reactive(new Map<string, ActionStatus>())
	const timeouts = new Map<string, ReturnType<typeof setTimeout>>()

	/**
	 * Get the current status of an action
	 *
	 * @param key - action identifier, e.g. an item id when tracking a list
	 */
	function getActionStatus(key: string): ActionStatus {
		return statuses.get(key) ?? 'idle'
	}

	/**
	 * Run an action and track its status. Errors are re-thrown to the caller.
	 *
	 * @param key - action identifier, e.g. an item id when tracking a list
	 * @param action - async action to run
	 */
	async function runAction<T>(key: string, action: () => Promise<T>): Promise<T> {
		clearTimeout(timeouts.get(key))
		statuses.set(key, 'pending')
		try {
			const result = await action()
			statuses.set(key, 'success')
			return result
		} catch (error) {
			statuses.set(key, 'error')
			throw error
		} finally {
			timeouts.set(key, setTimeout(() => {
				statuses.delete(key)
				timeouts.delete(key)
			}, resetDelay))
		}
	}

	onScopeDispose(() => {
		timeouts.forEach((timeout) => clearTimeout(timeout))
	})

	return {
		getActionStatus,
		runAction,
	}
}
