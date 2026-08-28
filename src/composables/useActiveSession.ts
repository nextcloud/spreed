/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useStore } from 'vuex'
import { SESSION } from '../constants.ts'
import { hasTalkFeature } from '../services/CapabilitiesManager.ts'
import { setSessionState } from '../services/participantsService.js'
import { useTokenStore } from '../stores/token.ts'
import { isAxiosErrorResponse } from '../types/guards.ts'
import { useDocumentVisibility } from './useDocumentVisibility.ts'
import { useGetToken } from './useGetToken.ts'
import { useIsInCall } from './useIsInCall.js'

type SessionStateValue = typeof SESSION.STATE[keyof typeof SESSION.STATE]

const INACTIVE_TIME_MS = 60_000

// Sessions are created as active on the server (talk_sessions.state defaults to 1)
const currentState = ref<SessionStateValue>(SESSION.STATE.ACTIVE)

/**
 * Whether the session of the current conversation is active on the server.
 *
 * The server only notifies about messages with an inactive session, so marking
 * them as read has to follow the same signal, not the document visibility.
 *
 * @return whether the session is active
 */
export function useIsSessionActive() {
	return computed(() => currentState.value === SESSION.STATE.ACTIVE)
}

/**
 * Check whether the current session is active or not:
 * - tab or browser window was moved to background or minimized
 * - there was no movement within tab window for a long time
 * - work for both ChatView and CallView
 */
export function useActiveSession() {
	const store = useStore()
	const token = useGetToken()
	const tokenStore = useTokenStore()
	// Without 'session-state' feature support - conversation is always considered active
	const supportSessionState = computed(() => hasTalkFeature(token.value, 'session-state'))

	const isInCall = useIsInCall()
	const isDocumentVisible = useDocumentVisibility()

	let inactiveTimer: NodeJS.Timeout | undefined

	/**
	 * Whether the window is focused and visible right now.
	 */
	function isWindowActive() {
		return document.hasFocus() && isDocumentVisible.value
	}

	/**
	 * (Re)start the countdown to mark the session as inactive.
	 */
	function scheduleSessionAsInactive() {
		clearTimeout(inactiveTimer)
		inactiveTimer = setTimeout(setSessionAsInactive, INACTIVE_TIME_MS)
	}

	watch(token, () => {
		if (!supportSessionState.value) {
			return
		}
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

	watch(supportSessionState, (value) => {
		if (value) {
			window.addEventListener('focus', handleWindowFocus)
			window.addEventListener('blur', handleWindowFocus)
			if (!isWindowActive()) {
				scheduleSessionAsInactive()
			}
		} else {
			stopTrackingSessionState()
		}
	}, { immediate: true })

	onBeforeUnmount(stopTrackingSessionState)

	/**
	 * Undo everything set up while 'session-state' was supported.
	 */
	function stopTrackingSessionState() {
		window.removeEventListener('focus', handleWindowFocus)
		window.removeEventListener('blur', handleWindowFocus)
		document.body.removeEventListener('mouseenter', handleMouseEnter)
		document.body.removeEventListener('mouseleave', handleMouseLeave)
		clearTimeout(inactiveTimer)
		inactiveTimer = undefined
		currentState.value = SESSION.STATE.ACTIVE
	}

	/**
	 * Mark the session as active, on the client and on the server.
	 */
	async function setSessionAsActive() {
		if (!supportSessionState.value) {
			return
		}
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
			if (isAxiosErrorResponse(error) && error.response?.status === 404) {
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

	/**
	 * Mark the session as inactive, on the client and on the server.
	 */
	async function setSessionAsInactive() {
		if (!supportSessionState.value) {
			return
		}
		if (currentState.value === SESSION.STATE.INACTIVE
			|| !token.value) {
			return
		}
		if (isInCall.value) {
			// Sessions in a call stay active, the isInCall watcher repeats the update
			return
		}
		clearTimeout(inactiveTimer)
		inactiveTimer = undefined
		currentState.value = SESSION.STATE.INACTIVE

		try {
			await setSessionState(token.value, SESSION.STATE.INACTIVE)
			console.info('Session has been marked as inactive')
		} catch (error) {
			console.error(error)
			// The server still has it active, so it would keep swallowing notifications
			currentState.value = SESSION.STATE.ACTIVE
			if (isAxiosErrorResponse(error) && error.response?.status === 404) {
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

	/**
	 * Handle the window gaining or losing focus.
	 *
	 * @param event the focus/blur event
	 * @param event.type the event type, 'focus' or 'blur'
	 */
	function handleWindowFocus({ type }: FocusEvent) {
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

	/**
	 * Handle the mouse entering the tab while it is in the background.
	 */
	function handleMouseEnter() {
		// The window is not focused, so hovering it only postpones the update
		setSessionAsActive()
	}

	/**
	 * Handle the mouse leaving the tab while it is in the background.
	 */
	function handleMouseLeave() {
		// Restart timer, if mouse leaves the tab
		scheduleSessionAsInactive()
	}
}
