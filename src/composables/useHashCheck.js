/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showError, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { computed, watch } from 'vue'
import { useTalkHashStore } from '../stores/talkHash.js'
import { messagePleaseReload } from '../utils/talkDesktopUtils.ts'

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

		showError(t('spreed', 'Nextcloud Talk was updated.') + '\n' + messagePleaseReload, {
			timeout: TOAST_PERMANENT_TIMEOUT,
		})
	}

	return isNextcloudTalkHashDirty
}
