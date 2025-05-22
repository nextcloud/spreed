/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { ref, nextTick, computed, watch, onBeforeUnmount, onMounted } from 'vue'

import { subscribe, unsubscribe } from '@nextcloud/event-bus'

import { useDocumentVisibility } from './useDocumentVisibility.ts'
import { useIsInCall } from './useIsInCall.js'
import { useStore } from './useStore.js'
import { CONFIG, CONVERSATION } from '../constants.ts'
import { getTalkConfig } from '../services/CapabilitiesManager.ts'
import { EventBus } from '../services/EventBus.ts'
import { useSessionStore } from '../stores/session.ts'

const experimentalUpdateParticipants = (getTalkConfig('local', 'experiments', 'enabled') ?? 0) & CONFIG.EXPERIMENTAL.UPDATE_PARTICIPANTS

/**
 * @param {import('vue').Ref} isActive whether the participants tab is active
 * @param {boolean} isTopBar whether the component is the top bar
 */
export function useGetParticipants(isActive = ref(true), isTopBar = true) {

	// Encapsulation
	const sessionStore = useSessionStore()
	const store = useStore()
	const token = computed(() => store.getters.getToken())
	const conversation = computed(() => store.getters.conversation(token.value))
	const isInCall = useIsInCall()
	const isDocumentVisible = useDocumentVisibility()
	const isOneToOneConversation = computed(() => conversation.value?.type === CONVERSATION.TYPE.ONE_TO_ONE
		|| conversation.value?.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER)
	let fetchingParticipants = false
	let pendingChanges = true
	let throttleFastUpdateTimeout = null
	let throttleSlowUpdateTimeout = null

	/**
	 * Initialise the get participants listeners
	 *
	 */
	function initialiseGetParticipants() {
		EventBus.on('joined-conversation', onJoinedConversation)
		if (experimentalUpdateParticipants) {
			EventBus.on('signaling-users-in-room', handleUsersUpdated)
			EventBus.on('signaling-users-joined', handleUsersUpdated)
			EventBus.on('signaling-users-changed', handleUsersUpdated)
			EventBus.on('signaling-users-left', handleUsersLeft)
			EventBus.on('signaling-all-users-changed-in-call-to-disconnected', handleUsersDisconnected)
		}

		// FIXME this works only temporary until signaling is fixed to be only on the calls
		// Then we have to search for another solution. Maybe the room list which we update
		// periodically gets a hash of all online sessions?
		EventBus.on('signaling-participant-list-changed', throttleUpdateParticipants)
		subscribe('guest-promoted', onJoinedConversation)
	}

	const handleUsersUpdated = async ([users]) => {
		if (sessionStore.updateSessions(token.value, users)) {
			throttleUpdateParticipants()
		}
	}

	const handleUsersLeft = ([sessionIds]) => {
		sessionStore.updateSessionsLeft(token.value, sessionIds)
	}
	const handleUsersDisconnected = () => {
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
		EventBus.off('signaling-participant-list-changed', throttleUpdateParticipants)
		unsubscribe('guest-promoted', onJoinedConversation)
	}

	const onJoinedConversation = () => {
		if (isOneToOneConversation.value) {
			cancelableGetParticipants()
		} else {
			nextTick(() => throttleUpdateParticipants())
		}
	}

	const throttleUpdateParticipants = () => {
		if (!isActive.value && !isInCall.value) {
			// Update is ignored but there is a flag to force the participants update
			pendingChanges = true
			return
		}

		if (isDocumentVisible.value && (isInCall.value || !conversation.value?.hasCall)) {
			throttleFastUpdate()
		} else {
			throttleSlowUpdate()
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

		// Cancel the parallel request queue to not fetch twice
		clearTimeout(throttleFastUpdateTimeout)
		throttleFastUpdateTimeout = null
		clearTimeout(throttleSlowUpdateTimeout)
		throttleSlowUpdateTimeout = null

		await store.dispatch('fetchParticipants', { token: token.value })
		fetchingParticipants = false
	}

	const throttleFastUpdate = () => {
		if (throttleFastUpdateTimeout) {
			return
		}
		throttleFastUpdateTimeout = setTimeout(cancelableGetParticipants, 3_000)
	}
	const throttleSlowUpdate = () => {
		if (throttleSlowUpdateTimeout) {
			return
		}
		throttleSlowUpdateTimeout = setTimeout(cancelableGetParticipants, 15_000)
	}

	onMounted(() => {
		if (isTopBar) {
			initialiseGetParticipants()
		}
	})

	watch(isActive, (newValue) => {
		if (newValue && pendingChanges) {
			throttleUpdateParticipants()
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
