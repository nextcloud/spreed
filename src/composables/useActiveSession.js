/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed, onBeforeMount, onBeforeUnmount, ref, watch } from 'vue'
import { useStore } from 'vuex'
import { SESSION } from '../constants.ts'
import { hasTalkFeature } from '../services/CapabilitiesManager.ts'
import { setSessionState } from '../services/participantsService.js'
import { useTokenStore } from '../stores/token.ts'
import { useDocumentVisibility } from './useDocumentVisibility.ts'
import { useGetToken } from './useGetToken.ts'
import { useIsInCall } from './useIsInCall.js'

const INACTIVE_TIME_MS = 60_000

// Sessions are created as active on the server (talk_sessions.state defaults to 1)
const currentState = ref(SESSION.STATE.ACTIVE)

/**
 * Whether the session of the current conversation is active on the server.
 *
 * The server only notifies about messages with an inactive session, so marking
 * them as read has to follow the same signal, not the document visibility.
 *
 * @return {import('vue').ComputedRef<boolean>} whether the session is active
 */
export function useIsSessionActive() {
	return computed(() => currentState.value === SESSION.STATE.ACTIVE)
}

/**
 * Check whether the current session is active or not:
 * - tab or browser window was moved to background or minimized
 * - there was no movement within tab window for a long time
 * - work for both ChatView and CallView
 *
 * @return {boolean|undefined}
 */
export function useActiveSession() {
	const store = useStore()
	const token = useGetToken()
	const tokenStore = useTokenStore()
	// FIXME has no API support on federated conversations
	const supportSessionState = computed(() => hasTalkFeature(token.value, 'session-state'))

	if (!supportSessionState.value) {
		return false
	}

	const isInCall = useIsInCall()
	const isDocumentVisible = useDocumentVisibility()

	let inactiveTimer = null
	const isWindowActive = () => document.hasFocus() && isDocumentVisible.value

	const scheduleSessionAsInactive = () => {
		clearTimeout(inactiveTimer)
		inactiveTimer = setTimeout(setSessionAsInactive, INACTIVE_TIME_MS)
	}

	watch(token, () => {
		// Joined conversation has active state by default
		currentState.value = SESSION.STATE.ACTIVE
		// Updating right away would race with joining the conversation
		if (!isWindowActive()) {
			scheduleSessionAsInactive()
		}
	})

	watch(isDocumentVisible, (value) => {
		// Change state if tab is hidden or minimized
		if (value) {
			setSessionAsActive()
		} else {
			setSessionAsInactive()
		}
	})

	watch(isInCall, (value) => {
		// Repeat the update which was skipped for the duration of the call
		if (!value && !isWindowActive()) {
			setSessionAsInactive()
		}
	})

	onBeforeMount(() => {
		window.addEventListener('focus', handleWindowFocus)
		window.addEventListener('blur', handleWindowFocus)
	})

	onBeforeUnmount(() => {
		window.removeEventListener('focus', handleWindowFocus)
		window.removeEventListener('blur', handleWindowFocus)
	})

	const setSessionAsActive = async () => {
		// Without re-arming, a background window stays active until the next focus
		if (isWindowActive()) {
			clearTimeout(inactiveTimer)
		} else {
			scheduleSessionAsInactive()
		}

		if (currentState.value === SESSION.STATE.ACTIVE
			|| !token.value) {
			return
		}
		currentState.value = SESSION.STATE.ACTIVE

		try {
			await setSessionState(token.value, SESSION.STATE.ACTIVE)
			console.info('Session has been marked as active')
		} catch (error) {
			console.error(error)
			if (error?.response?.status === 404) {
				// In case of 404 - participant did not have a session, block UI to join call
				tokenStore.updateLastJoinedConversationToken('')
				// Automatically try to join the conversation again
				store.dispatch('joinConversation', { token: token.value })
			} else {
				// Follow the server, which keeps notifying about new messages
				currentState.value = SESSION.STATE.INACTIVE
			}
		}
	}

	const setSessionAsInactive = async () => {
		if (currentState.value === SESSION.STATE.INACTIVE
			|| !token.value) {
			return
		}
		if (isInCall.value) {
			// Sessions in a call stay active, the isInCall watcher repeats the update
			return
		}
		clearTimeout(inactiveTimer)
		inactiveTimer = null
		currentState.value = SESSION.STATE.INACTIVE

		try {
			await setSessionState(token.value, SESSION.STATE.INACTIVE)
			console.info('Session has been marked as inactive')
		} catch (error) {
			console.error(error)
			// The server still has it active, so it would keep swallowing notifications
			currentState.value = SESSION.STATE.ACTIVE
			if (error?.response?.status === 404) {
				// In case of 404 - participant did not have a session, block UI to join call
				tokenStore.updateLastJoinedConversationToken('')
				// Automatically try to join the conversation again
				store.dispatch('joinConversation', { token: token.value })
			}
			if (!isWindowActive()) {
				scheduleSessionAsInactive()
			}
		}
	}

	const handleWindowFocus = ({ type }) => {
		clearTimeout(inactiveTimer)
		if (type === 'focus') {
			setSessionAsActive()

			document.body.removeEventListener('mouseenter', handleMouseEnter)
			document.body.removeEventListener('mouseleave', handleMouseLeave)
		} else if (type === 'blur') {
			scheduleSessionAsInactive()

			// Listen for mouse events to track activity on tab
			document.body.addEventListener('mouseenter', handleMouseEnter)
			document.body.addEventListener('mouseleave', handleMouseLeave)
		}
	}

	const handleMouseEnter = (event) => {
		// The window is not focused, so hovering it only postpones the update
		setSessionAsActive()
	}

	const handleMouseLeave = (event) => {
		// Restart timer, if mouse leaves the tab
		scheduleSessionAsInactive()
	}

	return true
}
