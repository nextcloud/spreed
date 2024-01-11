/**
 * @copyright Copyright (c) 2022 Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @author Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Create breakout rooms for a given conversation
 *
 * @param {string} token The conversation token
 * @param {string} mode Either manual, auto, or free, see constants file
 * @param {number} amount The amount of breakout rooms to be created
 * @param {string} attendeeMap A json encoded Map of attendeeId => room number (0 based)
 * (Only considered when the mode is "manual")
 * @return {Promise<import('axios').AxiosResponse<any>>}
 */
const configureBreakoutRooms = async function(token, mode, amount, attendeeMap) {
	return await axios.post(generateOcsUrl('/apps/spreed/api/v1/breakout-rooms/{token}', { token }), {
		mode,
		amount,
		attendeeMap,
	})
}

/**
 * Resets the request assistance
 *
 * @param {string} token the breakout room token
 * @param {string} attendeeMap A json encoded Map of attendeeId => room number (0 based)
 * @return {Promise<import('axios').AxiosResponse<any>>} The array of conversations
 */
const reorganizeAttendees = async function(token, attendeeMap) {
	return await axios.post(generateOcsUrl('/apps/spreed/api/v1/breakout-rooms/{token}/attendees', {
		token,
	}), {
		attendeeMap,
	}
	)
}

/**
 * Deletes all breakout rooms for a given conversation
 *
 * @param {string} token The conversation token
 * @return {Promise<import('axios').AxiosResponse<any>>}
 */
const deleteBreakoutRooms = async function(token) {
	return await axios.delete(generateOcsUrl('/apps/spreed/api/v1/breakout-rooms/{token}', { token }))
}

/**
 * Fetches the breakout rooms for given conversation
 *
 * @param {string} token The conversation token
 * @return {Promise<import('axios').AxiosResponse<any>>} The array of conversations
 */
const getBreakoutRooms = async function(token) {
	return await axios.get(generateOcsUrl('/apps/spreed/api/v4/room/{token}/breakout-rooms', { token }))
}

/**
 *
 * @param {string} token The conversation token
 * @return {Promise<import('axios').AxiosResponse<any>>} The array of conversations
 */
const startBreakoutRooms = async function(token) {
	return await axios.post(generateOcsUrl('/apps/spreed/api/v1/breakout-rooms/{token}/rooms', { token }))
}

/**
 * Stops the breakout rooms
 *
 * @param {string} token The conversation token
 * @return {Promise<import('axios').AxiosResponse<any>>} The array of conversations
 */
const stopBreakoutRooms = async function(token) {
	return await axios.delete(generateOcsUrl('/apps/spreed/api/v1/breakout-rooms/{token}/rooms', { token }))
}

/**
 * @param {string} token the conversation token
 * @param {string} message The message to be posted
 * @return {Promise<import('axios').AxiosResponse<any>>} The array of conversations
 */
const broadcastMessageToBreakoutRooms = async function(token, message) {
	return await axios.post(generateOcsUrl('/apps/spreed/api/v1/breakout-rooms/{token}/broadcast', {
		token,
	}), {
		message,
		token,
	})
}

/**
 *
 * @param {string} token the conversation token
 * @return {Promise<import('axios').AxiosResponse<any>>} The array of conversations
 */
const getBreakoutRoomsParticipants = async function(token) {
	return await axios.get(generateOcsUrl('/apps/spreed/api/v4/room/{token}/breakout-rooms/participants', {
		token,
	}))
}

/**
 * Requests assistance from a moderator
 *
 * @param {string} token the breakout room token
 * @return {Promise<import('axios').AxiosResponse<any>>} The array of conversations
 */
const requestAssistance = async function(token) {
	return await axios.post(generateOcsUrl('/apps/spreed/api/v1/breakout-rooms/{token}/request-assistance', {
		token,
	})
	)
}

/**
 * Resets the request assistance
 *
 * @param {string} token the breakout room token
 * @return {Promise<import('axios').AxiosResponse<any>>} The array of conversations
 */
const resetRequestAssistance = async function(token) {
	return await axios.delete(generateOcsUrl('/apps/spreed/api/v1/breakout-rooms/{token}/request-assistance', {
		token,
	})
	)
}

/**
 * This endpoint allows participants to switch between breakout rooms when they are allowed to choose the breakout room
 * and not are automatically or manually assigned by the moderator.
 *
 * @param {string} token Conversation token of the parent room hosting the breakout rooms
 * @param {string} target Conversation token of the target breakout room
 * @return {Promise<import('axios').AxiosResponse<any>>} The target breakout room
 */
const switchToBreakoutRoom = async function(token, target) {
	return await axios.post(generateOcsUrl('/apps/spreed/api/v1/breakout-rooms/{token}/switch', {
		token,
	}), {
		target,
	}
	)
}

export {
	configureBreakoutRooms,
	reorganizeAttendees,
	deleteBreakoutRooms,
	getBreakoutRooms,
	startBreakoutRooms,
	stopBreakoutRooms,
	broadcastMessageToBreakoutRooms,
	getBreakoutRoomsParticipants,
	requestAssistance,
	resetRequestAssistance,
	switchToBreakoutRoom,
}
