/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare module '@nextcloud/cdav-library'

declare class DavClient {
	constructor(options: { rootUrl: string })
	connect(options: { enableCalDAV: boolean }): Promise<void>

	currentUserPrincipal: DavPrincipal
	calendarHomes: DavCalendarHome[]
}
