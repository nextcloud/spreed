/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	Conversation,
	Participant,
	SignalingSessionPayload,
	StandaloneSignalingLeaveSession,
	StandaloneSignalingUpdateSession,
} from '../types/index.ts'

import { createSharedComposable } from '@vueuse/core'
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { CONFIG, CONVERSATION } from '../constants.ts'
import { getTalkConfig } from '../services/CapabilitiesManager.ts'
import { EventBus } from '../services/EventBus.ts'
import { useActorStore } from '../stores/actor.ts'
import { useSessionStore } from '../stores/session.ts'
import { useDocumentVisibility } from './useDocumentVisibility.ts'
import { useGetToken } from './useGetToken.ts'
import { useIsInCall } from './useIsInCall.js'
import { useStore } from './useStore.js'

const experimentalUpdateParticipants = (getTalkConfig('local', 'experiments', 'enabled') ?? 0) & CONFIG.EXPERIMENTAL.UPDATE_PARTICIPANTS

let fetchingParticipants = false
let pendingChanges = true
let throttleFastUpdateTimeout: NodeJS.Timeout | undefined
let throttleSlowUpdateTimeout: NodeJS.Timeout | undefined
let throttleLongUpdateTimeout: NodeJS.Timeout | undefined

/**
 * Composable to control logic for fetching participants list
 *
 * @param activeTab current active tab
 */
function useGetParticipantsComposable(activeTab = ref('participants')) {
	const actorStore = useActorStore()
	const sessionStore = useSessionStore()
	const store = useStore()
	const isInCall = useIsInCall()
	const isDocumentVisible = useDocumentVisibility()

	const isActive = computed(() => activeTab.value === 'participants')
	const token = useGetToken()
	const conversation = computed<Conversation | undefined>(() => store.getters.conversation(token.value))
	const isInLobby = computed<boolean>(() => store.getters.isInLobby)
	const isModeratorOrUser = computed<boolean>(() => store.getters.isModeratorOrUser)
	const isOneToOneConversation = computed(() => conversation.value?.type === CONVERSATION.TYPE.ONE_TO_ONE
		|| conversation.value?.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER)

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
		EventBus.on('signaling-users-changed', checkCurrentUserPermissions)
	}

	/**
	 * Patch participants list from signaling messages (joined/changed)
	 */
	function handleUsersUpdated([users]: [SignalingSessionPayload[]]) {
		if (sessionStore.updateSessions(token.value, users)) {
			throttleUpdateParticipants()
		} else {
			throttleLongUpdate()
		}
	}

	/**
	 * Check if current user permission has been changed from signaling messages
	 */
	async function checkCurrentUserPermissions([users]: [StandaloneSignalingUpdateSession[]]) {
		// TODO: move logic to sessionStore once experimental flag is dropped
		const currentUser = users.find((user) => {
			return user.userId ? user.userId === actorStore.userId : user.actorId === actorStore.actorId
		})
		if (!currentUser) {
			return
		}
		// refresh conversation, if current user permissions have been changed
		if (currentUser.participantPermissions !== conversation.value?.permissions) {
			await store.dispatch('fetchConversation', { token: token.value })
		}

		const currentParticipant = store.getters.getParticipant(token.value, actorStore.attendeeId)
		if (currentParticipant && isModeratorOrUser.value && currentUser.participantPermissions !== currentParticipant.permissions) {
			await cancelableGetParticipants()
		}
	}

	/**
	 * Patch participants list from signaling messages (left)
	 */
	function handleUsersLeft([sessionIds]: [StandaloneSignalingLeaveSession[]]) {
		sessionStore.updateSessionsLeft(token.value, sessionIds)
		throttleLongUpdate()
	}

	/**
	 * Patch participants list from signaling messages (end call for everyone)
	 */
	function handleUsersDisconnected() {
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
		EventBus.off('signaling-users-changed', checkCurrentUserPermissions)
	}

	/**
	 * Trigger participants list update upon joining
	 */
	async function onJoinedConversation() {
		if (isOneToOneConversation.value || experimentalUpdateParticipants) {
			cancelableGetParticipants()
		} else {
			nextTick(() => throttleUpdateParticipants())
		}
	}

	/**
	 * Schedule a participants list update depending on conditions:
	 * - participant list is visible - fast update
	 * - chat is open, tab is in background, user is not in the current call - slow update
	 */
	function throttleUpdateParticipants() {
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

	/**
	 * Update participants list
	 */
	async function cancelableGetParticipants() {
		if (fetchingParticipants || token.value === '' || isInLobby.value || !isModeratorOrUser.value) {
			return
		}

		fetchingParticipants = true
		cancelPendingUpdates()

		await store.dispatch('fetchParticipants', { token: token.value })
		fetchingParticipants = false
	}

	/**
	 * Schedule a participants list update (in 3 seconds)
	 */
	function throttleFastUpdate() {
		if (!fetchingParticipants && !throttleFastUpdateTimeout) {
			throttleFastUpdateTimeout = setTimeout(cancelableGetParticipants, 3_000)
		}
	}

	/**
	 * Schedule a participants list update (in 15 seconds)
	 */
	function throttleSlowUpdate() {
		if (!fetchingParticipants && !throttleSlowUpdateTimeout) {
			throttleSlowUpdateTimeout = setTimeout(cancelableGetParticipants, 15_000)
		}
	}

	/**
	 * Schedule a participants list update (in 60 seconds)
	 */
	function throttleLongUpdate() {
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
		throttleFastUpdateTimeout = undefined
		clearTimeout(throttleSlowUpdateTimeout)
		throttleSlowUpdateTimeout = undefined
		clearTimeout(throttleLongUpdateTimeout)
		throttleLongUpdateTimeout = undefined
	}

	return {
		cancelableGetParticipants,
	}
}

/**
 * Shared composable to control logic for fetching participants list
 */
export const useGetParticipants = createSharedComposable(useGetParticipantsComposable)
