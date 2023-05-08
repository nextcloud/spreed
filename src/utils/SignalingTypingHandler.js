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

import SignalingParticipantList from './SignalingParticipantList.js'

/**
 * Helper to send and receive signaling messages for typing notifications.
 *
 * The store is updated when remote participants change their typing status.
 *
 * It is expected that the typing status of the current participant will be
 * modified only when the current conversation is joined.
 *
 * @param {object} store the Vuex store
 */
export default function SignalingTypingHandler(store) {
	this._store = store

	this._signaling = null
	this._signalingParticipantList = new SignalingParticipantList()

	this._typing = false

	this._handleMessageBound = this._handleMessage.bind(this)
	this._handleParticipantsJoinedBound = this._handleParticipantsJoined.bind(this)
	this._handleParticipantsLeftBound = this._handleParticipantsLeft.bind(this)
}

SignalingTypingHandler.prototype = {

	destroy() {
		if (this._signaling) {
			this._signaling.off('message', this._handleMessageBound)
			this._signalingParticipantList.off('participantsJoined', this._handleParticipantsJoinedBound)
			this._signalingParticipantList.off('participantsLeft', this._handleParticipantsLeftBound)
		}

		this._signalingParticipantList.destroy()

		this._destroyed = true
	},

	setSignaling(signaling) {
		if (this._destroyed) {
			return
		}

		if (this._signaling) {
			this._signaling.off('message', this._handleMessageBound)
			this._signalingParticipantList.off('participantsJoined', this._handleParticipantsJoinedBound)
			this._signalingParticipantList.off('participantsLeft', this._handleParticipantsLeftBound)
		}

		this._signaling = signaling
		this._signalingParticipantList.setSignaling(signaling)

		if (this._signaling) {
			this._signaling.on('message', this._handleMessageBound)
			this._signalingParticipantList.on('participantsJoined', this._handleParticipantsJoinedBound)
			this._signalingParticipantList.on('participantsLeft', this._handleParticipantsLeftBound)
		}
	},

	setTyping(typing) {
		if (this._destroyed) {
			return
		}

		if (!this._signaling) {
			return
		}

		if (!this._store.getters.currentConversationIsJoined) {
			return
		}

		this._typing = typing

		const currentNextcloudSessionId = this._store.getters.getSessionId()

		for (const participant of this._signalingParticipantList.getParticipants()) {
			if (participant.nextcloudSessionId === currentNextcloudSessionId) {
				continue
			}

			this._signaling.emit('message', {
				type: typing ? 'startedTyping' : 'stoppedTyping',
				to: participant.signalingSessionId,
			})
		}

		this._store.commit('setTyping', {
			token: this._store.getters.getToken(),
			sessionId: this._store.getters.getSessionId(),
			typing,
		})
	},

	_handleMessage(data) {
		if (data.type !== 'startedTyping' && data.type !== 'stoppedTyping') {
			return
		}

		const participant = this._signalingParticipantList.getParticipants().find(participant => participant.signalingSessionId === data.from)
		if (!participant) {
			return
		}

		this._store.commit('setTyping', {
			token: this._store.getters.getToken(),
			sessionId: participant.nextcloudSessionId,
			typing: data.type === 'startedTyping',
		})
	},

	_handleParticipantsJoined(SignalingParticipantList, participants) {
		if (!this._typing) {
			return
		}

		for (const participant of participants) {
			this._signaling.emit('message', {
				type: 'startedTyping',
				to: participant.signalingSessionId,
			})
		}
	},

	_handleParticipantsLeft(SignalingParticipantList, participants) {
		for (const participant of participants) {
			this._store.commit('setTyping', {
				token: this._store.getters.getToken(),
				sessionId: participant.nextcloudSessionId,
				typing: false,
			})
		}
	},

}
