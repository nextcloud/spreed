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
 * We assume migration is done from now on, so we remove the migration flag
 * REMOVE: in stable 33+
 */
function migrateDirectLocalStorageToNextcloudBrowserStorage() {
	if (BrowserStorage.getItem('localStorageMigrated')) {
		BrowserStorage.removeItem('localStorageMigrated')
	}
}
/**
 * Clean up some deprecated (no longer in use) keys from @nextcloud/browser-storage
 * REMOVE: in stable 33+
 */
function cleanOutdatedBrowserStorageKeys() {
	const deprecatedKeys = [
		'showMediaSettings_', // Migration from conversation level to Talk level settings
		'devicesPreferred', // Migration to audioInputDevicePreferred|videoInputDevicePreferred
		'audioInputDevicePreferred', // Not needed anymore
		'videoInputDevicePreferred', // Not needed anymore
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
