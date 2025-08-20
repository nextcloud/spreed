/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare module '@nextcloud/cdav-library'

declare class DavClient {
	constructor(options: { rootUrl: string, defaultHeaders: Record<string, unknown> }, factories = {})
	connect(options?: { enableCalDAV?: boolean, enableCardDAV?: boolean }): Promise<void>

	currentUserPrincipal: DavPrincipal
	calendarHomes: DavCalendarHome[]
}
