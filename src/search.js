/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'

import { getRequestToken } from '@nextcloud/auth'
import { emit } from '@nextcloud/event-bus'
import { translate, translatePlural } from '@nextcloud/l10n'
import { generateFilePath, imagePath } from '@nextcloud/router'

import '@nextcloud/dialogs/style.css'

(function(OC, OCA, t, n) {

	/**
	 *
	 */
	function init() {
		if (!OCA.UnifiedSearch) {
			return
		}
		console.debug('Initializing unified search plugin-filters from talk')
		OCA.UnifiedSearch.registerFilterAction({
			id: 'talk-message',
			appId: 'spreed',
			label: t('spreed', 'In conversation'),
			icon: imagePath('spreed', 'app.svg'),
			callback: () => {
				const container = document.createElement('div')
				container.id = 'spreed-unified-search-conversation-select'
				const body = document.getElementById('body-user')
				body.appendChild(container)

				const RoomSelector = () => import('./components/RoomSelector.vue')
				const vm = new Vue({
					el: container,
					render: h => h(RoomSelector, {
						props: {
							dialogTitle: t('spreed', 'Select conversation'),
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

					emit('nextcloud:unified-search:add-filter', {
						id: 'talk-message',
						payload: conversation,
						filterUpdateText: t('spreed', 'Search in conversation: {conversation}', { conversation: conversation.displayName }),
						filterParams: { conversation: conversation.token }
					})
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
