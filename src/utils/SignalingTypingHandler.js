/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { useActorStore } from '../stores/actor.js'
import pinia from '../stores/pinia.ts'
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
	this._actorStore = useActorStore(pinia)

	this._signaling = null
	this._signalingParticipantList = new SignalingParticipantList()

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

		const currentNextcloudSessionId = this._actorStore.sessionId

		for (const participant of this._signalingParticipantList.getParticipants()) {
			if (participant.nextcloudSessionId === currentNextcloudSessionId) {
				continue
			}

			this._signaling.emit('message', {
				type: typing ? 'startedTyping' : 'stoppedTyping',
				to: participant.signalingSessionId,
			})
		}

		this._store.dispatch('setTyping', {
			token: this._store.getters.getToken(),
			sessionId: this._actorStore.sessionId,
			typing,
		})
	},

	_handleMessage(data) {
		if (data.type !== 'startedTyping' && data.type !== 'stoppedTyping') {
			return
		}

		const participant = this._signalingParticipantList.getParticipants().find((participant) => participant.signalingSessionId === data.from)
		if (!participant) {
			return
		}

		this._store.dispatch('setTyping', {
			token: this._store.getters.getToken(),
			sessionId: participant.nextcloudSessionId,
			typing: data.type === 'startedTyping',
		})
	},

	_handleParticipantsJoined(SignalingParticipantList, participants) {
		if (!this._store.getters.actorIsTyping) {
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
			this._store.dispatch('setTyping', {
				token: this._store.getters.getToken(),
				sessionId: participant.nextcloudSessionId,
				typing: false,
			})
		}
	},

}
