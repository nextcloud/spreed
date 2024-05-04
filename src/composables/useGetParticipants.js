/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import debounce from 'debounce'
import { ref, nextTick, computed, watch, onBeforeUnmount, onMounted } from 'vue'

import { subscribe, unsubscribe } from '@nextcloud/event-bus'

import { useIsInCall } from './useIsInCall.js'
import { useStore } from './useStore.js'
import { CONVERSATION } from '../constants.js'
import { EventBus } from '../services/EventBus.js'

/**
 * @param {import('vue').Ref} isActive whether the participants tab is active
 * @param {boolean} isTopBar whether the component is the top bar
 */
export function useGetParticipants(isActive = ref(true), isTopBar = true) {

	// Encapsulation
	const store = useStore()
	const token = computed(() => store.getters.getToken())
	const conversation = computed(() => store.getters.conversation(token.value))
	const isInCall = useIsInCall()
	const isOneToOneConversation = computed(() => conversation.value?.type === CONVERSATION.TYPE.ONE_TO_ONE
		|| conversation.value?.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER)
	let fetchingParticipants = false
	let pendingChanges = true

	/**
	 * Initialise the get participants listeners
	 *
	 */
	function initialiseGetParticipants() {
		EventBus.on('joined-conversation', onJoinedConversation)

		// FIXME this works only temporary until signaling is fixed to be only on the calls
		// Then we have to search for another solution. Maybe the room list which we update
		// periodically gets a hash of all online sessions?
		EventBus.on('signaling-participant-list-changed', debounceUpdateParticipants)
		subscribe('guest-promoted', onJoinedConversation)
	}

	/**
	 * Stop the get participants listeners
	 *
	 */
	function stopGetParticipants() {
		EventBus.off('joined-conversation', onJoinedConversation)
		EventBus.off('signaling-participant-list-changed', debounceUpdateParticipants)
		unsubscribe('guest-promoted', onJoinedConversation)
	}

	const onJoinedConversation = () => {
		if (isOneToOneConversation.value) {
			cancelableGetParticipants()
		} else {
			nextTick(() => debounceUpdateParticipants())
		}
	}

	const debounceUpdateParticipants = () => {
		if (!isActive.value && !isInCall.value) {
			// Update is ignored but there is a flag to force the participants update
			pendingChanges = true
			return
		}

		if (store.getters.windowIsVisible() && (isInCall.value || !conversation.value?.hasCall)) {
			debounceFastUpdateParticipants()
		} else {
			debounceSlowUpdateParticipants()
		}
	    pendingChanges = false
	}

	const cancelableGetParticipants = async () => {
		const isInLobby = store.getters.isInLobby
		const isModeratorOrUser = store.getters.isModeratorOrUser
		if (fetchingParticipants || token.value === '' || isInLobby || !isModeratorOrUser) {
			return
		}

		fetchingParticipants = true
		debounceFastUpdateParticipants.clear()
		debounceSlowUpdateParticipants.clear()

		await store.dispatch('fetchParticipants', { token: token.value })
		fetchingParticipants = false
	}

	const debounceFastUpdateParticipants = debounce(
		cancelableGetParticipants, 3000)
	const debounceSlowUpdateParticipants = debounce(
		cancelableGetParticipants, 15000)

	onMounted(() => {
		if (isTopBar) {
			initialiseGetParticipants()
		}
	})

	watch(isActive, (newValue) => {
		if (newValue && pendingChanges) {
			debounceUpdateParticipants()
		}
	})

	onBeforeUnmount(() => {
		if (isTopBar) {
			stopGetParticipants()
		}
	})

	return {
		cancelableGetParticipants,
	}
}
