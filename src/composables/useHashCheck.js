/**
 * @copyright Copyright (c) 2023 Maksim Sukharev <antreesy.web@gmail.com>
 *
 * @author Maksim Sukharev <antreesy.web@gmail.com>
 * @author Marco Ambrosini <marcoambrosini@icloud.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { watch, computed, ref } from 'vue'

import { showError, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'

import { useStore } from './useStore.js'

/**
 * Check whether the conflicting session detected or not, and navigate to another page
 *
 * @return {import('vue').ComputedRef<boolean>}
 */
export function useHashCheck() {
	const store = useStore()

	const reloadWarningShown = ref(false)

	const isNextcloudTalkHashDirty = computed(() => {
		return store.getters.isNextcloudTalkHashDirty
	})

	watch(isNextcloudTalkHashDirty, (newValue) => {
		if (newValue && !reloadWarningShown.value) {
			showReloadWarning()
		}
	})

	const showReloadWarning = () => {
		reloadWarningShown.value = true

		showError(t('spreed', 'Nextcloud Talk was updated, please reload the page'), {
			timeout: TOAST_PERMANENT_TIMEOUT,
		})
	}

	return isNextcloudTalkHashDirty
}
