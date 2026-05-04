/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** The purpose of this file is to wrap the logic shared by the different Talk entry points */

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { reactive } from 'vue'
import { CALL, PARTICIPANT } from './constants.ts'
import BrowserStorage from './services/BrowserStorage.js'
import { EventBus } from './services/EventBus.ts'
import { setTalkSessionUniqueTabIdHeader } from './services/talkSessionUniqueTabId.ts'
import store from './store/index.js'
import { useIntegrationsStore } from './stores/integrations.js'
import pinia from './stores/pinia.ts'
import { useTokenStore } from './stores/token.ts'
import { isSafari } from './utils/browserCheck.ts'

import '@nextcloud/dialogs/style.css'

let appInitialized = false
let storageMigrated = false

/**
 * Initializes the talk application surroundings. Skip if was initialized already.
 * Valid for Talk main/recording/public sidebars, that are not repeatedly mounted and destroyed on page.
 */
export function initializeTalkOnce() {
	if (!appInitialized) {
		initializeTalk()
	}
}

/**
 * Initializes the talk application surroundings.
 */
export function initializeTalk() {
	registerPublicApi()
	registerGlobalEvents()
	setTalkSessionUniqueTabIdHeader()
	if (!storageMigrated) {
		migrateBrowserStorageOnce()
	}
	appInitialized = true
}

/**
 * Ensures window.OCA.Talk object existing and registers public API for integrations
 */
function registerPublicApi() {
	if (!window.OCA.Talk) {
		window.OCA.Talk = reactive({})
	}

	/**
	 * Frontend message API for adding actions to talk messages.
	 *
	 * @param {object} data the wrapping object;
	 * @param {string} data.label the action label.
	 * @param {Function} data.callback the callback function. This function will receive
	 * the messageAPIData object as a parameter and be triggered by a click on the action.
	 * @param {string} data.icon the action label. E.g. "icon-reply"
	 */
	window.OCA.Talk.registerMessageAction = ({ label, callback, icon }) => {
		const messageAction = {
			label,
			callback,
			icon,
		}
		const integrationsStore = useIntegrationsStore(pinia)
		integrationsStore.addMessageAction(messageAction)
	}

	window.OCA.Talk.registerParticipantSearchAction = ({ label, callback, show, icon }) => {
		const participantSearchAction = {
			label,
			callback,
			show,
			icon,
		}
		const integrationsStore = useIntegrationsStore(pinia)
		integrationsStore.addParticipantSearchAction(participantSearchAction)
	}
}

/**
 * Register EventBus listeners for global events
 * FIXME extract to composable and integrate in apps
 */
function registerGlobalEvents() {
	const tokenStore = useTokenStore(pinia)

	EventBus.on('signaling-join-room', ([token]) => {
		tokenStore.updateLastJoinedConversationToken(token)
	})

	EventBus.on('signaling-recording-status-changed', ([token, status]) => {
		store.dispatch('setConversationProperties', { token, properties: { callRecording: status } })

		if (status !== CALL.RECORDING.FAILED) {
			return
		}

		if (!store.getters.isInCall(tokenStore.token)) {
			return
		}

		const conversation = store.getters.conversation(tokenStore.token)
		if (conversation?.participantType === PARTICIPANT.TYPE.OWNER
			|| conversation?.participantType === PARTICIPANT.TYPE.MODERATOR) {
			showError(t('spreed', 'The recording failed. Please contact your administrator.'))
		}
	})
}

/**
 * Clean up some deprecated (no longer in use) keys from @nextcloud/browser-storage
 */
function cleanOutdatedBrowserStorageKeys() {
	const deprecatedKeys = [
		'showMediaSettings_', // Migration from conversation level to Talk level settings
		'devicesPreferred', // Migration to audioInputDevicePreferred|videoInputDevicePreferred
		'audioInputDevicePreferred', // Not needed anymore
		'videoInputDevicePreferred', // Not needed anymore

		// Talk 33
		'virtualBackgroundBlurStrength_', // Migration from conversation level to Talk level settings
		'virtualBackgroundEnabled_', // Migration from conversation level to Talk level settings
		'virtualBackgroundType_', // Migration from conversation level to Talk level settings
		'virtualBackgroundUrl_', // Migration from conversation level to Talk level settings
	].map((key) => BrowserStorage.scopeKey(key)) // FIXME upstream: this is a private method

	Object.keys(localStorage).forEach((key) => {
		if (deprecatedKeys.some((deprecatedKey) => key.startsWith(deprecatedKey))) {
			localStorage.removeItem(key)
		}
	})

	if (isSafari) {
		BrowserStorage.removeItem('noiseSuppression') // Not supported by Safari browsers
		BrowserStorage.removeItem('autoGainControl') // Not supported by Safari browsers
	}
}

/**
 * Migrate and clean up outdated BrowserStorage keys. Skip if was initialized already.
 */
function migrateBrowserStorageOnce() {
	if (window.requestIdleCallback) {
		window.requestIdleCallback(() => {
			cleanOutdatedBrowserStorageKeys()
			storageMigrated = true
		})
	} else {
		// Fallback for Safari
		cleanOutdatedBrowserStorageKeys()
		storageMigrated = true
	}
}
