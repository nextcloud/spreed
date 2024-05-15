/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import escapeHtml from 'escape-html'

import { getRequestToken } from '@nextcloud/auth'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { generateFilePath, generateUrl } from '@nextcloud/router'

import { postRichObjectToConversation } from './services/messagesService.ts'
import { requestRoomSelection } from './utils/requestRoomSelection.js'

import '@nextcloud/dialogs/style.css'

/**
 * @param {object} location Geo location object
 * @param {object} conversation The conversation object given by the RoomSelector
 * @param {string} conversation.token The conversation token
 * @param {string} conversation.displayName The conversation display name
 */
async function postLocationToRoom(location, { token, displayName }) {
	try {
		const response = await postRichObjectToConversation(token, {
			objectType: 'geo-location',
			objectId: location.id,
			metaData: JSON.stringify(location),
		})
		const messageId = response.data.ocs.data.id
		const targetUrl = generateUrl('/call/{token}#message_{messageId}', { token, messageId })

		showSuccess(t('spreed', 'Location has been posted to {conversation}')
			.replace(/\{conversation}/g, `<a target="_blank" class="external" href="${targetUrl}">${escapeHtml(displayName)} ↗</a>`),
		{
			isHTML: true,
		})
	} catch (exception) {
		console.error('Error posting location to conversation', exception, exception.response?.status)
		if (exception.response?.status === 403) {
			showError(t('spreed', 'No permission to post messages in this conversation'))
		} else {
			showError(t('spreed', 'An error occurred while posting location to conversation'))
		}
	}
}

/**
 * Initialise the maps action
 */
function init() {
	if (!window.OCA.Maps?.registerMapsAction) {
		return
	}

	window.OCA.Maps.registerMapsAction({
		label: t('spreed', 'Share to a conversation'),
		icon: 'icon-talk',
		callback: async (location) => {
			const conversation = await requestRoomSelection('spreed-post-location-to-room-select', {
				dialogTitle: t('spreed', 'Share to conversation'),
				showPostableOnly: true,
			})

			postLocationToRoom(location, conversation)
		},
	})
}

// CSP config for webpack dynamic chunk loading
// eslint-disable-next-line
__webpack_nonce__ = btoa(getRequestToken())

// Correct the root of the app for chunk loading
// OC.linkTo matches the apps folders
// OC.generateUrl ensure the index.php (or not)
// We do not want the index.php since we're loading files
// eslint-disable-next-line
__webpack_public_path__ = generateFilePath('spreed', '', 'js/')

document.addEventListener('DOMContentLoaded', init)
