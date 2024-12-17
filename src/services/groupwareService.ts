/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import type {
	OutOfOfficeResponse,
	UpcomingEventsResponse,
} from '../types/index.ts'

/**
 * Get upcoming events for a given conversation within the next 31 days.
 * @param location conversation's absolute URL
 */
const getUpcomingEvents = async (location: string): UpcomingEventsResponse => {
	return axios.get(generateOcsUrl('/apps/dav/api/v1/events/upcoming'), {
		params: {
			location,
		},
	})
}

/**
 * Get absence information for a user (in a given 1-1 conversation).
 * @param userId user id
 */
const getUserAbsence = async (userId: string): OutOfOfficeResponse => {
	return axios.get(generateOcsUrl('/apps/dav/api/v1/outOfOffice/{userId}/now', { userId }))
}

export {
	getUpcomingEvents,
	getUserAbsence,
}
