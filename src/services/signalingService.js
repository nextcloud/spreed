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

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { restartInternalSignaling, stopInternalSignaling } from './signaling/internalSignalingService'

const state = {
	listeners: {},
	isUsingExternalSignaling: false,
}

/**
 * Fetch the signaling settings for the given conversation
 * @param {string} token The token of the conversation to be connected.
 */
const fetchSignalingSettings = async function(token) {
	/**
	 * 'hideWarning' => !empty($signaling) || $this->getHideSignalingWarning(),
	 * 'server' => $signaling,
	 * 'ticket' => $this->getSignalingTicket($userId),
	 * 'stunservers' => $stun,
	 * 'turnservers' => $turn,
	 */
	return axios.get(generateOcsUrl('apps/spreed/api/v1/signaling', 2) + 'settings')
}

/**
 * Create the connection to a given servers
 *
 * @param {string} token Conversation to do the signaling on
 * @param {object} signalingServer Signaling server information
 * @param {string} signalingTicket The ticket to authenticate on the signaling server
 * @param {array} stunServers List of stun servers: {url: <String>}
 * @param {array} turnServers List of turn servers: {url: <String>, username: <String>, credential: <String>}
 */
const startSignaling = function(token, signalingServer, signalingTicket, stunServers, turnServers) {
	state.isUsingExternalSignaling = signalingServer && signalingServer.length
	if (state.isUsingExternalSignaling) {
		// External signaling server
		// FIXME
	} else {
		restartInternalSignaling(token)
	}
}

/**
 * Stop the connection to the servers and abort all requests
 */
const stopSignaling = function() {
	if (state.isUsingExternalSignaling) {
		// External signaling server
		// FIXME
	} else {
		stopInternalSignaling()
	}
}

/**
 * Add a listener
 * @param {string} ev Event identifier
 * @param {callback} handler Callback to be invoked on the event
 * @deprecated Use the EventBus instead, this is only for the transition between Talk 7 and 8 due to deadlines
 */
const addSignalingListener = function(ev, handler) {
	if (!state.listeners.hasOwnProperty(ev)) {
		state.listeners[ev] = [handler]
	} else {
		state.listeners[ev].push(handler)
	}
}

/**
 * Remove a listener
 * @param {string} ev Event identifier
 * @param {callback} handler Callback to be invoked on the event
 * @deprecated Use the EventBus instead, this is only for the transition between Talk 7 and 8 due to deadlines
 */
const removeSignalingListener = function(ev, handler) {
	if (!state.listeners.hasOwnProperty(ev)) {
		return
	}

	let pos = state.listeners[ev].indexOf(handler)
	while (pos !== -1) {
		state.listeners[ev].splice(pos, 1)
		pos = state.listeners[ev].indexOf(handler)
	}
}

export {
	fetchSignalingSettings,
	addSignalingListener,
	removeSignalingListener,

	startSignaling,
	stopSignaling,
}
