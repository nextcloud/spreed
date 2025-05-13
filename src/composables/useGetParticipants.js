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
		// Internal signaling messages (in room)
		EventBus.on('signaling-users-in-room', handleUsersUpdated)
		// External signaling messages (join, left, change)
		EventBus.on('signaling-users-joined', handleUsersUpdated)
		EventBus.on('signaling-users-changed', handleUsersUpdated)
		EventBus.on('signaling-users-left', handleUsersLeft)
		EventBus.on('signaling-all-users-changed-in-call-to-disconnected', handleUsersDisconnected)
		// External signaling messages (participant added/removed)
		EventBus.on('signaling-participant-list-changed', handleOldBehaviour)
		subscribe('guest-promoted', onJoinedConversation)
	}

	const handleUsersUpdated = async ([users]) => {
		const sessionStore = useSessionStore()
		if (sessionStore.updateSessions(token.value, users)) {
			console.count(`should fetch list for ${token.value} | missing`)
			debounceUpdateParticipants()
		} else {
			debounceLongUpdateParticipants()
			console.count(`should fetch list for ${token.value} | long poll`)
		}
	}

	const handleOldBehaviour = () => {
		console.count(`should fetch list for ${token.value} | old behaviour`)
	}

	const handleUsersLeft = ([sessionIds]) => {
		const sessionStore = useSessionStore()
		sessionStore.updateSessionsLeft(token.value, sessionIds)
		debounceLongUpdateParticipants()
		console.count(`should fetch list for ${token.value} | long poll`)
	}
	const handleUsersDisconnected = () => {
		const sessionStore = useSessionStore()
		sessionStore.updateParticipantsDisconnectedFromStandaloneSignaling(token.value)
		debounceLongUpdateParticipants()
		console.count(`should fetch list for ${token.value} | long poll`)
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
		EventBus.off('signaling-participant-list-changed', handleOldBehaviour)
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
		debounceLongUpdateParticipants.clear()

		await store.dispatch('fetchParticipants', { token: token.value })
		console.count(`fetch list for ${token.value}`)
		fetchingParticipants = false
	}

	const debounceFastUpdateParticipants = debounce(
		cancelableGetParticipants, 3_000)
	const debounceSlowUpdateParticipants = debounce(
		cancelableGetParticipants, 15_000)
	const debounceLongUpdateParticipants = debounce(
		cancelableGetParticipants, 60_000)

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
