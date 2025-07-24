/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { getCSPNonce } from '@nextcloud/auth'

type ExitFullscreen = typeof document.exitFullscreen
type RequestFullscreen = typeof document.documentElement.requestFullscreen

declare global {
	interface Document {
		webkitExitFullscreen: ExitFullscreen
	}

	interface HTMLElement {
		webkitRequestFullscreen: RequestFullscreen
	}

	const OCP: {
		AppConfig: {
			setValue: (app: string, key: string, value: string | number | boolean, options?: { success?: () => void, error?: () => void }) => void
		}
		Accessibility: {
			disableKeyboardShortcuts: () => boolean
		}
	}

	const OC: {
		MimeType: {
			getIconUrl: (mimetype?: string) => string | undefined
		}
	}

	declare module '*.svg?raw' {
		const content: string
		export default content
	}

	// @nextcloud/webpack-vue-config build globals
	const appName: string
	const appVersion: string

	/**
	 * Build constant to divide build for web app and desktop client
	 */
	const IS_DESKTOP: false

	let __webpack_nonce__: ReturnType<typeof getCSPNonce>
	let __webpack_public_path__: string
}

export {}
