/*
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
 * @copyright Copyright (c) 2023 Grigorii Shartsev <me@shgk.me>
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
 */

import { computed, onBeforeMount, onBeforeUnmount, ref } from 'vue'

import { useStore } from './useStore.js'
import { EventBus } from '../services/EventBus.js'
import SessionStorage from '../services/SessionStorage.js'

/**
 * Check whether the user joined the call of the current token in this PHP session or not
 *
 * @return {import('vue').ComputedRef<boolean>}
 */
export function useIsInCall() {
	const store = useStore()

	const sessionStorageJoinedConversation = ref(null)

	const readSessionStorageJoinedConversation = () => {
		sessionStorageJoinedConversation.value = SessionStorage.getItem('joined_conversation')
	}

	onBeforeMount(() => {
		EventBus.on('joined-conversation', readSessionStorageJoinedConversation)
		readSessionStorageJoinedConversation()
	})

	onBeforeUnmount(() => {
		EventBus.off('joined-conversation', readSessionStorageJoinedConversation)
	})

	return computed(() => {
		if (store.getters.forceCallView) {
			return true
		}
		return sessionStorageJoinedConversation.value === store.getters.getToken() && store.getters.isInCall(store.getters.getToken())
	})
}
