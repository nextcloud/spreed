/**
 *
 * @copyright Copyright (c) 2023, Daniel Calviño Sánchez (danxuliu@gmail.com)
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

import EmitterMixin from './EmitterMixin.js'

/**
 * Helper to keep track of the signaling participants.
 *
 * The participants included the following properties:
 * - nextcloudSessionId
 * - signalingSessionId
 * - userId (optional, not included if the participant is a guest)
 *
 * The following events are emitted:
 * - participantsJoined(participants)
 */
export default function SignalingParticipantList() {
	this._superEmitterMixin()

	this._signaling = null
	this._participants = []

	this._handleLeaveRoomBound = this._handleLeaveRoom.bind(this)
	this._handleUsersInRoomBound = this._handleUsersInRoom.bind(this)
	this._handleUsersJoinedBound = this._handleUsersJoined.bind(this)
	this._handleUsersLeftBound = this._handleUsersLeft.bind(this)
}

SignalingParticipantList.prototype = {

	destroy() {
		if (this._signaling) {
			this._signaling.off('leaveRoom', this._handleLeaveRoomBound)
			this._signaling.off('usersInRoom', this._handleUsersInRoomBound)
			this._signaling.off('usersJoined', this._handleUsersJoinedBound)
			this._signaling.off('usersLeft', this._handleUsersLeftBound)
		}

		this._destroyed = true

		this._participants = []
	},

	setSignaling(signaling) {
		if (this._destroyed) {
			return
		}

		if (this._signaling) {
			this._signaling.off('leaveRoom', this._handleLeaveRoomBound)
			this._signaling.off('usersInRoom', this._handleUsersInRoomBound)
			this._signaling.off('usersJoined', this._handleUsersJoinedBound)
			this._signaling.off('usersLeft', this._handleUsersLeftBound)
		}

		this._signaling = signaling

		if (this._signaling) {
			this._signaling.on('leaveRoom', this._handleLeaveRoomBound)
			this._signaling.on('usersInRoom', this._handleUsersInRoomBound)
			this._signaling.on('usersJoined', this._handleUsersJoinedBound)
			this._signaling.on('usersLeft', this._handleUsersLeftBound)
		}
	},

	getParticipants() {
		return this._participants
	},

	_handleLeaveRoom(token) {
		this._participants = []
	},

	_handleUsersInRoom(users) {
		const participants = []
		const participantsJoined = []

		for (const user of users) {
			const participant = {
				nextcloudSessionId: user.sessionId,
				signalingSessionId: user.sessionId,
			}
			if (user.userId) {
				participant.userId = user.userId
			}

			participants.push(participant)

			if (!this._participants.find(oldParticipant => oldParticipant.signalingSessionId === participant.signalingSessionId)) {
				participantsJoined.push(participant)
			}
		}

		this._participants = participants

		if (participantsJoined.length > 0) {
			this._trigger('participantsJoined', [participantsJoined])
		}
	},

	_handleUsersJoined(users) {
		const participantsJoined = []

		for (const user of users) {
			const participant = {
				nextcloudSessionId: user.roomsessionid,
				signalingSessionId: user.sessionid,
			}
			if (user.userid) {
				participant.userId = user.userid
			}

			this._participants.push(participant)

			participantsJoined.push(participant)
		}

		if (participantsJoined.length > 0) {
			this._trigger('participantsJoined', [participantsJoined])
		}
	},

	_handleUsersLeft(sessionIds) {
		for (const sessionId of sessionIds) {
			this._participants = this._participants.filter(participant => participant.signalingSessionId != sessionId)
		}
	},

}

EmitterMixin.apply(SignalingParticipantList.prototype)
