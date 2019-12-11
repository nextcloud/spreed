/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

import CancelableRequest from '../../utils/cancelableRequest'
import axios from '@nextcloud/axios'
import { EventBus } from '../../services/EventBus'
import { generateOcsUrl } from '@nextcloud/router'

const state = {
	token: '',
	failedRequests: 0,
	pendingMessages: [],
	cancelFetch: () => {},
	cancelSend: () => {},
	isSendingMessages: false,
}

/**
 * Send signaling messages to the signaling server
 * @param {string} token The token of the conversation to be messaged to.
 * @param {Array} messages The signaling messages to send
 */
const sendInternalMessages = async function(token, messages) {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/signaling', 2) + token, {
		messages: JSON.stringify(messages),
	})
}

/**
 * Fetch signaling messages from the signaling server
 * @param {string} token The token of the conversation to be polled from.
 */
const fetchInternalMessages = async function(token) {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/signaling', 2) + token)
}

/**
 * Reset the state of the internal signaling and start polling
 * @param {string} token The conversation token to do signaling for.
 */
const restartInternalSignaling = function(token) {
	state.cancelFetch('canceled')
	state.cancelSend('canceled')

	state.token = token
	state.pendingMessages = []
	state.isSendingMessages = false
	state.cancelFetch = () => {}
	state.cancelSend = () => {}

	if (token === '') {
		return
	}

	window.setInterval(() => {
		// Send signaling messages grouped every 500ms
		sendSignalingMessages()
	}, 500)

	fetchSignalingMessages()
}

/**
 * Stop polling for signaling messages
 */
const stopInternalSignaling = function() {
	state.cancelFetch('canceled')
	state.cancelSend('canceled')

	state.token = ''
	state.pendingMessages = []
	state.isSendingMessages = false
	state.cancelFetch = () => {}
	state.cancelSend = () => {}
}

const sendSignalingMessages = async function() {
	if (state.isSendingMessages) {
		return
	}
	state.isSendingMessages = true

	const pendingMessagesLength = state.pendingMessages.length
	if (pendingMessagesLength === 0) {
		state.isSendingMessages = false
		return
	}

	const messages = state.pendingMessages.splice(0, pendingMessagesLength)

	state.cancelSend('canceled')
	const { request, cancel } = CancelableRequest(sendInternalMessages)
	state.cancelSend = cancel

	try {
		await request(state.token, messages)
	} catch (exception) {
		console.error('Error while sending signaling messages')
	}

	state.isSendingMessages = false
}

const fetchSignalingMessages = async function() {
	state.cancelFetch('canceled')
	const { request, cancel } = CancelableRequest(fetchInternalMessages)
	state.cancelFetch = cancel

	if (!state.token) {
		// Signaling is stopped
		return
	}

	try {
		const response = await request(state.token)

		// Successful request, reset the fail counter
		state.failedRequests = 0

		response.data.ocs.data.forEach(message => {
			switch (message.type) {
			case 'usersInRoom':
				EventBus.$emit('Signaling::usersInRoom', [message.data])
				break
			case 'message':
				if (typeof (message.data) === 'string') {
					message.data = JSON.parse(message.data)
				}
				EventBus.$emit('Signaling::message', [message.data])
				break
			default:
				console.info('Unknown Signaling Message')
				break
			}
		})
	} catch (exception) {
		if (axios.isCancel(exception)) {
			console.debug('The request has been canceled', exception)
			return
		}

		if (exception.response) {
			if (exception.response.status === 403
				|| exception.response.status === 404) {
				EventBus.$emit('Signaling::stoppedOnFail')
			}
		}
		state.failedRequests++

		if (state.failedRequests >= 3) {
			EventBus.$emit('Signaling::stoppedOnFail')
			throw exception
		}
	}

	/**
	 * Recursively call the method for new signaling messages
	 */
	fetchSignalingMessages()
}

export {
	restartInternalSignaling,
	stopInternalSignaling,
}
