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

// Augment models with the public methods added to their prototype by the
// EmitterMixin.
/* eslint-disable @typescript-eslint/no-explicit-any --
 * Arguments of function types are contravariant in strict mode, so the
 * "any" type is required here, as the "unknown" type would prevent
 * assigning a function type with narrower argument types.
 */
declare module './utils/webrtc/models/CallParticipantCollection.js' {
	interface CallParticipantCollection {
		on(event: string, handler: (callParticipantCollection: CallParticipantCollection, ...args: any[]) => void): void
		off(event: string, handler: (callParticipantCollection: CallParticipantCollection, ...args: any[]) => void): void
	}
}

declare module './utils/webrtc/models/CallParticipantModel.js' {
	interface CallParticipantModel {
		on(event: string, handler: (callParticipantModel: CallParticipantModel, ...args: any[]) => void): void
		off(event: string, handler: (callParticipantModel: CallParticipantModel, ...args: any[]) => void): void
	}
}

declare module './utils/webrtc/models/LocalCallParticipantModel.js' {
	interface LocalCallParticipantModel {
		on(event: string, handler: (localCallParticipantModel: LocalCallParticipantModel, ...args: any[]) => void): void
		off(event: string, handler: (localCallParticipantModel: LocalCallParticipantModel, ...args: any[]) => void): void
	}
}
/* eslint-enable @typescript-eslint/no-explicit-any */

export {}
