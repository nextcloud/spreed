/*
 * @copyright Copyright (c) 2020 Vincent Petry <vincent@nextcloud.com>
 *
 * @author Vincent Petry <vincent@nextcloud.com>
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
 *
 */

import Vue from 'vue'

import { getRequestToken } from '@nextcloud/auth'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { translate, translatePlural } from '@nextcloud/l10n'
import { generateFilePath, generateUrl } from '@nextcloud/router'

import RoomSelector from './components/RoomSelector.vue'

import { postRichObjectToConversation } from './services/messagesService.js'

(function(OC, OCA, t, n) {
	/**
	 * @param {object} card The card object given by the deck app
	 * @param {string} token The conversation to post to
	 */
	async function postCardToRoom(card, token) {
		try {
			const response = await postRichObjectToConversation(token, {
				objectType: 'deck-card',
				objectId: card.id,
				metaData: JSON.stringify(card),
			})
			const messageId = response.data.ocs.data.id
			const targetUrl = generateUrl('/call/{token}#message_{messageId}', { token, messageId })
			showSuccess(t('spreed', 'Deck card has been posted to the selected <a href="{link}">conversation</a>', {
				link: targetUrl,
			}), {
				isHTML: true,
			})
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
		if (!OCA.Deck) {
			return
		}

		OCA.Deck.registerCardAction({
			label: t('spreed', 'Post to a conversation'),
			icon: 'icon-talk',
			callback: (card) => {
				const container = document.createElement('div')
				container.id = 'spreed-post-card-to-room-select'
				const body = document.getElementById('body-user')
				body.appendChild(container)

				const ComponentVM = Vue.extend(RoomSelector)
				const vm = new ComponentVM({
					el: container,
					propsData: {
						dialogTitle: t('spreed', 'Post to conversation'),
						showPostableOnly: true,
					},
				})

				vm.$root.$on('close', () => {
					vm.$el.remove()
					vm.$destroy()
				})
				vm.$root.$on('select', (token) => {
					vm.$el.remove()
					vm.$destroy()

					postCardToRoom(card, token)
				})
			},
		})
	}

	// CSP config for webpack dynamic chunk loading
	// eslint-disable-next-line
	// __webpack_nonce__ = btoa(getRequestToken())

	// Correct the root of the app for chunk loading
	// OC.linkTo matches the apps folders
	// OC.generateUrl ensure the index.php (or not)
	// We do not want the index.php since we're loading files
	// eslint-disable-next-line
//	__webpack_public_path__ = generateFilePath('spreed', '', 'js/')

	Vue.prototype.t = translate
	Vue.prototype.n = translatePlural
	Vue.prototype.OC = OC
	Vue.prototype.OCA = OCA

	document.addEventListener('DOMContentLoaded', init)

})(window.OC, window.OCA, t, n)
