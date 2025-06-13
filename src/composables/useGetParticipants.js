/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createSharedComposable } from '@vueuse/core'
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { CONFIG, CONVERSATION } from '../constants.ts'
import { getTalkConfig } from '../services/CapabilitiesManager.ts'
import { EventBus } from '../services/EventBus.ts'
import { useSessionStore } from '../stores/session.ts'
import { useDocumentVisibility } from './useDocumentVisibility.ts'
import { useGetToken } from './useGetToken.ts'
import { useIsInCall } from './useIsInCall.js'
import { useStore } from './useStore.js'

const experimentalUpdateParticipants = (getTalkConfig('local', 'experiments', 'enabled') ?? 0) & CONFIG.EXPERIMENTAL.UPDATE_PARTICIPANTS

/**
 * Composable to control logic for fetching participants list
 *
 * @param activeTab current active tab
 */
function useGetParticipantsComposable(activeTab = ref('participants')) {
	const sessionStore = useSessionStore()
	const store = useStore()
	const isInCall = useIsInCall()
	const isDocumentVisible = useDocumentVisibility()

	const isActive = computed(() => activeTab.value === 'participants')
	const token = useGetToken()
	const conversation = computed(() => store.getters.conversation(token.value))
	const isInLobby = computed(() => store.getters.isInLobby)
	const isModeratorOrUser = computed(() => store.getters.isModeratorOrUser)
	const isOneToOneConversation = computed(() => conversation.value?.type === CONVERSATION.TYPE.ONE_TO_ONE
		|| conversation.value?.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER)
	let fetchingParticipants = false
	let pendingChanges = true
	let throttleFastUpdateTimeout = null
	let throttleSlowUpdateTimeout = null
	let throttleLongUpdateTimeout = null

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
			EventBus.on('signaling-participant-list-updated', throttleUpdateParticipants)
		} else {
			// FIXME this works only temporary until signaling is fixed to be only on the calls
			// Then we have to search for another solution. Maybe the room list which we update
			// periodically gets a hash of all online sessions?
			EventBus.on('signaling-participant-list-changed', throttleUpdateParticipants)
		}
	}

	const handleUsersUpdated = async ([users]) => {
		if (sessionStore.updateSessions(token.value, users)) {
			throttleUpdateParticipants()
		} else {
			throttleLongUpdate()
		}
	}

	const handleUsersLeft = ([sessionIds]) => {
		sessionStore.updateSessionsLeft(token.value, sessionIds)
		throttleLongUpdate()
	}
	const handleUsersDisconnected = () => {
		sessionStore.updateParticipantsDisconnectedFromStandaloneSignaling(token.value)
		throttleLongUpdate()
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
		EventBus.off('signaling-participant-list-updated', throttleUpdateParticipants)
		EventBus.off('signaling-participant-list-changed', throttleUpdateParticipants)
	}

	const onJoinedConversation = () => {
		if (isOneToOneConversation.value || experimentalUpdateParticipants) {
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
		if (fetchingParticipants || token.value === '' || isInLobby.value || !isModeratorOrUser.value) {
			return
		}

		fetchingParticipants = true
		cancelPendingUpdates()

		await store.dispatch('fetchParticipants', { token: token.value })
		fetchingParticipants = false
	}

	const throttleFastUpdate = () => {
		if (!fetchingParticipants && !throttleFastUpdateTimeout) {
			throttleFastUpdateTimeout = setTimeout(cancelableGetParticipants, 3_000)
		}
	}
	const throttleSlowUpdate = () => {
		if (!fetchingParticipants && !throttleSlowUpdateTimeout) {
			throttleSlowUpdateTimeout = setTimeout(cancelableGetParticipants, 15_000)
		}
	}
	const throttleLongUpdate = () => {
		if (!fetchingParticipants && !throttleLongUpdateTimeout) {
			throttleLongUpdateTimeout = setTimeout(cancelableGetParticipants, 60_000)
		}
	}

	initialiseGetParticipants()

	watch(token, () => {
		cancelPendingUpdates()
	})

	watch(isActive, (newValue) => {
		if (newValue && pendingChanges) {
			throttleUpdateParticipants()
		}
	})

	watch(isModeratorOrUser, (newValue, oldValue) => {
		if (newValue && !oldValue) {
			// Fetch participants list if guest was promoted to moderators
			nextTick(() => throttleUpdateParticipants())
		}
	})

	onBeforeUnmount(() => {
		cancelPendingUpdates()
		stopGetParticipants()
	})

	/**
	 * Cancel scheduled participant list updates
	 * Applies to all parallel queues to not fetch twice
	 */
	function cancelPendingUpdates() {
		clearTimeout(throttleFastUpdateTimeout)
		throttleFastUpdateTimeout = null
		clearTimeout(throttleSlowUpdateTimeout)
		throttleSlowUpdateTimeout = null
		clearTimeout(throttleLongUpdateTimeout)
		throttleLongUpdateTimeout = null
	}

	return {
		cancelableGetParticipants,
	}
}

/**
 * Shared composable to control logic for fetching participants list
 */
export const useGetParticipants = createSharedComposable(useGetParticipantsComposable)
