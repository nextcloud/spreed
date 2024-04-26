/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCurrentInstance } from 'vue'

/**
 * Replacement of Vuex 4 built-in useStore in Vue 3
 *
 * @see https://vuex.vuejs.org/guide/composition-api.html
 * @todo remove after the migration to Vue 3
 * @return {import('vuex').Store<import('../store/storeConfig.js').default>}
 */
export function useStore() {
	return getCurrentInstance().proxy.$root.$options.store
}
