/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import Vue from 'vue'

import { ATTENDEE, PARTICIPANT } from '../constants.ts'
import store from '../store/index.js'
import type {
	InternalSignalingSession,
	Participant,
	StandaloneSignalingJoinSession,
	StandaloneSignalingLeaveSession,
	StandaloneSignalingUpdateSession,
} from '../types/index.ts'

type Session = {
	attendeeId: number | undefined,
	token: string,
	signalingSessionId: string,
	sessionId: string | undefined,
	inCall: number | undefined,
}

type SignalingSessionPayload =
	| InternalSignalingSession
	| StandaloneSignalingJoinSession
	| StandaloneSignalingUpdateSession

type ParticipantUpdatePayload = {
	displayName?: string,
	inCall: number,
	lastPing: number,
	permissions: number,
	participantType?: number,
	sessionIds?: string[],
}

type State = {
	sessions: Record<string, Session>,
}

function isInternalSignalingSession(item: SignalingSessionPayload): item is InternalSignalingSession
function isInternalSignalingSession(item: SignalingSessionPayload[]): item is InternalSignalingSession[]
/**
 *
 * @param item user or array of users as it comes from signaling message
 */
function isInternalSignalingSession(item: SignalingSessionPayload | SignalingSessionPayload[]): item is InternalSignalingSession | InternalSignalingSession[] {
	if (Array.isArray(item)) {
		return 'roomId' in item[0]
	}
	return 'roomId' in item
}

/**
 *
 * @param item user as it comes from signaling message
 */
function isStandaloneSignalingJoinSession(item: SignalingSessionPayload): item is StandaloneSignalingJoinSession {
	return 'sessionid' in item
}

/**
 *
 * @param item user as it comes from signaling message
 */
function isStandaloneSignalingUpdateSession(item: SignalingSessionPayload): item is StandaloneSignalingUpdateSession {
	return !('roomId' in item) && 'sessionId' in item
}

export const useSessionStore = defineStore('session', {
	state: (): State => ({
		sessions: {},
	}),

	getters: {
		getSession: (state) => (signalingSessionId?: string): Session | undefined => {
			if (signalingSessionId) {
				return state.sessions[signalingSessionId]
			}
		},
	},

	actions: {
		addSession(session: Session) {
			Vue.set(this.sessions, session.signalingSessionId, session)
			return session
		},

		deleteSession(signalingSessionId: string) {
			if (this.sessions[signalingSessionId]) {
				Vue.delete(this.sessions, signalingSessionId)
			}
		},

		findOrCreateSession(token: string, user: SignalingSessionPayload): Session {
			const signalingSessionId = isStandaloneSignalingJoinSession(user) ? user.sessionid : user.sessionId
			if (!signalingSessionId) {
				console.error('Can not define sessionId from the payload: %s', JSON.stringify(user))
				return null
			}

			const knownSession = this.getSession(signalingSessionId)
			if (knownSession) {
				return knownSession
			}

			let sessionId: Session['sessionId'] | undefined
			if (isStandaloneSignalingJoinSession(user)) {
				sessionId = user.roomsessionid
			} else {
				sessionId = isInternalSignalingSession(user) ? user.sessionId : user.nextcloudSessionId
			}
			if (!sessionId) {
				/**
				 * FIXME currently non-internal Nextcloud sessions are ignored. Examples:
				 * - recording server joining as hidden participant
				 * - dial-out phone number joining call
				 */
				console.debug('Ignored session: %s', JSON.stringify(user))
				return null
			}

			let attendee: Participant | null
			let inCall: Session['inCall']
			if (isStandaloneSignalingJoinSession(user)) {
				/**
				 * FIXME it is currently impossible to define some Nextcloud actor types (e.g. emails)
				 * from the signaling payload. Falling back to internal/federated users or guests
				 */
				const actorType = user.userid
					? (user.federated ? ATTENDEE.ACTOR_TYPE.FEDERATED_USERS : ATTENDEE.ACTOR_TYPE.USERS)
					: ATTENDEE.ACTOR_TYPE.GUESTS
				attendee = store.getters.findParticipant(token, {
					sessionId,
					actorId: user.userid,
					actorType,
				}) as Participant | null
				inCall = attendee?.inCall
			} else {
				attendee = store.getters.findParticipant(token, {
					sessionId,
					actorId: user.actorId,
					actorType: user.actorType,
				}) as Participant | null
				inCall = user.inCall
			}

			return this.addSession({
				attendeeId: attendee?.attendeeId,
				token,
				signalingSessionId,
				sessionId,
				inCall,
			})
		},

		/**
		 * Update sessions in store and participants object according to data from signaling messages
		 *
		 * @param token - Conversation token
		 * @param users - Users payload from signaling message
		 * @return {boolean} whether list has unknown sessions mapped to attendees list
		 */
		updateSessions(token: string, users: SignalingSessionPayload[]): boolean {
			let hasUnknownSessions = false
			const currentSessionIds = new Set<string>()

			for (const user of users) {
				const session = this.findOrCreateSession(token, user)
				currentSessionIds.add(session.signalingSessionId)

				// If we can not find an attendeeId for session - participant is missing from the list
				if (!session.attendeeId) {
					console.debug('Possible orphan session: %s', JSON.stringify(user))
					hasUnknownSessions = true
					continue
				}

				if (isStandaloneSignalingJoinSession(user)) {
					// this.updateParticipantJoinedFromStandaloneSignaling(token, user)
				} else if (isStandaloneSignalingUpdateSession(user)) {
					// this.updateParticipantChangedFromStandaloneSignaling(token, user)
				}
			}

			if (isInternalSignalingSession(users)) {
				this.updateParticipantsFromInternalSignaling(token, users)

				// Internal signaling server always returns all current sessions,
				// so if some participants are missing - they are 'offline'
				for (const signalingSessionId of Object.keys(this.sessions)) {
					if (!currentSessionIds.has(signalingSessionId)) {
						this.deleteSession(signalingSessionId)
					}
				}
			}

			return hasUnknownSessions
		},

		/**
		 * Delete sessions in store and update participants objects
		 *
		 * @param token - Conversation token
		 * @param sessionIds - Left session ids from signaling message
		 */
		updateSessionsLeft(token: string, sessionIds: StandaloneSignalingLeaveSession[]) {
			for (const sessionId of sessionIds) {
				// this.updateParticipantLeftFromStandaloneSignaling(token, sessionId)
				this.deleteSession(sessionId)
			}
		},

		/**
		 * Update participants in store according to data from internal signaling server
		 *
		 * @param token - Conversation token
		 * @param users - Users payload from signaling message
		 */
		updateParticipantsFromInternalSignaling(token: string, users: InternalSignalingSession[]) {
			const participantsToUpdate: Record<string, ParticipantUpdatePayload> = {}

			for (const user of users) {
				const session = this.getSession(user.sessionId)
				this.updateSession(user.sessionId, { inCall: user.inCall })
				const attendeeId = session?.attendeeId
				if (!attendeeId) {
					// Skip participant update
					continue
				}

				if (!participantsToUpdate[attendeeId]) {
					// Prepare payload to update object in participants store
					participantsToUpdate[attendeeId] = {
						inCall: user.inCall,
						lastPing: user.lastPing,
						permissions: user.participantPermissions,
						sessionIds: [user.sessionId],
					}
				} else {
					// Payload already exists, but participant also joined from another device
					participantsToUpdate[attendeeId].sessionIds!.push(user.sessionId)
					participantsToUpdate[attendeeId].inCall = participantsToUpdate[attendeeId].inCall | user.inCall
					participantsToUpdate[attendeeId].lastPing = Math.max(participantsToUpdate[attendeeId].lastPing, user.lastPing)
				}
			}

			for (const participant of store.getters.participantsList(token) as Participant[]) {
				const { attendeeId, sessionIds } = participant
				if (participantsToUpdate[attendeeId]) {
					store.commit('updateParticipant', {
						token,
						attendeeId,
						updatedData: participantsToUpdate[attendeeId],
					})
				} else if (sessionIds.length !== 0) {
					// Participant left conversation from all devices, setting as 'offline'
					store.commit('updateParticipant', {
						token,
						attendeeId,
						updatedData: { inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED, sessionIds: [] },
					})
				}
			}
		},
	},
})
