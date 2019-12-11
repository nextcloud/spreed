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
import { EventBus } from './EventBus'
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
 * Does the signaling server support a given feature
 * @param {string} feature The feature to check for
 * @returns {boolean}
 */
const hasFeature = function(feature) {
	if (state.isUsingExternalSignaling) {
		// External signaling server
		// FIXME return hasFeatureExternalSignaling(feature)
		return false
	} else {
		return false
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
	if (['onBeforeReceiveMessage', 'onAfterReceiveMessage'].indexOf(ev)) {
		console.debug('The event "' + ev + '" is not supported anymore')
	}

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

/**
 * Invoke the legacy listeners
 * @param {string} ev Event identifier
 * @param {*} args The data of the event
 */
const invokeSignalingListeners = function(ev, args) {
	let handlers = state.listeners[ev]
	if (!handlers) {
		return
	}

	handlers = handlers.slice(0)
	for (let i = 0, len = handlers.length; i < len; i++) {
		const handler = handlers[i]
		handler.apply(handler, args)
	}
}

/**
 * TODO This is only temporary and should be migrated to a direct use instead
 */
EventBus.$on('Signaling::usersInRoom', function(args) {
	invokeSignalingListeners('usersInRoom', args)
	invokeSignalingListeners('participantListChanged', [])
})

EventBus.$on('Signaling::message', function(args) {
	invokeSignalingListeners('message', args)
})

EventBus.$on('Signaling::stoppedOnFail', function(args) {
	invokeSignalingListeners('pullMessagesStoppedOnFail', args)
})

export {
	fetchSignalingSettings,
	startSignaling,
	hasFeature,
	stopSignaling,

	addSignalingListener,
	removeSignalingListener,
}
