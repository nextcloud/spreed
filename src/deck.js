/*
 * @copyright Copyright (c) 2020 Vincent Petry <vincent@nextcloud.com>
 *
 * @author Vincent Petry <vincent@nextcloud.com>
 *
 * @license GNU AGPL version 3 or any later version
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
 *
 */

import Vue from 'vue'
import { generateFilePath, generateUrl } from '@nextcloud/router'
import { getRequestToken } from '@nextcloud/auth'
import { translate, translatePlural } from '@nextcloud/l10n'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { postRichObjectToConversation } from './services/messagesService'

// CSP config for webpack dynamic chunk loading
// eslint-disable-next-line
__webpack_nonce__ = btoa(getRequestToken())

// Correct the root of the app for chunk loading
// OC.linkTo matches the apps folders
// OC.generateUrl ensure the index.php (or not)
// We do not want the index.php since we're loading files
// eslint-disable-next-line
__webpack_public_path__ = generateFilePath('spreed', '', 'js/')

Vue.prototype.t = translate
Vue.prototype.n = translatePlural
Vue.prototype.OC = OC
Vue.prototype.OCA = OCA

document.addEventListener('DOMContentLoaded', function() {

	if (!window.OCA.Deck) {
		return
	}

	window.OCA.Deck.registerCardAction({
		label: t('spreed', 'Post to a conversation'),
		icon: 'icon-talk',
		callback: (card) => {
			OCP.Collaboration.trigger('room').then(async(token) => {
				try {
					const response = await postRichObjectToConversation(token, {
						objectType: 'deck-card',
						objectId: card.id,
						metaData: JSON.stringify(card),
					})
					const messageId = response.data.ocs.data.id
					const targetUrl = generateUrl('/call/{token}#message_{messageId}', { token, messageId })
					showSuccess(t('spreed', 'Deck card has been posted to the selected <a href="{link}">conversation</a>.', {
						link: targetUrl,
					}), {
						isHTML: true,
					})
				} catch (exception) {
					console.error('Error posting deck card to conversation', exception, exception.response?.status)
					showError(t('spreed', 'An error occurred while posting deck card to conversation.'))
				}
			})
		},
	})

})
