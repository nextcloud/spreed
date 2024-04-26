/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
 * - participantsLeft(participants)
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
		if (this._participants.length > 0) {
			this._trigger('participantsLeft', [this._participants])
		}

		this._participants = []
	},

	_handleUsersInRoom(users) {
		const participants = []
		const participantsJoined = []
		const participantsLeft = []

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

		for (const oldParticipant of this._participants) {
			if (!participants.find(participant => participant.signalingSessionId === oldParticipant.signalingSessionId)) {
				participantsLeft.push(oldParticipant)
			}
		}

		this._participants = participants

		if (participantsJoined.length > 0) {
			this._trigger('participantsJoined', [participantsJoined])
		}
		if (participantsLeft.length > 0) {
			this._trigger('participantsLeft', [participantsLeft])
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
		const participantsLeft = []

		for (const sessionId of sessionIds) {
			const index = this._participants.findIndex(participant => participant.signalingSessionId === sessionId)
			if (index >= 0) {
				participantsLeft.push(this._participants[index])

				this._participants.splice(index, 1)
			}
		}

		if (participantsLeft.length > 0) {
			this._trigger('participantsLeft', [participantsLeft])
		}
	},

}

EmitterMixin.apply(SignalingParticipantList.prototype)
