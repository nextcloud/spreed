/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Hex from 'crypto-js/enc-hex.js'
import SHA1 from 'crypto-js/sha1.js'
import { defineStore } from 'pinia'
import Vue from 'vue'

import { useGuestNameStore } from './guestName.js'
import { ATTENDEE, PARTICIPANT } from '../constants.js'
import store from '../store/index.js'
import type { Participant } from '../types'

type Session = {
	attendeeId: number | undefined,
	token: string,
	signalingSessionId: string,
	sessionId: string | undefined,
}
type InternalSignalingPayload = {
	userId: string,
	sessionId: string,
	roomId: number,
	inCall: number,
	lastPing: number,
	participantPermissions: number,
}
type InternalUpdatePayload = Record<string, {
	inCall: number,
	lastPing: number,
	permissions: number,
	sessionIds: string[],
}>
type StandaloneSignalingJoinPayload = {
	userid: string,
	user: { displayname: string },
	sessionid: string, // Standalone signaling id
	roomsessionid: string, // Nextcloud id
}
type StandaloneSignalingChangePayload = {
	sessionId: string, // Standalone signaling id
	participantType: number,
	participantPermissions: number,
	inCall: number,
	lastPing: number,
	nextcloudSessionId?: string, // Nextcloud id
	userId?: string, // For registered users only
	displayName?: string,
}
type StandaloneUpdatePayload = Record<string, {
	inCall: number,
	lastPing: number,
	permissions: number,
	participantType: number,
	displayName?: string,
}>

type FindSessionType = 'int-change' | 'ext-join' | 'ext-change'
type FindSessionPayload = InternalSignalingPayload | StandaloneSignalingJoinPayload | StandaloneSignalingChangePayload

type State = {
	sessions: Record<string, Session>,
}
export const useSignalingStore = defineStore('signaling', {
	state: (): State => ({
		sessions: {},
	}),

	getters: {
		getSignalingSession: (state) => (signalingSessionId?: string): Session | undefined => {
			if (signalingSessionId) {
				return state.sessions[signalingSessionId]
			}
		},
	},

	actions: {
		addSignalingSession(session: Session) {
			Vue.set(this.sessions, session.signalingSessionId, session)
		},

		deleteSignalingSession(signalingSessionId: string) {
			if (this.sessions[signalingSessionId]) {
				Vue.delete(this.sessions, signalingSessionId)
			}
		},

		findSignalingSession(token: string, type: FindSessionType, payload: FindSessionPayload) {
			// Unify payload from different methods
			let signalingSessionId: string | undefined
			let userId: string | undefined
			let sessionId: string | undefined
			switch (type) {
			case 'ext-join': {
				signalingSessionId = (payload as StandaloneSignalingJoinPayload).sessionid
				userId = (payload as StandaloneSignalingJoinPayload).userid
				sessionId = (payload as StandaloneSignalingJoinPayload).roomsessionid
				break
			}
			case 'int-change': {
				signalingSessionId = (payload as InternalSignalingPayload).sessionId
				userId = (payload as InternalSignalingPayload).userId
				sessionId = (payload as InternalSignalingPayload).sessionId
				break
			}
			case 'ext-change': {
				signalingSessionId = (payload as StandaloneSignalingChangePayload).sessionId
				userId = (payload as StandaloneSignalingChangePayload).userId
				sessionId = (payload as StandaloneSignalingChangePayload).nextcloudSessionId
				break
			}
			}

			// Look for existing session by signaling id
			const knownSession: Session | undefined = this.getSignalingSession(signalingSessionId)
			if (knownSession) {
				return knownSession
			}

			// Attempt to find attendee by userId or guest sessionIds
			if (signalingSessionId) {
				const attendee = (store.getters.participantsList(token) as Participant[]).find(attendee => {
					return attendee.actorType !== ATTENDEE.ACTOR_TYPE.GUESTS
						? userId && attendee.actorId === userId
						: sessionId && attendee.sessionIds.includes(sessionId)
				})

				const newSession: Session = { attendeeId: attendee?.attendeeId, token, signalingSessionId, sessionId }
				this.addSignalingSession(newSession)

				return newSession
			}
		},

		/**
		 * Update participants in store according to data from internal signaling server
		 *
		 * @param token the conversation token;
		 * @param participants the new participant objects;
		 * @return {boolean} whether list has unknown sessions mapped to attendees list
		 */
		updateParticipantsFromInternalSignaling(token: string, participants: InternalSignalingPayload[]): boolean {
			const attendeeUsers = store.getters.participantsList(token) as Participant[]
			const attendeeUsersToUpdate: InternalUpdatePayload = {}
			const newSessions = new Set<string>()
			let hasUnknownSessions = false

			for (const participant of participants) {
				newSessions.add(participant.sessionId)
				const session = this.findSignalingSession(token, 'int-change', participant)
				const attendeeId = session?.attendeeId
				if (!attendeeId) {
					hasUnknownSessions = true
					continue
				}

				if (!attendeeUsersToUpdate[attendeeId]) {
					// Prepare updated data
					attendeeUsersToUpdate[attendeeId] = {
						inCall: participant.inCall,
						lastPing: participant.lastPing,
						permissions: participant.participantPermissions,
						sessionIds: [participant.sessionId],
					}
				} else {
					// Participant might join from several devices
					attendeeUsersToUpdate[attendeeId].sessionIds.push(participant.sessionId)
				}
			}

			// Update participant objects
			for (const attendee of attendeeUsers) {
				const { attendeeId, sessionIds } = attendee
				if (attendeeUsersToUpdate[attendeeId]) {
					store.commit('updateParticipant', {
						token,
						attendeeId,
						updatedData: attendeeUsersToUpdate[attendeeId],
					})
				} else if (sessionIds.length) {
					// Participant left conversation from all devices
					store.commit('updateParticipant', {
						token,
						attendeeId,
						updatedData: { inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED, sessionIds: [] },
					})
				}
			}

			// Clean up old sessions
			for (const session of Object.keys(this.sessions)) {
				if (!newSessions.has(session)) {
					this.deleteSignalingSession(session)
				}
			}

			return hasUnknownSessions
		},

		/**
		 * Update participants joined in store according to data from standalone signaling server
		 *
		 * @param token the conversation token;
		 * @param participants the newly joined participant objects;
		 * @return {boolean} whether list has unknown sessions mapped to attendees list
		 */
		updateParticipantsJoinedFromStandaloneSignaling(token: string, participants: StandaloneSignalingJoinPayload[]): boolean {
			const attendeeUsers = store.getters.participantsList(token) as Participant[]
			const attendeeUsersToUpdate: Record<string, { displayName?: string, sessionIds: string[] }> = {}
			let hasUnknownSessions = false

			for (const participant of participants) {
				const session = this.findSignalingSession(token, 'ext-join', participant)
				const attendeeId = session?.attendeeId

				if (!attendeeId) {
					hasUnknownSessions = true
					continue
				}

				const attendee = store.getters.getParticipant(token, attendeeId)

				if (!attendeeUsersToUpdate[attendeeId]) {
					attendeeUsersToUpdate[attendeeId] = { sessionIds: [...attendee.sessionIds] }
				}
				if (participant.user.displayname) {
					attendeeUsersToUpdate[attendeeId].displayName = participant.user.displayname
				}
				// Participant might join from several devices
				if (!attendeeUsersToUpdate[attendeeId].sessionIds.includes(participant.roomsessionid)) {
					attendeeUsersToUpdate[attendeeId].sessionIds.push(participant.roomsessionid)
				}
			}

			for (const [attendeeId, updatedData] of Object.entries(attendeeUsersToUpdate)) {
				store.commit('updateParticipant', {
					token,
					attendeeId: +attendeeId,
					updatedData,
				})
			}

			return hasUnknownSessions
		},

		/**
		 * Update participants left in store according to data from standalone signaling server
		 *
		 * @param signalingSessionIds disconnected signaling sessions;
		 */
		updateParticipantsLeftFromStandaloneSignaling(signalingSessionIds: string[]) {
			for (const signalingSessionId of signalingSessionIds) {
				const session = this.getSignalingSession(signalingSessionId)
				if (!session) {
					continue
				}
				this.deleteSignalingSession(signalingSessionId)

				const { token, attendeeId, sessionId } = session
				const attendee = store.getters.getParticipant(token, attendeeId)
				const updatedData : { sessionIds: [], inCall?: number } = {
					sessionIds: attendee.sessionIds.filter((id: string) => id !== sessionId)
				}
				if (updatedData.sessionIds.length === 0) {
					updatedData.inCall = PARTICIPANT.CALL_FLAG.DISCONNECTED
				}
				store.commit('updateParticipant', { token, attendeeId, updatedData })
			}
		},

		/**
		 * Update participants changed in store according to data from standalone signaling server
		 *
		 * @param token the conversation token;
		 * @param participants the changed participant objects;
		 */
		updateParticipantsChangedFromStandaloneSignaling(token: string, participants: StandaloneSignalingChangePayload[]) {
			const guestNameStore = useGuestNameStore()
			const attendeeUsersToUpdate: StandaloneUpdatePayload = {}

			for (const participant of participants) {
				const session = this.findSignalingSession(token, 'ext-change', participant)
				const attendeeId = session?.attendeeId
				if (!attendeeId) {
					continue
				}

				if (!attendeeUsersToUpdate[attendeeId]) {
					attendeeUsersToUpdate[attendeeId] = {
						participantType: participant.participantType,
						permissions: participant.participantPermissions,
						inCall: participant.inCall,
						lastPing: participant.lastPing,
					}
				} else {
					// Participant might join from several devices
					attendeeUsersToUpdate[attendeeId].inCall
						= Math.max(attendeeUsersToUpdate[attendeeId].inCall, participant.inCall)
				}
				if (participant.displayName) {
					attendeeUsersToUpdate[attendeeId].displayName = participant.displayName
					const attendee = store.getters.getParticipant(token, attendeeId) as Participant

					if (attendee.displayName !== participant.displayName
						&& (participant.participantType === PARTICIPANT.TYPE.GUEST
							|| participant.participantType === PARTICIPANT.TYPE.GUEST_MODERATOR)) {
						guestNameStore.addGuestName({
							token,
							actorId: Hex.stringify(SHA1(attendee.sessionIds[0])),
							actorDisplayName: participant.displayName,
						}, { noUpdate: false })
					}
				}
			}

			for (const [attendeeId, updatedData] of Object.entries(attendeeUsersToUpdate)) {
				store.commit('updateParticipant', {
					token,
					attendeeId: +attendeeId,
					updatedData,
				})
			}
		},

		/**
		 * Update participants (end call for everyone) in store according to data from standalone signaling server
		 *
		 * @param token conversation token;
		 */
		updateParticipantsCallDisconnectedFromStandaloneSignaling(token: string) {
			const attendeeUsers = store.getters.participantsList(token) as Participant[]
			for (const attendee of attendeeUsers) {
				store.commit('updateParticipant', {
					token,
					attendeeId: attendee.attendeeId,
					updatedData: { inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED }
				})
			}
		},
	},
})
