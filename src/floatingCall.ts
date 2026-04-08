/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { UserProfileData } from './types/index.ts'

import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import { createApp, defineAsyncComponent, reactive } from 'vue'
import FloatingCallDialog from './components/FloatingCall/FloatingCallDialog.vue'
import { CONVERSATION } from './constants.ts'
import { createMemoryRouter } from './router/router.ts'
import { createConversation } from './services/conversationsService.ts'
import { getUserProfile } from './services/coreService.ts'
import store from './store/index.js'
import pinia from './stores/pinia.ts'
import { NextcloudGlobalsVuePlugin } from './utils/NextcloudGlobalsVuePlugin.js'

/**
 * Prompt the user to start a floating call in place or navigate to the app
 *
 * @param url Intercepted URL (<...>/apps/spreed/?callUser=<userId>)
 */
export async function handleInterceptedLink(url: URL) {
	const userId = url.searchParams.get('callUser')!

	let profile: UserProfileData | undefined
	try {
		profile = (await getUserProfile(userId)).data.ocs.data
	} catch (e) {
		console.warn('Could not fetch profile for', userId, e)
	}

	const response = await spawnDialog(FloatingCallDialog, {
		userId,
		displayName: profile?.displayname ?? undefined,
		role: profile?.role ?? undefined,
		organisation: profile?.organisation ?? undefined,
		timezone: profile?.timezone ?? undefined,
	})

	if (response === 'integration') {
		await handleStartFloatingCall(userId)
	} else if (response === 'navigate') {
		// Navigate normally
		window.location.assign(url.href)
	} else {
		// dialog closed, no action needed
	}
}

/**
 * Initializes the floating container on the page
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
			window.OCA.Talk = reactive({})
		}

		const floatingCallContainer = document.createElement('div')
		floatingCallContainer.id = `talk-floating-call-${conversationToken}`
		document.body.appendChild(floatingCallContainer)
		const router = createMemoryRouter()

		const FloatingCallOverlay = defineAsyncComponent(() => import('./FloatingCallOverlay.vue'))
		const instance = createApp(FloatingCallOverlay, {
			token: conversationToken,
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
		}

		instance.mount(document.getElementById(`talk-floating-call-${conversationToken}`)!)
	} catch (error) {
		console.error(error)
	}
}
