/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Hex from 'crypto-js/enc-hex.js'
import SHA1 from 'crypto-js/sha1.js'
import { defineStore } from 'pinia'
import Vue from 'vue'

import { useGuestNameStore } from './guestName.js'
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
}

type SignalingSessionPayload =
	| InternalSignalingSession
	| StandaloneSignalingJoinSession
	| StandaloneSignalingUpdateSession

type ParticipantUpdatePayload = {
	attendeeId: number,
	displayName?: string,
	inCall: number,
	lastPing: number,
	permissions: number,
	participantType?: number,
	sessionIds?: string[],
}

type ParticipantStandaloneJoinPayload = {
	attendeeId: number,
	displayName: string,
	sessionIds: string[],
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

function isStandaloneSignalingJoinSession(item: SignalingSessionPayload): item is StandaloneSignalingJoinSession
function isStandaloneSignalingJoinSession(item: SignalingSessionPayload[]): item is StandaloneSignalingJoinSession[]
/**
 *
 * @param item user or array of users as it comes from signaling message
 */
function isStandaloneSignalingJoinSession(item: SignalingSessionPayload | SignalingSessionPayload[]): item is StandaloneSignalingJoinSession | StandaloneSignalingJoinSession[] {
	if (Array.isArray(item)) {
		return 'sessionid' in item[0]
	}
	return 'sessionid' in item
}

function isStandaloneSignalingUpdateSession(item: SignalingSessionPayload): item is StandaloneSignalingUpdateSession
function isStandaloneSignalingUpdateSession(item: SignalingSessionPayload[]): item is StandaloneSignalingUpdateSession[]
/**
 *
 * @param item user or array of users as it comes from signaling message
 */
function isStandaloneSignalingUpdateSession(item: SignalingSessionPayload | SignalingSessionPayload[]): item is StandaloneSignalingUpdateSession | StandaloneSignalingUpdateSession[] {
	if (Array.isArray(item)) {
		return !('roomId' in item[0]) && 'sessionId' in item[0]
	}
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

		orphanSessionIds: (state) => {
			return Object.keys(state.sessions).filter(signalingSessionId => !state.sessions[signalingSessionId].attendeeId) // TODO
		},
	},

	actions: {
		addSession(session: Session) {
			Vue.set(this.sessions, session.signalingSessionId, session) // TODO
			return session
		},

		deleteSession(signalingSessionId: string) {
			if (this.sessions[signalingSessionId]) {
				Vue.delete(this.sessions, signalingSessionId) // TODO
			}
		},

		updateSession(signalingSessionId: string, updatedData: Partial<Session>) { // TODO
			if (this.sessions[signalingSessionId]) {
				Vue.set(this.sessions, signalingSessionId, {
					...this.sessions[signalingSessionId],
					...updatedData,
				})
			}
		},

		findOrCreateSession(token: string, user: SignalingSessionPayload): Session {
			const signalingSessionId = isStandaloneSignalingJoinSession(user) ? user.sessionid : user.sessionId
			if (!signalingSessionId) {
				throw new Error('Can not define sessionId from the payload') // TODO
			}

			const knownSession = this.getSession(signalingSessionId)
			if (knownSession) {
				return knownSession
			}

			let sessionId: string | undefined
			let attendee: Participant | null
			if (isStandaloneSignalingJoinSession(user)) {
				sessionId = user.roomsessionid
				const actorType = user.userid
					? (user.federated ? ATTENDEE.ACTOR_TYPE.FEDERATED_USERS : ATTENDEE.ACTOR_TYPE.USERS)
					: ATTENDEE.ACTOR_TYPE.GUESTS // FIXME emails?
				attendee = store.getters.findParticipant(token, {
					sessionId,
					actorId: user.userid,
					actorType,
				}) as Participant | null
			} else {
				sessionId = isInternalSignalingSession(user) ? user.sessionId : user.nextcloudSessionId
				attendee = store.getters.findParticipant(token, {
					sessionId,
					actorId: user.actorId,
					actorType: user.actorType,
				}) as Participant | null
			}

			return this.addSession({
				attendeeId: attendee?.attendeeId,
				token,
				signalingSessionId,
				sessionId,
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
					hasUnknownSessions = true
					continue
				}

				if (isStandaloneSignalingJoinSession(user)) {
					this.updateParticipantJoinedFromStandaloneSignaling(token, session.attendeeId, user)
				} else if (isStandaloneSignalingUpdateSession(user)) {
					this.updateParticipantChangedFromStandaloneSignaling(token, session.attendeeId, user)
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
			this.updateParticipantsLeftFromStandaloneSignaling(token, sessionIds)
			for (const sessionId of sessionIds) {
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
				const attendeeId = session?.attendeeId
				if (!attendeeId) {
					// Skip participant update
					continue
				}

				if (!participantsToUpdate[attendeeId]) {
					// Prepare payload to update object in participants store
					participantsToUpdate[attendeeId] = {
						attendeeId,
						inCall: user.inCall,
						lastPing: user.lastPing,
						permissions: user.participantPermissions,
						sessionIds: [user.sessionId],
					}
				} else {
					// Payload already exists, but participant also joined from another device
					participantsToUpdate[attendeeId].sessionIds!.push(user.sessionId)
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
						updatedData: { attendeeId, inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED, sessionIds: [] },
					})
				}
			}
		},

		/**
		 * Update participants joined in store according to signaling message
		 *
		 * @param token - Conversation token
		 * @param attendeeId - Attendee ID
		 * @param user - Users payload from signaling message
		 */
		updateParticipantJoinedFromStandaloneSignaling(token: string, attendeeId: number, user: StandaloneSignalingJoinSession) {
			const participant = store.getters.getParticipant(token, attendeeId) as Participant | null
			if (!participant) {
				return // TODO
			}

			const updatedData: ParticipantStandaloneJoinPayload = {
				attendeeId,
				displayName: user.user?.displayname ?? participant.displayName,
				sessionIds: [...new Set([...participant.sessionIds, user.roomsessionid])],
			}

			store.commit('updateParticipant', { token, attendeeId, updatedData })
		},

		/**
		 * Update participants left in store according to signaling message
		 *
		 * @param token - Conversation token
		 * @param sessionIdsLeft - Disconnected signaling sessions
		 */
		updateParticipantsLeftFromStandaloneSignaling(token: string, sessionIdsLeft: string[]) {
			for (const sessionId of sessionIdsLeft) {
				const session = this.getSession(sessionId)
				const attendeeId = session?.attendeeId
				if (!attendeeId) {
					// Skip participant update
					continue
				}

				const participant = store.getters.getParticipant(token, attendeeId) as Participant | null
				if (!participant) {
					continue // TODO
				}

				const sessionIds = participant.sessionIds.filter((id: string) => id !== sessionId)
				store.commit('updateParticipant', {
					token,
					attendeeId,
					updatedData: {
						sessionIds,
						inCall: sessionIds.length ? participant.inCall : PARTICIPANT.CALL_FLAG.DISCONNECTED,
					},
				})
			}
		},

		/**
		 * Update participants changes in store according to signaling message
		 *
		 * @param token - Conversation token
		 * @param attendeeId - Attendee ID
		 * @param user - Users payload from signaling message
        */
		updateParticipantChangedFromStandaloneSignaling(token: string, attendeeId: number, user: StandaloneSignalingUpdateSession) {
			const guestNameStore = useGuestNameStore()

			const participant = store.getters.getParticipant(token, attendeeId) as Participant | null
			if (!participant) {
				return // TODO
			}

			const updatedData = {
				attendeeId,
				displayName: user.displayName ?? participant.displayName,
				participantType: user.participantType,
				permissions: user.participantPermissions,
				inCall: Math.max(participant.inCall, user.inCall),
				lastPing: user.lastPing,
			}

			store.commit('updateParticipant', { token, attendeeId, updatedData })

			if ((participant.participantType === PARTICIPANT.TYPE.GUEST || participant.participantType === PARTICIPANT.TYPE.GUEST_MODERATOR)
				&& updatedData.displayName !== participant.displayName) {
				guestNameStore.addGuestName({  // TODO
					token,
					actorId: Hex.stringify(SHA1(participant.sessionIds[0])),
					actorDisplayName: updatedData.displayName!,
				}, { noUpdate: false })
			}
		},

		/**
		 * Update participants (end call for everyone) in store according to signaling message
		 *
		 * @param token - Conversation token
		 */
		updateParticipantsDisconnectedFromStandaloneSignaling(token: string) {
			for (const participant of store.getters.participantsList(token) as Participant[]) {
				store.commit('updateParticipant', {
					token,
					attendeeId: participant.attendeeId,
					updatedData: {
						inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED,
					},
				})
			}
		},
	},
})
