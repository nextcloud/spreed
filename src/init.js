/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// The purpose of this file is to wrap the logic shared by the different talk
// entry points

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import { CALL, PARTICIPANT, VIRTUAL_BACKGROUND } from './constants.ts'
import BrowserStorage from './services/BrowserStorage.js'
import { EventBus } from './services/EventBus.ts'
import store from './store/index.js'
import { useIntegrationsStore } from './stores/integrations.js'

import '@nextcloud/dialogs/style.css'

if (!window.OCA.Talk) {
	window.OCA.Talk = {}
}

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
	const integrationsStore = useIntegrationsStore()
	integrationsStore.addMessageAction(messageAction)
}

window.OCA.Talk.registerParticipantSearchAction = ({ label, callback, show, icon }) => {
	const participantSearchAction = {
		label,
		callback,
		show,
		icon,
	}
	const integrationsStore = useIntegrationsStore()
	integrationsStore.addParticipantSearchAction(participantSearchAction)
}

EventBus.on('signaling-join-room', (payload) => {
	const token = payload[0]
	store.dispatch('updateLastJoinedConversationToken', token)
})

EventBus.on('signaling-recording-status-changed', ([token, status]) => {
	store.dispatch('setConversationProperties', { token, properties: { callRecording: status } })

	if (status !== CALL.RECORDING.FAILED) {
		return
	}

	if (!store.getters.isInCall(store.getters.getToken())) {
		return
	}

	const conversation = store.getters.conversation(store.getters.getToken())
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

	const storageKeys = Array.from(Array(localStorage.length), (_, i) => localStorage.key(i)).filter((key) => key.startsWith('audioDisabled_')
		|| key.startsWith('videoDisabled_')
		|| key.startsWith('virtualBackgroundEnabled_')
		|| key.startsWith('virtualBackgroundType_')
		|| key.startsWith('virtualBackgroundBlurStrength_')
		|| key.startsWith('virtualBackgroundUrl_'),
	)

	if (storageKeys.length) {
		console.debug('Migrating localStorage keys to BrowserStorage', storageKeys)

		storageKeys.forEach((key) => {
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
		})
	}

	BrowserStorage.setItem('localStorageMigrated', 'done')
}

migrateDirectLocalStorageToNextcloudBrowserStorage()
