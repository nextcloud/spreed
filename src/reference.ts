/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { App } from 'vue'
import type { TalkReferenceRichObject } from './types/index.ts'

import { getCSPNonce } from '@nextcloud/auth'
import { generateFilePath } from '@nextcloud/router'
import { registerWidget } from '@nextcloud/vue/functions/reference'
import { createApp, defineAsyncComponent } from 'vue'

// CSP config for webpack dynamic chunk loading
__webpack_nonce__ = getCSPNonce()

// Correct the root of the app for chunk loading
// OC.linkTo matches the apps folders
// OC.generateUrl ensure the index.php (or not)
// We do not want the index.php since we're loading files
__webpack_public_path__ = generateFilePath('spreed', '', 'js/')

const CallReferenceWidget = defineAsyncComponent(() => import('./components/ReferenceWidgets/CallReferenceWidget.vue'))

// Track mounted widget instances per host element so the destroy callback can unmount them
const widgetApps = new WeakMap<HTMLElement, App>()

registerWidget('call', (el, { richObject, accessible, openGraphObject }) => {
	const app = createApp(CallReferenceWidget, {
		richObject: richObject as unknown as TalkReferenceRichObject,
		accessible,
		fallbackAvatarUrl: openGraphObject?.thumb ?? null,
	})
	widgetApps.set(el, app)
	app.mount(el)
}, (el) => {
	widgetApps.get(el)?.unmount()
	widgetApps.delete(el)
}, {
	hasInteractiveView: false,
	fullWidth: false,
	isResizable: false,
})
