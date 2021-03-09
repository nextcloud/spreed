/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import {
	signalingJoinCall,
	signalingLeaveCall,
} from '../utils/webrtc/index'

/**
 * Join a call as participant
 * @param {string} token The token of the call to be joined.
 * @param {int} flags The available PARTICIPANT.CALL_FLAG for this participants
 */
const joinCall = async function(token, flags) {
	try {
		// FIXME flags is ignored?
		await signalingJoinCall(token)
	} catch (error) {
		console.debug('Error while joining call: ', error)
	}
}

/**
 * Leave a call as participant
 * @param {string} token The token of the call to be left
 */
const leaveCall = async function(token) {
	try {
		await signalingLeaveCall(token)
	} catch (error) {
		console.debug('Error while leaving call: ', error)
	}
}

const fetchPeers = async function(token, options) {
	const response = await axios.get(generateOcsUrl('apps/spreed/api/v4', 2) + `call/${token}`, options)
	return response
}

export {
	joinCall,
	leaveCall,
	fetchPeers,
}
