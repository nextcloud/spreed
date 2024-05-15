/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { watch, computed } from 'vue'

// eslint-disable-next-line
// import { showError, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'

import { useTalkHashStore } from '../stores/talkHash.js'

/**
 * Check whether the conflicting session detected or not, and navigate to another page
 *
 * @return {import('vue').ComputedRef<boolean>}
 */
export function useHashCheck() {
	const talkHashStore = useTalkHashStore()

	let reloadWarningShown = false

	const isNextcloudTalkHashDirty = computed(() => talkHashStore.isNextcloudTalkHashDirty)

	watch(isNextcloudTalkHashDirty, (newValue) => {
		if (newValue && !reloadWarningShown) {
			showReloadWarning()
		}
	})

	const showReloadWarning = () => {
		reloadWarningShown = true

		window.OCP.Toast.error(t('spreed', 'Nextcloud Talk was updated, please reload the page'), {
			timeout: TOAST_PERMANENT_TIMEOUT,
		})
	}

	return isNextcloudTalkHashDirty
}
