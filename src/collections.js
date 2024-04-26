/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'

// eslint-disable-next-line no-unexpected-multiline
(function(OCP, OC) {

	// eslint-disable-next-line
	__webpack_nonce__ = btoa(OC.requestToken)
	// eslint-disable-next-line
	__webpack_public_path__ = OC.linkTo('spreed', 'js/')

	Vue.prototype.t = t
	Vue.prototype.n = n
	Vue.prototype.OC = OC

	OCP.Collaboration.registerType('room', {
		action: () => {
			return new Promise((resolve, reject) => {
				const container = document.createElement('div')
				container.id = 'spreed-room-select'
				const body = document.getElementById('body-user')
				body.appendChild(container)

				const RoomSelector = () => import('./components/RoomSelector.vue')
				const ComponentVM = new Vue({
					el: container,
					render: h => h(RoomSelector, {
						props: {
							// Even if it is used from Talk the Collections menu is
							// independently loaded, so the properties that depend
							// on the store need to be explicitly injected.
							container: window.store ? window.store.getters.getMainContainerSelector() : undefined,
							isPlugin: true,
						},
					}),
				})

				ComponentVM.$root.$on('close', () => {
					ComponentVM.$el.remove()
					ComponentVM.$destroy()
					reject(new Error('User cancelled resource selection'))
				})
				ComponentVM.$root.$on('select', ({ token }) => {
					resolve(token)
					ComponentVM.$el.remove()
					ComponentVM.$destroy()
				})
			})
		},
		typeString: t('spreed', 'Link to a conversation'),
		typeIconClass: 'icon-talk',
	})
})(window.OCP, window.OC)
