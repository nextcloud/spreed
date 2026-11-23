/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { App } from 'vue'

import { getCSPNonce } from '@nextcloud/auth'
import { generateFilePath } from '@nextcloud/router'
import { registerWidget } from '@nextcloud/vue/functions/reference'
import { routeLocationKey, routerKey } from 'vue-router'

if (!IS_DESKTOP) {
	// CSP config for webpack dynamic chunk loading
	__webpack_nonce__ = getCSPNonce()

	// Correct the root of the app for chunk loading
	__webpack_public_path__ = generateFilePath('spreed', '', 'js/')
}

const apps = new WeakMap<Element, App>()

registerWidget('call', async (el, { richObject, accessible }) => {
	const { createApp } = await import('vue')
	const { default: TalkReferenceWidget } = await import('./TalkReferenceWidget.vue')

	const app = createApp(TalkReferenceWidget, { richObject, accessible })
	// Talk page: reuse its router, so the card navigates without a page reload
	const talkRouter = window.OCA.Talk?.instance?.$router
	if (talkRouter) {
		app.provide(routerKey, talkRouter)
		app.provide(routeLocationKey, talkRouter.currentRoute.value)
	}
	app.mount(el)
	apps.set(el, app)
}, (el) => {
	apps.get(el)?.unmount()
	apps.delete(el)
}, { hasInteractiveView: false })
