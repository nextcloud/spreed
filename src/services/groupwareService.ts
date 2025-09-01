/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { AxiosRequestConfig } from '@nextcloud/axios'
import type {
	getMutualEventsResponse,
	OutOfOfficeResponse,
	scheduleMeetingParams,
	scheduleMeetingResponse,
	UpcomingEventsResponse,
} from '../types/index.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Get upcoming events for a given conversation within the next 31 days.
 * @param location conversation's absolute URL
 */
async function getUpcomingEvents(location: string): UpcomingEventsResponse {
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
async function getUserAbsence(userId: string): OutOfOfficeResponse {
	return axios.get(generateOcsUrl('/apps/dav/api/v1/outOfOffice/{userId}/now', { userId }))
}

/**
 * Get information about mutual events for a given 1-1 conversation.
 *
 * @param token The conversation token
 */
async function getMutualEvents(token: string): getMutualEventsResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v4/room/{token}/mutual-events', { token }))
}

/**
 * Schedule a new meeting for a given conversation.
 *
 * @param token The conversation token
 * @param payload Function payload
 * @param payload.calendarUri Last part of the calendar URI as seen by the participant
 * @param payload.start Unix timestamp when the meeting starts
 * @param payload.end Unix timestamp when the meeting ends, falls back to 60 minutes after start
 * @param payload.title Title or summary of the event, falling back to the conversation name if none is given
 * @param payload.description Description of the event, falling back to the conversation description if none is given
 * @param payload.attendeeIds List of attendee ids to invite (null - everyone, [] - only actor)
 * @param [options] Axios request options
 */
async function scheduleMeeting(token: string, { calendarUri, start, end, title, description, attendeeIds }: scheduleMeetingParams, options?: AxiosRequestConfig): scheduleMeetingResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/meeting', { token }), {
		calendarUri,
		start,
		end,
		title,
		description,
		attendeeIds,
	} as scheduleMeetingParams, options)
}

export {
	getMutualEvents,
	getUpcomingEvents,
	getUserAbsence,
	scheduleMeeting,
}
