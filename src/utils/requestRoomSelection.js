/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue, { defineAsyncComponent } from 'vue'

Vue.prototype.OC = window.OC
Vue.prototype.OCA = window.OCA
Vue.prototype.OCP = window.OCP

/**
 *
 * @param {string} containerId - id of the container to append the RoomSelector component to
 * @param {object} roomSelectorProps - props data to pass to RoomSelector component
 * @return {Promise<object|null>} - resolves with the conversation of the selected room or null if canceled
 */
export function requestRoomSelection(containerId, roomSelectorProps) {
	return new Promise((resolve) => {
		const container = document.createElement('div')
		container.id = containerId
		const body = document.getElementById('body-user')
		body.appendChild(container)

		const RoomSelector = defineAsyncComponent(() => import('../components/RoomSelector.vue'))

		const vm = new Vue({
			render: h => h(RoomSelector, {
				props: {
					isPlugin: true,
					...roomSelectorProps,
				},
			}),
		}).$mount(container)

		vm.$root.$on('close', () => {
			container.remove()
			vm.$destroy()
			resolve(null)
		})

		vm.$root.$on('select', (conversation) => {
			container.remove()
			vm.$destroy()
			resolve(conversation)
		})
	})
}
