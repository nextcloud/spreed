/*
 * @copyright Copyright (c) 2021 Julien Veyssier <julien@nextcloud.com>
 *
 * @author Julien Veyssier <julien@nextcloud.com>
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

import escapeHtml from 'escape-html'
import Vue from 'vue'

import { getRequestToken } from '@nextcloud/auth'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { translate, translatePlural } from '@nextcloud/l10n'
import { generateFilePath, generateUrl } from '@nextcloud/router'

import { postRichObjectToConversation } from './services/messagesService.js'

import '@nextcloud/dialogs/style.css'

(function(OC, OCA, t, n) {
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
		if (!OCA.Maps?.registerMapsAction) {
			return
		}

		OCA.Maps.registerMapsAction({
			label: t('spreed', 'Share to a conversation'),
			icon: 'icon-talk',
			callback: (location) => {
				const container = document.createElement('div')
				container.id = 'spreed-post-location-to-room-select'
				const body = document.getElementById('body-user')
				body.appendChild(container)

				const RoomSelector = () => import('./components/RoomSelector.vue')
				const vm = new Vue({
					el: container,
					render: h => h(RoomSelector, {
						props: {
							dialogTitle: t('spreed', 'Share to conversation'),
							showPostableOnly: true,
						},
					}),
				})

				vm.$root.$on('close', () => {
					vm.$el.remove()
					vm.$destroy()
				})
				vm.$root.$on('select', (conversation) => {
					vm.$el.remove()
					vm.$destroy()

					postLocationToRoom(location, conversation)
				})
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

	Vue.prototype.t = translate
	Vue.prototype.n = translatePlural
	Vue.prototype.OC = OC
	Vue.prototype.OCA = OCA

	document.addEventListener('DOMContentLoaded', init)

})(window.OC, window.OCA, t, n)
