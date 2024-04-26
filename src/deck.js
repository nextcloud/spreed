/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import escapeHtml from 'escape-html'
import Vue from 'vue'

import { getRequestToken } from '@nextcloud/auth'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { translate, translatePlural } from '@nextcloud/l10n'
import { generateFilePath, generateUrl } from '@nextcloud/router'

import { postRichObjectToConversation } from './services/messagesService.ts'

import '@nextcloud/dialogs/style.css'

(function(OC, OCA, t, n) {
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

			showSuccess(t('spreed', 'Deck card has been posted to {conversation}')
				.replace(/\{conversation}/g, `<a target="_blank" class="external" href="${targetUrl}">${escapeHtml(displayName)} ↗</a>`),
			{
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

				const RoomSelector = () => import('./components/RoomSelector.vue')
				const vm = new Vue({
					el: container,
					render: h => h(RoomSelector, {
						props: {
							dialogTitle: t('spreed', 'Post to conversation'),
							showPostableOnly: true,
							isPlugin: true,
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

					postCardToRoom(card, conversation)
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
