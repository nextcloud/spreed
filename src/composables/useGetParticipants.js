/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import debounce from 'debounce'
import { ref, computed, watch, onBeforeUnmount, onMounted } from 'vue'

import { subscribe, unsubscribe } from '@nextcloud/event-bus'

import { useIsInCall } from './useIsInCall.js'
import { useStore } from './useStore.js'
import { EventBus } from '../services/EventBus.js'
import { useSignalingStore } from '../stores/signaling.ts'

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
	const participantsInitialised = computed(() => store.getters.participantsInitialised(token.value))
	let fetchingParticipants = false
	let pendingChanges = true

	/**
	 * Initialise the get participants listeners
	 *
	 */
	function initialiseGetParticipants() {
		EventBus.on('joined-conversation', onJoinedConversation)
		EventBus.on('signaling-users-in-room', updateUsersFromInternalSignaling)
		EventBus.on('signaling-users-joined', updateUsersJoinedFromStandaloneSignaling)
		EventBus.on('signaling-users-left', updateUsersLeftFromStandaloneSignaling)
		EventBus.on('signaling-users-changed', updateUsersChangedFromStandaloneSignaling)
		EventBus.on('signaling-all-users-changed-in-call-to-disconnected', updateUsersCallDisconnectedFromStandaloneSignaling)
		// FIXME this works only temporary until signaling is fixed to be only on the calls
		// Then we have to search for another solution. Maybe the room list which we update
		// periodically gets a hash of all online sessions?
		EventBus.on('signaling-participant-list-changed', debouncePostponedUpdateParticipants)
		subscribe('guest-promoted', onJoinedConversation)
	}

	const updateUsersFromInternalSignaling = async ([participants]) => {
		const signalingStore = useSignalingStore()
		const hasUnknownSessions = signalingStore.updateParticipantsFromInternalSignaling(token.value, participants)
		if (hasUnknownSessions) {
			debounceUpdateParticipants()
		}
	}
	const updateUsersJoinedFromStandaloneSignaling = async ([participants]) => {
		const signalingStore = useSignalingStore()
		const hasUnknownSessions = signalingStore.updateParticipantsJoinedFromStandaloneSignaling(token.value, participants)
		if (hasUnknownSessions) {
			debounceUpdateParticipants()
		}
	}
	const updateUsersLeftFromStandaloneSignaling = ([signalingSessionIds]) => {
		const signalingStore = useSignalingStore()
		signalingStore.updateParticipantsLeftFromStandaloneSignaling(signalingSessionIds)
	}
	const updateUsersChangedFromStandaloneSignaling = ([participants]) => {
		const signalingStore = useSignalingStore()
		signalingStore.updateParticipantsChangedFromStandaloneSignaling(token.value, participants)
	}
	const updateUsersCallDisconnectedFromStandaloneSignaling = () => {
		const signalingStore = useSignalingStore()
		signalingStore.updateParticipantsCallDisconnectedFromStandaloneSignaling(token.value)
	}
	/**
	 * Stop the get participants listeners
	 *
	 */
	function stopGetParticipants() {
		EventBus.off('joined-conversation', onJoinedConversation)
		EventBus.off('signaling-users-in-room', updateUsersFromInternalSignaling)
		EventBus.off('signaling-users-joined', updateUsersJoinedFromStandaloneSignaling)
		EventBus.off('signaling-users-left', updateUsersLeftFromStandaloneSignaling)
		EventBus.off('signaling-users-changed', updateUsersChangedFromStandaloneSignaling)
		EventBus.off('signaling-all-users-changed-in-call-to-disconnected', updateUsersCallDisconnectedFromStandaloneSignaling)
		EventBus.off('signaling-participant-list-changed', debouncePostponedUpdateParticipants)
		unsubscribe('guest-promoted', onJoinedConversation)
	}

	const onJoinedConversation = () => {
		if (!participantsInitialised.value) {
			cancelableGetParticipants()
		} else {
			debounceUpdateParticipants()
		}
	}

	const debouncePostponedUpdateParticipants = () => {
		debounceUpdateParticipants(true)
	}

	const debounceUpdateParticipants = (postpone) => {
		if (!isActive.value && !isInCall.value) {
			// Update is ignored but there is a flag to force the participants update
			pendingChanges = true
			return
		}

		if (postpone) {
			console.count('perf: debounceLongUpdateParticipants')
			debounceLongUpdateParticipants()
		} else if (store.getters.windowIsVisible() && (isInCall.value || !conversation.value?.hasCall)) {
			console.count('perf: debounceFastUpdateParticipants')
			debounceFastUpdateParticipants()
		} else {
			console.count('perf: debounceSlowUpdateParticipants')
			debounceSlowUpdateParticipants()
		}
	    pendingChanges = false
	}

	const cancelableGetParticipants = async () => {
		console.count('perf: cancelableGetParticipants')
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
		fetchingParticipants = false
	}

	const debounceFastUpdateParticipants = debounce(
		cancelableGetParticipants, 3000)
	const debounceSlowUpdateParticipants = debounce(
		cancelableGetParticipants, 15000)
	const debounceLongUpdateParticipants = debounce(
		cancelableGetParticipants, 30000)

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
