/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCSPNonce } from '@nextcloud/auth'
import { generateFilePath } from '@nextcloud/router'

// CSP config for webpack dynamic chunk loading
__webpack_nonce__ = getCSPNonce()

// Correct the root of the app for chunk loading
// OC.linkTo matches the apps folders
// OC.generateUrl ensure the index.php (or not)
// We do not want the index.php since we're loading files
__webpack_public_path__ = generateFilePath('spreed', '', 'js/')

document.addEventListener('click', (event) => {
	// ignore if already handled, not left button, or user wants modifier (open in new tab/window)
	if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
		return
	}

	const anchor = (event?.target as HTMLElement).closest('a')
	if (!anchor) {
		return
	}

	const href = anchor.getAttribute('href')
	if (!href || href.startsWith('#')) {
		return
	}

	const url = new URL(href, location.href)
	// ignore external links
	if (url.origin !== location.origin) {
		return
	}

	// ignore non-Talk links
	if (!url.href.includes('/apps/spreed/?callUser=')) {
		return
	}

	event.preventDefault() // stop browser navigation

	import('./floatingCall.ts').then((module) => {
		module.handleInterceptedLink(url)
			.catch((err) => {
				console.error('Link handler error', err)
			})
	})
})
