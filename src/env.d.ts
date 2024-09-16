/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

type ExitFullscreen = typeof document.exitFullscreen
type RequestFullscreen = typeof document.documentElement.requestFullscreen

declare global {
	interface Document {
		webkitExitFullscreen: ExitFullscreen;
	}

	interface HTMLElement {
		webkitRequestFullscreen: RequestFullscreen;
	}

	// @nextcloud/webpack-vue-config build globals
	const appName: string
	const appVersion: string

	/**
	 * Build constant to divide build for web app and desktop client
	 */
	const IS_DESKTOP: false

	let __webpack_nonce__: ReturnType<typeof btoa>
	let __webpack_public_path__: string
}

export {}
