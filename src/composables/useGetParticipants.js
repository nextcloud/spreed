/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import debounce from 'debounce'
import { ref, nextTick, computed, watch, onBeforeUnmount, onMounted } from 'vue'

import { subscribe, unsubscribe } from '@nextcloud/event-bus'

import { useDocumentVisibility } from './useDocumentVisibility.ts'
import { useIsInCall } from './useIsInCall.js'
import { useStore } from './useStore.js'
import { CONVERSATION } from '../constants.ts'
import { EventBus } from '../services/EventBus.ts'
import { useSessionStore } from '../stores/session.ts'

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
	const isDocumentVisible = useDocumentVisibility()
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
		EventBus.on('signaling-users-in-room', handleUsersUpdated)
		EventBus.on('signaling-users-joined', handleUsersUpdated)
		EventBus.on('signaling-users-changed', handleUsersUpdated)
		EventBus.on('signaling-users-left', handleUsersLeft)
		EventBus.on('signaling-all-users-changed-in-call-to-disconnected', handleUsersDisconnected)

		// FIXME this works only temporary until signaling is fixed to be only on the calls
		// Then we have to search for another solution. Maybe the room list which we update
		// periodically gets a hash of all online sessions?
		EventBus.on('signaling-participant-list-changed', debounceUpdateParticipants)
		subscribe('guest-promoted', onJoinedConversation)
	}

	const handleUsersUpdated = async ([users]) => {
		const sessionStore = useSessionStore()
		if (sessionStore.updateSessions(token.value, users)) {
			debounceUpdateParticipants()
		}
	}

	const handleUsersLeft = ([sessionIds]) => {
		const sessionStore = useSessionStore()
		sessionStore.updateSessionsLeft(sessionIds)
	}
	const handleUsersDisconnected = () => {
		const sessionStore = useSessionStore()
		sessionStore.updateParticipantsDisconnectedFromStandaloneSignaling(token.value)
	}
	/**
	 * Stop the get participants listeners
	 *
	 */
	function stopGetParticipants() {
		EventBus.off('joined-conversation', onJoinedConversation)
		EventBus.off('signaling-users-in-room', handleUsersUpdated)
		EventBus.off('signaling-users-joined', handleUsersUpdated)
		EventBus.off('signaling-users-changed', handleUsersUpdated)
		EventBus.off('signaling-users-left', handleUsersLeft)
		EventBus.off('signaling-all-users-changed-in-call-to-disconnected', handleUsersDisconnected)
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

		if (isDocumentVisible.value && (isInCall.value || !conversation.value?.hasCall)) {
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
