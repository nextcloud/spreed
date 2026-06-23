/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { NotificationEvent } from '../../index.ts'

declare module '@nextcloud/event-bus' {
	import type { NextcloudUser } from '@nextcloud/auth'
	export interface NextcloudEvents {
		'user:info:changed': NextcloudUser
		'notifications:action:execute': NotificationEvent
		'notifications:notification:received': NotificationEvent
		// LeftSidebar > NcAppNavigation
		'toggle-navigation': { open: boolean }
		// MediaSettings
		'talk:media-settings:hide': void
		'talk:media-settings:show': void | 'video-verification' | 'device-check' | 'backgrounds'
		// ConversationSettingsDialog
		'show-conversation-settings': { token: string }
		'hide-conversation-settings': void
	}
}
export {}
