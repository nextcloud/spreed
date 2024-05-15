/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp, defineAsyncComponent } from 'vue'
import { NextcloudGlobalsVuePlugin } from './NextcloudGlobalsVuePlugin.js'

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

		const app = createApp(RoomSelector, {
			isPlugin: true,
			...roomSelectorProps,
			onClose: () => {
				container.remove()
				app.unmount()
				resolve(null)
			},
			onSelect: (conversation) => {
				container.remove()
				app.unmount()
				resolve(conversation)
			},
		}).use(NextcloudGlobalsVuePlugin)
		app.mount(container)
	})
}
