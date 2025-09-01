import type {
	broadcastChatMessageParams,
	broadcastChatMessageResponse,
	configureBreakoutRoomsParams,
	configureBreakoutRoomsResponse,
	deleteBreakoutRoomsResponse,
	getBreakoutRoomsParticipantsResponse,
	getBreakoutRoomsResponse,
	reorganizeAttendeesParams,
	reorganizeAttendeesResponse,
	requestAssistanceResponse,
	resetRequestAssistanceResponse,
	startBreakoutRoomsResponse,
	stopBreakoutRoomsResponse,
	switchToBreakoutRoomParams,
	switchToBreakoutRoomResponse,
} from '../types/index.ts'

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Create breakout rooms for a given conversation
 *
 * @param token The conversation token
 * @param mode Either manual, auto, or free // TODO use enums
 * @see CONVERSATION.BREAKOUT_ROOM_MODE
 *
 * @param amount The amount of breakout rooms to be created
 * @param attendeeMap A JSON-encoded map of attendeeId => room number (0 based)
 * (Only considered when the mode is "manual")
 */
async function configureBreakoutRooms(token: string, mode: 0 | 1 | 2 | 3, amount: number, attendeeMap?: string): configureBreakoutRoomsResponse {
	return axios.post(generateOcsUrl('/apps/spreed/api/v1/breakout-rooms/{token}', { token }), {
		mode,
		amount,
		attendeeMap,
	} as configureBreakoutRoomsParams)
}

/**
 * Apply new attendee map for breakout rooms in given conversation
 *
 * @param token the breakout room token
 * @param attendeeMap A JSON-encoded map of attendeeId => room number (0 based)
 */
async function reorganizeAttendees(token: string, attendeeMap: string): reorganizeAttendeesResponse {
	return axios.post(generateOcsUrl('/apps/spreed/api/v1/breakout-rooms/{token}/attendees', { token }), {
		attendeeMap,
	} as reorganizeAttendeesParams)
}

/**
 * Deletes all breakout rooms for a given conversation
 *
 * @param token The conversation token
 */
async function deleteBreakoutRooms(token: string): deleteBreakoutRoomsResponse {
	return axios.delete(generateOcsUrl('/apps/spreed/api/v1/breakout-rooms/{token}', { token }))
}

/**
 * Fetches the breakout rooms for given conversation
 *
 * @param token The conversation token
 */
async function getBreakoutRooms(token: string): getBreakoutRoomsResponse {
	return axios.get(generateOcsUrl('/apps/spreed/api/v4/room/{token}/breakout-rooms', { token }))
}

/**
 * @param token The conversation token
 */
async function startBreakoutRooms(token: string): startBreakoutRoomsResponse {
	return axios.post(generateOcsUrl('/apps/spreed/api/v1/breakout-rooms/{token}/rooms', { token }))
}

/**
 * @param token The conversation token
 */
async function stopBreakoutRooms(token: string): stopBreakoutRoomsResponse {
	return axios.delete(generateOcsUrl('/apps/spreed/api/v1/breakout-rooms/{token}/rooms', { token }))
}

/**
 * @param token the conversation token
 * @param message The message to be posted
 */
async function broadcastMessageToBreakoutRooms(token: string, message: string): broadcastChatMessageResponse {
	return axios.post(generateOcsUrl('/apps/spreed/api/v1/breakout-rooms/{token}/broadcast', { token }), {
		message,
	} as broadcastChatMessageParams)
}

/**
 * @param token the conversation token
 */
async function fetchBreakoutRoomsParticipants(token: string): getBreakoutRoomsParticipantsResponse {
	return axios.get(generateOcsUrl('/apps/spreed/api/v4/room/{token}/breakout-rooms/participants', { token }))
}

/**
 * Requests assistance from a moderator
 *
 * @param token the breakout room token
 */
async function requestAssistance(token: string): requestAssistanceResponse {
	return axios.post(generateOcsUrl('/apps/spreed/api/v1/breakout-rooms/{token}/request-assistance', { token }))
}

/**
 * Resets the request assistance
 *
 * @param token the breakout room token
 */
async function dismissRequestAssistance(token: string): resetRequestAssistanceResponse {
	return axios.delete(generateOcsUrl('/apps/spreed/api/v1/breakout-rooms/{token}/request-assistance', { token }))
}

/**
 * This endpoint allows participants to switch between breakout rooms when they are allowed to choose the breakout room
 * and not are automatically or manually assigned by the moderator.
 *
 * @param token Conversation token of the parent room hosting the breakout rooms
 * @param target Conversation token of the target breakout room
 */
async function switchToBreakoutRoom(token: string, target: string): switchToBreakoutRoomResponse {
	return axios.post(generateOcsUrl('/apps/spreed/api/v1/breakout-rooms/{token}/switch', { token }), {
		target,
	} as switchToBreakoutRoomParams)
}

export {
	broadcastMessageToBreakoutRooms,
	configureBreakoutRooms,
	deleteBreakoutRooms,
	dismissRequestAssistance,
	fetchBreakoutRoomsParticipants,
	getBreakoutRooms,
	reorganizeAttendees,
	requestAssistance,
	startBreakoutRooms,
	stopBreakoutRooms,
	switchToBreakoutRoom,
}
