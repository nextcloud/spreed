/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import {createApp, defineAsyncComponent,} from 'vue'
import ConfirmDialog from './components/UIShared/ConfirmDialog.vue'
import { CONVERSATION } from './constants.ts'
import { createMemoryRouter } from './router/router.ts'
import { createConversation } from './services/conversationsService.ts'
import store from './store/index.js'
import pinia from './stores/pinia.ts'
import { NextcloudGlobalsVuePlugin } from './utils/NextcloudGlobalsVuePlugin.js'

/**
 * Prompt user to start a floating call in place or navigate to Talk app to call there
 *
 * @param url Intercepted URL (<...>/apps/spreed/?callUser=<userId>)
 */
export async function handleInterceptedLink(url: URL) {
	const response = await spawnDialog(ConfirmDialog, {
		name: 'Thumbnail',
		buttons: [
			{
				label: t('spreed', 'Continue in Talk app'),
				callback: () => 'navigate',
			},
			{
				label: t('spreed', 'Call right now!'),
				variant: 'primary',
				callback: () => 'integration',
			},
		],
	})

	if (response === 'integration') {
		await handleStartFloatingCall(url.searchParams.get('callUser')!)
	} else if (response === 'navigate') {
		// Navigate normally
		window.location.assign(url.href)
	} else {
		// dialog closed, no action needed
	}
}

/**
 * Prompt user to start a floating call in place or navigate to Talk app to call there
 *
 * @param userId Intercepted URL (<...>/apps/spreed/?callUser=<userId>)
 */
export async function handleStartFloatingCall(userId: string) {
	try {
		const conversationToken = (await createConversation({
			roomType: CONVERSATION.TYPE.ONE_TO_ONE,
			participants: { users: [userId] },
		})).data.ocs.data.token

		if (!window.OCA.Talk) {
			window.OCA.Talk = {}
		}

		const floatingCallContainer = document.createElement('div')
		floatingCallContainer.id = `talk-floating-call-${conversationToken}`
		document.body.appendChild(floatingCallContainer)
		const router = createMemoryRouter()

		const FloatingCallOverlay = defineAsyncComponent(() => import('./FloatingCallOverlay.vue'))
		const floatingApp = createApp(FloatingCallOverlay, {
			token: conversationToken,
		})
			.use(pinia)
			.use(store)
			.use(router)
			.use(NextcloudGlobalsVuePlugin)
		window.OCA.Talk.floatingApp = floatingApp
		window.OCA.Talk.unmountFloatingApp = function() {
			floatingApp.unmount()
			document.body.removeChild(floatingCallContainer)
			delete window.OCA.Talk.floatingApp
			delete window.OCA.Talk.unmountFloatingApp
		}

		floatingApp.mount(document.getElementById(`talk-floating-call-${conversationToken}`)!)
	} catch (error) {
		console.error(error)
	}
}
