/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// The purpose of this file is to wrap the logic shared by the different talk
// entry points

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { reactive } from 'vue'
import { CALL, PARTICIPANT, VIRTUAL_BACKGROUND } from './constants.ts'
import BrowserStorage from './services/BrowserStorage.js'
import { EventBus } from './services/EventBus.ts'
import store from './store/index.js'
import { useIntegrationsStore } from './stores/integrations.js'
import pinia from './stores/pinia.ts'
import { useTokenStore } from './stores/token.ts'

import '@nextcloud/dialogs/style.css'

if (!window.OCA.Talk) {
	window.OCA.Talk = reactive({})
}

const integrationsStore = useIntegrationsStore(pinia)
const tokenStore = useTokenStore(pinia)

/**
 * Frontend message API for adding actions to talk messages.
 *
 * @param {object} data the wrapping object;
 * @param {string} data.label the action label.
 * @param {Function} data.callback the callback function. This function will receive
 * the messageAPIData object as a parameter and be triggered by a click on the
 * action.
 * @param {string} data.icon the action label. E.g. "icon-reply"
 */
window.OCA.Talk.registerMessageAction = ({ label, callback, icon }) => {
	const messageAction = {
		label,
		callback,
		icon,
	}
	integrationsStore.addMessageAction(messageAction)
}

window.OCA.Talk.registerParticipantSearchAction = ({ label, callback, show, icon }) => {
	const participantSearchAction = {
		label,
		callback,
		show,
		icon,
	}
	integrationsStore.addParticipantSearchAction(participantSearchAction)
}

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

/**
 * Migrate localStorage to @nextcloud/browser-storage
 *
 * In order to preserve the user settings while migrating to the abstraction,
 * we loop over the localStorage entries and add the matching ones to the
 * BrowserStorage
 */
const migrateDirectLocalStorageToNextcloudBrowserStorage = () => {
	if (BrowserStorage.getItem('localStorageMigrated') !== null) {
		return
	}

	const deprecatedKeys = [
		'audioDisabled_',
		'videoDisabled_',
		'virtualBackgroundEnabled_',
		'virtualBackgroundType_',
		'virtualBackgroundBlurStrength_',
		'virtualBackgroundUrl_',
	]

	Object.keys(localStorage).forEach((key) => {
		if (deprecatedKeys.some((deprecatedKey) => key.startsWith(deprecatedKey))) {
			console.debug('Migrating localStorage key to BrowserStorage: %s', key)
			BrowserStorage.setItem(key, localStorage.getItem(key))
			localStorage.removeItem(key)

			if (key.startsWith('virtualBackgroundEnabled_')) {
				// Before Talk 17 there was only a boolean
				// `virtualBackgroundEnabled_{token}` (stored as string).
				// Now we also need to have a type and the default type
				// is "none". So when migrating the data for
				// conversations the user had previously enabled the
				// background blur we also add the type with value blur.
				const typeKey = key.replace('virtualBackgroundEnabled_', 'virtualBackgroundType_')
				if (localStorage.getItem(typeKey) === null) {
					BrowserStorage.setItem(typeKey, VIRTUAL_BACKGROUND.BACKGROUND_TYPE.BLUR)
				}
			}
		}
	})

	BrowserStorage.setItem('localStorageMigrated', 'done')
}

/**
 * Clean up some deprecated (no longer in use) keys from @nextcloud/browser-storage
 */
function cleanOutdatedBrowserStorageKeys() {
	const deprecatedKeys = [
		'showMediaSettings_', // Migration from conversation level to Talk level settings
		'devicesPreferred', // Migration to audioInputDevicePreferred|videoInputDevicePreferred
	].map((key) => BrowserStorage.scopeKey(key)) // FIXME upstream: this is a private method

	Object.keys(localStorage).forEach((key) => {
		if (deprecatedKeys.some((deprecatedKey) => key.startsWith(deprecatedKey))) {
			localStorage.removeItem(key)
		}
	})
}

if (window.requestIdleCallback) {
	window.requestIdleCallback(() => {
		migrateDirectLocalStorageToNextcloudBrowserStorage()
		cleanOutdatedBrowserStorageKeys()
	})
} else {
	// Fallback for Safari
	migrateDirectLocalStorageToNextcloudBrowserStorage()
	cleanOutdatedBrowserStorageKeys()
}
