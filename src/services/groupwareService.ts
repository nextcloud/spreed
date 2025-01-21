/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import type {
	OutOfOfficeResponse,
	UpcomingEventsResponse,
	scheduleMeetingParams,
	scheduleMeetingResponse,
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
 * @param options options object destructured
 */
const scheduleMeeting = async function(token: string, { calendarUri, start, end, title, description, attendeeIds }: scheduleMeetingParams, options?: object): scheduleMeetingResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/meeting', { token }, options), {
		calendarUri,
		start,
		end,
		title,
		description,
		attendeeIds,
	} as scheduleMeetingParams, options)
}

export {
	getUpcomingEvents,
	getUserAbsence,
	scheduleMeeting,
}