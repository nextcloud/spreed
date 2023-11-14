/*
 * @copyright Copyright (c) 2023 Maksim Sukharev <antreesy.web@gmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

import { computed, onBeforeMount, onBeforeUnmount, ref, watch } from 'vue'

import { getCapabilities } from '@nextcloud/capabilities'
import { showInfo } from '@nextcloud/dialogs'

import { SESSION } from '../constants.js'
import { setSessionState } from '../services/participantsService.js'
import { useStore } from './useStore.js'

const supportSessionState = getCapabilities()?.spreed?.features?.includes('session-state')
const INACTIVE_TIME_MS = 3 * 60 * 1000
/**
 * Check whether the current session is active or not:
 * - tab or browser window was moved to background or minimized
 * - there was no movement within tab window for a long time
 * - work for both ChatView and CallView
 *
 * @return {boolean|undefined}
 */
export function useActiveSession() {
	if (!supportSessionState) {
		return supportSessionState
	}

	const store = useStore()
	const token = computed(() => store.getters.getToken())
	const windowIsVisible = computed(() => store.getters.windowIsVisible())

	const inactiveTimer = ref(null)
	const currentState = ref(SESSION.STATE.ACTIVE)

	watch(token, () => {
		// Joined conversation has active state by default
		currentState.value = SESSION.STATE.ACTIVE
	})

	watch(windowIsVisible, (value) => {
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
		}
	}

	const setSessionAsInactive = async () => {
		if (currentState.value === SESSION.STATE.INACTIVE
			|| !token.value) {
			return
		}
		clearTimeout(inactiveTimer.value)
		inactiveTimer.value = null
		currentState.value = SESSION.STATE.INACTIVE

		try {
			await setSessionState(token.value, SESSION.STATE.INACTIVE)
			// Show toast message only when tab is visible on screen
			if (windowIsVisible.value) {
				showInfo(t('spreed', 'Session has been marked as inactive'))
			}
			console.info('Session has been marked as inactive')
		} catch (error) {
			console.error(error)
		}
	}

	const handleWindowFocus = ({ type }) => {
		if (type === 'focus') {
			setSessionAsActive()

			document.removeEventListener('mouseenter', handleMouseMove)
			document.removeEventListener('mouseleave', handleMouseMove)
		} else if (type === 'blur') {
			clearTimeout(inactiveTimer.value)
			inactiveTimer.value = setTimeout(() => {
				setSessionAsInactive()
			}, INACTIVE_TIME_MS)

			// Listen for mouse events to track activity on tab
			document.addEventListener('mouseenter', handleMouseMove)
			document.addEventListener('mouseleave', handleMouseMove)
		}
	}

	const handleMouseMove = (event) => {
		setSessionAsActive()
		// Restart timer, if mouse moves around the tab
		clearTimeout(inactiveTimer.value)
		inactiveTimer.value = setTimeout(() => {
			setSessionAsInactive()
		}, INACTIVE_TIME_MS)
	}

	return supportSessionState
}
