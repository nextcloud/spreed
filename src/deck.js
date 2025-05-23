/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getRequestToken } from '@nextcloud/auth'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { generateFilePath, generateUrl } from '@nextcloud/router'
import escapeHtml from 'escape-html'
import { postRichObjectToConversation } from './services/messagesService.ts'
import { requestRoomSelection } from './utils/requestRoomSelection.js'

import '@nextcloud/dialogs/style.css'

/**
 * @param {object} card The card object given by the deck app
 * @param {object} conversation The conversation object given by the RoomSelector
 * @param {string} conversation.token The conversation token
 * @param {string} conversation.displayName The conversation display name
 */
async function postCardToRoom(card, { token, displayName }) {
	try {
		const response = await postRichObjectToConversation(token, {
			objectType: 'deck-card',
			objectId: card.id,
			metaData: JSON.stringify(card),
		})

		const messageId = response.data.ocs.data.id
		const targetUrl = generateUrl('/call/{token}#message_{messageId}', { token, messageId })

		showSuccess(
			t('spreed', 'Deck card has been posted to {conversation}')
				.replace(/\{conversation}/g, `<a target="_blank" class="external" href="${targetUrl}">${escapeHtml(displayName)} ↗</a>`),
			{
				isHTML: true,
			},
		)
	} catch (exception) {
		console.error('Error posting deck card to conversation', exception, exception.response?.status)
		if (exception.response?.status === 403) {
			showError(t('spreed', 'No permission to post messages in this conversation'))
		} else {
			showError(t('spreed', 'An error occurred while posting deck card to conversation'))
		}
	}
}

/**
	*
	*/
function init() {
	if (!window.OCA.Deck) {
		return
	}

	window.OCA.Deck.registerCardAction({
		label: t('spreed', 'Post to a conversation'),
		icon: 'icon-talk',
		callback: async (card) => {
			const conversation = await requestRoomSelection('spreed-post-card-to-room-select', {
				dialogTitle: t('spreed', 'Post to conversation'),
				showPostableOnly: true,
			})
			if (conversation) {
				postCardToRoom(card, conversation)
			}
		},
	})
}

// CSP config for webpack dynamic chunk loading
__webpack_nonce__ = btoa(getRequestToken())

// Correct the root of the app for chunk loading
// OC.linkTo matches the apps folders
// OC.generateUrl ensure the index.php (or not)
// We do not want the index.php since we're loading files
__webpack_public_path__ = generateFilePath('spreed', '', 'js/')

document.addEventListener('DOMContentLoaded', init)
