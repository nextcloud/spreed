/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed, onBeforeMount, onBeforeUnmount, ref, watch } from 'vue'
import { CONFIG, SESSION } from '../constants.ts'
import { getTalkConfig, hasTalkFeature } from '../services/CapabilitiesManager.ts'
import { setSessionState } from '../services/participantsService.js'
import { useTokenStore } from '../stores/token.ts'
import { useDocumentVisibility } from './useDocumentVisibility.ts'
import { useGetToken } from './useGetToken.ts'
import { useIsInCall } from './useIsInCall.js'
import { useStore } from './useStore.js'

const INACTIVE_TIME_MS = 3 * 60 * 1000

const experimentalRecoverSession = (getTalkConfig('local', 'experiments', 'enabled') ?? 0) & CONFIG.EXPERIMENTAL.RECOVER_SESSION

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

	if (!supportSessionState) {
		return false
	}

	const isInCall = useIsInCall()
	const isDocumentVisible = useDocumentVisibility()

	const inactiveTimer = ref(null)
	const currentState = ref(SESSION.STATE.ACTIVE)

	watch(token, () => {
		// Joined conversation has active state by default
		currentState.value = SESSION.STATE.ACTIVE
	})

	watch(isDocumentVisible, (value) => {
		// Change state if tab is hidden or minimized
		if (value) {
			setSessionAsActive()
		} else {
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
		if (currentState.value === SESSION.STATE.ACTIVE
			|| !token.value) {
			return
		}
		clearTimeout(inactiveTimer.value)
		inactiveTimer.value = null
		currentState.value = SESSION.STATE.ACTIVE

		try {
			await setSessionState(token.value, SESSION.STATE.ACTIVE)
			console.info('Session has been marked as active')
		} catch (error) {
			console.error(error)
			if (experimentalRecoverSession && error?.response?.status === 404) {
				// In case of 404 - participant did not have a session, block UI to join call
				tokenStore.updateLastJoinedConversationToken('')
				// Automatically try to join the conversation again
				store.dispatch('joinConversation', { token: token.value })
			}
		}
	}

	const setSessionAsInactive = async () => {
		if (currentState.value === SESSION.STATE.INACTIVE
			|| !token.value) {
			return
		}
		if (isInCall.value) {
			return
		}
		clearTimeout(inactiveTimer.value)
		inactiveTimer.value = null
		currentState.value = SESSION.STATE.INACTIVE

		try {
			await setSessionState(token.value, SESSION.STATE.INACTIVE)
			console.info('Session has been marked as inactive')
		} catch (error) {
			console.error(error)
			if (experimentalRecoverSession && error?.response?.status === 404) {
				// In case of 404 - participant did not have a session, block UI to join call
				tokenStore.updateLastJoinedConversationToken('')
				// Automatically try to join the conversation again
				store.dispatch('joinConversation', { token: token.value })
			}
		}
	}

	const handleWindowFocus = ({ type }) => {
		clearTimeout(inactiveTimer.value)
		if (type === 'focus') {
			setSessionAsActive()

			document.body.removeEventListener('mouseenter', handleMouseEnter)
			document.body.removeEventListener('mouseleave', handleMouseLeave)
		} else if (type === 'blur') {
			inactiveTimer.value = setTimeout(() => {
				setSessionAsInactive()
			}, INACTIVE_TIME_MS)

			// Listen for mouse events to track activity on tab
			document.body.addEventListener('mouseenter', handleMouseEnter)
			document.body.addEventListener('mouseleave', handleMouseLeave)
		}
	}

	const handleMouseEnter = (event) => {
		setSessionAsActive()
		clearTimeout(inactiveTimer.value)
		inactiveTimer.value = null
	}

	const handleMouseLeave = (event) => {
		// Restart timer, if mouse leaves the tab
		inactiveTimer.value = setTimeout(() => {
			setSessionAsInactive()
		}, INACTIVE_TIME_MS)
	}

	return true
}
