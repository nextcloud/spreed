/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	DavCalendar,
	DavCalendarHome,
	DavPrincipal,
} from '../types/index.ts'

import { getRequestToken } from '@nextcloud/auth'
import DavClient from '@nextcloud/cdav-library'
import { generateRemoteUrl } from '@nextcloud/router'

/**
 * Copied from:
 * - https://github.com/nextcloud/calendar/blob/main/src/services/caldavService.js
 * - https://github.com/nextcloud/server/blob/master/core/src/OC/xhr-error.js
 * Modified for usage in Talk and Talk Desktop:
 * - migrated to TypeScript
 * - removed jQuery dependency
 * - dropped Ajax Error processing via OC.registerXHRForErrorProcessing
 */
const clients: Record<string, DavClient> = {}

const getClientKey = (headers: object) => JSON.stringify(headers)

const getClient = (headers: object = {}) => {
	const clientKey = getClientKey(headers)
	if (clients[clientKey]) {
		return clients[clientKey]
	}

	clients[clientKey] = new DavClient({
		rootUrl: generateRemoteUrl('dav'),
	}, () => {
		const mergedHeaders: Record<string, string> = {
			'X-Requested-With': 'XMLHttpRequest',
			requesttoken: getRequestToken() as string,
			'X-NC-CalDAV-Webcal-Caching': 'On',
			...headers,
		}
		const xhr = new XMLHttpRequest()
		const oldOpen = xhr.open

		// override open() method to add headers
		xhr.open = function() {
			// @ts-expect-error: Vue: Argument of type IArguments is not assignable to parameter of type
			// eslint-disable-next-line prefer-rest-params
			const result = oldOpen.apply(this, arguments)
			for (const name in mergedHeaders) {
				xhr.setRequestHeader(name, mergedHeaders[name])
			}

			return result
		}

		xhr.onload = (event: ProgressEvent) => {
			if (xhr.readyState !== 4) {
				return
			} else if ((xhr.status >= 200 && xhr.status < 300) || xhr.status === 304) {
				return
			}
			console.error(event)
		}
		xhr.onerror = (event: ProgressEvent) => {
			console.error(event)
		}
		return xhr
	})

	return clients[clientKey]
}

/**
 * Initializes the client for use in the user-view
 * If already connected, returns existing (see upstream)
 */
const initializeCalDavClient = async () => {
	await getClient().connect({ enableCalDAV: true })
}

/**
 * Returns the current User Principal
 */
const getDavCurrentUserPrincipal = (): DavPrincipal => {
	return getClient().currentUserPrincipal
}

/**
 * Returns calendar home
 * @param headers optional request headers
 */
const getDavCalendarHome = (headers?: object): DavCalendarHome => getClient(headers).calendarHomes[0]

/**
 * Get personal calendars for a user
 */
const getPersonalCalendars = async function(): Promise<DavCalendar[]> {
	return getDavCalendarHome().findAllCalendars()
}

const convertUrlToUri = (url: string): string => {
	return url.replace(/\/$/gi, '').split('/').pop() || url
}

const getDefaultCalendarUri = () => {
	return convertUrlToUri(getDavCurrentUserPrincipal().scheduleDefaultCalendarUrl)
}

export {
	convertUrlToUri,
	getDefaultCalendarUri,
	getPersonalCalendars,
	initializeCalDavClient,
}
