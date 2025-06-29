/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare module '@nextcloud/event-bus' {
	import type { NextcloudUser } from '@nextcloud/auth'
	export interface NextcloudEvents {
		'user:info:changed': NextcloudUser
	}
}
export {}
