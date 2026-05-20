/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { getCSPNonce } from '@nextcloud/auth'
import type { createApp, defineCustomElement } from 'vue'
import type { createStore } from 'vuex'
import type { SettingsAPI } from './services/SettingsAPI.ts'
import type MediaDevicesManager from './utils/webrtc/MediaDevicesManager.js'

type ExitFullscreen = typeof document.exitFullscreen
type RequestFullscreen = typeof document.documentElement.requestFullscreen

declare global {
	interface Document {
		webkitExitFullscreen: ExitFullscreen
	}

	interface HTMLElement {
		webkitRequestFullscreen: RequestFullscreen
	}

	interface Window {
		OCP: {
			AppConfig: {
				setValue: (app: string, key: string, value: string | number | boolean, options?: { success?: () => void, error?: () => void }) => void
			}
			Accessibility: {
				disableKeyboardShortcuts: () => boolean
			}
		}

		OC: {
			MimeType: {
				getIconUrl: (mimetype?: string) => string | undefined
			}
		}

		OCA: {
			Talk: {
				/** Vue app instance and optional destroyer */
				instance?: ReturnType<createApp>
				unmountInstance?: () => void
				newTab?: defineCustomElement

				/** Public API */
				Settings?: SettingsAPI

				/** Exposed internals */
				store?: ReturnType<createStore>
				mediaDevicesManager?: ReturnType<MediaDevicesManager>
				SimpleWebRTC?: undefined

				/** Added by Desktop client */
				Desktop?: IS_DESKTOP extends true
					? {
							getDesktopMediaSource?: () => void
						}
					: never

				/** Exposed functions */
				signalingGetSettingsForRecording?: () => void
				signalingJoinCallForRecording?: () => void
				signalingKill?: () => void
				registerMessageAction?: () => void
				registerParticipantSearchAction?: () => void
				gridDebugInformation?: () => void
				gridDevModeEnable?: () => void
			}

			/** Public API */
			Viewer: {
				open: () => void
				close: () => void
				mimetypes: string[]
				availableHandlers: Record<string, unknown>[]
			}
		}
	}

	const OCP: Window['OCP']
	const OC: Window['OC']
	const OCA: Window['OCA']

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
	const IS_DESKTOP: boolean

	let __webpack_nonce__: ReturnType<typeof getCSPNonce>
	let __webpack_public_path__: string
}
