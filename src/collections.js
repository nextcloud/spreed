/**
 * @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import Vue from 'vue'

// eslint-disable-next-line no-unexpected-multiline
(function(OCP, OC) {

	// eslint-disable-next-line
	//__webpack_nonce__ = btoa(OC.requestToken)
	// eslint-disable-next-line
	// __webpack_public_path__ = OC.linkTo('spreed', 'js/')

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
					render: h => h(RoomSelector, {
						props: {
							// Even if it is used from Talk the Collections menu is
							// independently loaded, so the properties that depend
							// on the store need to be explicitly injected.
							container: window.store ? window.store.getters.getMainContainerSelector() : undefined,
						},
					}),
				})
				ComponentVM.$mount(container)
				ComponentVM.$root.$on('close', () => {
					ComponentVM.$el.remove()
					ComponentVM.$destroy()
					reject(new Error('User cancelled resource selection'))
				})
				ComponentVM.$root.$on('select', (id) => {
					resolve(id)
					ComponentVM.$el.remove()
					ComponentVM.$destroy()
				})
			})
		},
		typeString: t('spreed', 'Link to a conversation'),
		typeIconClass: 'icon-talk',
	})
})(window.OCP, window.OC)
