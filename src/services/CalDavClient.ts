/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	DavCalendar,
	DavCalendarHome,
	DavPrincipal,
} from '../types/index.ts'

import DavClient from '@nextcloud/cdav-library'
import { generateRemoteUrl } from '@nextcloud/router'

/**
 * Copied from:
 * - https://github.com/nextcloud/calendar/blob/main/src/services/caldavService.js
 * - migrated to TypeScript
 */
const clients: Record<string, DavClient> = {}

const getClientKey = (headers: object) => JSON.stringify(headers)

/**
 *
 */
function getClient(headers: object = {}) {
	const clientKey = getClientKey(headers)
	if (clients[clientKey]) {
		return clients[clientKey]
	}

	clients[clientKey] = new DavClient({
		rootUrl: generateRemoteUrl('dav'),
		defaultHeaders: {
			'X-NC-CalDAV-Webcal-Caching': 'On',
		},
	})

	return clients[clientKey]
}

/**
 * Initializes the client for use in the user-view
 * If already connected, returns existing (see upstream)
 */
async function initializeCalDavClient() {
	await getClient().connect({ enableCalDAV: true })
}

/**
 * Returns the current User Principal
 */
function getDavCurrentUserPrincipal(): DavPrincipal {
	return getClient().currentUserPrincipal
}

/**
 * Returns calendar home
 *
 * @param headers optional request headers
 */
const getDavCalendarHome = (headers?: object): DavCalendarHome => getClient(headers).calendarHomes[0]

/**
 * Get personal calendars for a user
 */
async function getPersonalCalendars(): Promise<DavCalendar[]> {
	return getDavCalendarHome().findAllCalendars()
}

/**
 *
 */
function convertUrlToUri(url: string): string {
	return url.replace(/\/$/gi, '').split('/').pop() || url
}

/**
 *
 */
function getDefaultCalendarUri() {
	return convertUrlToUri(getDavCurrentUserPrincipal().scheduleDefaultCalendarUrl)
}

export {
	convertUrlToUri,
	getDefaultCalendarUri,
	getPersonalCalendars,
	initializeCalDavClient,
}
