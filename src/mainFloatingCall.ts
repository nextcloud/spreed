/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Conversation } from './types/index.ts'

import { createApp, defineAsyncComponent, reactive } from 'vue'
import { CONVERSATION } from './constants.ts'
import { createMemoryRouter } from './router/router.ts'
import { createConversation } from './services/conversationsService.ts'
import store from './store/index.js'
import pinia from './stores/pinia.ts'
import { NextcloudGlobalsVuePlugin } from './utils/NextcloudGlobalsVuePlugin.js'

import './init.js'

/**
 * Initializes the floating container on the page
 *
 * @param url Intercepted URL. One of:
 *            (<...>/apps/spreed/?callUser=<userId>#direct-call)
 */
export async function handleStartFloatingCall(url: URL) {
	try {
		const userId = url.searchParams.get('callUser')!

		const conversation: Conversation = (await createConversation({
			roomType: CONVERSATION.TYPE.ONE_TO_ONE,
			participants: { users: [userId] },
		})).data.ocs.data

		store.dispatch('addConversation', conversation)
		const token = conversation.token

		if (!window.OCA.Talk) {
			window.OCA.Talk = reactive({})
		}

		const floatingCallContainer = document.createElement('div')
		floatingCallContainer.id = `talk-floating-call-${token}`
		document.body.appendChild(floatingCallContainer)
		const router = createMemoryRouter()

		const FloatingCallOverlay = defineAsyncComponent(() => import('./FloatingCallOverlay.vue'))
		const instance = createApp(FloatingCallOverlay, {
			token,
		})
			.use(pinia)
			.use(store)
			.use(router)
			.use(NextcloudGlobalsVuePlugin)
		window.OCA.Talk.instance = instance
		window.OCA.Talk.unmountInstance = function() {
			instance.unmount()
			document.body.removeChild(floatingCallContainer)
			delete window.OCA.Talk.instance
			delete window.OCA.Talk.unmountInstance
			// @ts-expect-error: The operand of a delete operator must be optional
			delete window.OCA.Talk
		}

		instance.mount(document.getElementById(`talk-floating-call-${token}`)!)
	} catch (error) {
		console.error(error)
	}
}
