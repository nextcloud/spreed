/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed } from 'vue'

import { useStore } from './useStore.js'
import { ATTENDEE, PARTICIPANT } from '../constants.js'
import { isDoNotDisturb } from '../utils/userStatus.ts'

const MODERATOR_TYPES = [PARTICIPANT.TYPE.OWNER, PARTICIPANT.TYPE.MODERATOR, PARTICIPANT.TYPE.GUEST_MODERATOR]

/**
 * Handler for participants lists sorting
 */
export function useSortParticipants() {
	const store = useStore()

	const selfIsModerator = computed(() => {
		const participantType = store.getters.conversation(store.getters.getToken())?.participantType
		return MODERATOR_TYPES.includes(participantType)
	})

	/**
	 * Sort two participants by:
	 * - participants before groups
	 * - online status
	 * - in call
	 * - who raised hand first
	 * - type (moderators before normal participants)
	 * - user status (dnd at the end)
	 * - display name
	 *
	 * @param {object} participant1 First participant
	 * @param {number} participant1.participantType First participant type
	 * @param {string} participant1.sessionIds First participant sessions array
	 * @param {string} participant1.displayName First participant display name
	 * @param {string} participant1.status First participant user status
	 * @param {string} participant1.actorType First participant actor type
	 * @param {number} participant1.inCall First participant in call flag
	 * @param {number} participant1.attendeePermissions First participant attendee permissions
	 *
	 * @param {object} participant2 Second participant
	 * @param {number} participant2.participantType Second participant type
	 * @param {string} participant2.sessionIds Second participant sessions array
	 * @param {string} participant2.displayName Second participant display name
	 * @param {string} participant2.actorType Second participant actor type
	 * @param {string} participant2.status Second participant user status
	 * @param {number} participant2.inCall Second participant in call flag
	 * @param {number} participant2.attendeePermissions Second participant attendee permissions
	 * @return {number}
	 */
	function sortParticipants(participant1, participant2) {
		const p1IsCircle = participant1.actorType === ATTENDEE.ACTOR_TYPE.CIRCLES
		const p2IsCircle = participant2.actorType === ATTENDEE.ACTOR_TYPE.CIRCLES

		if (p1IsCircle !== p2IsCircle) {
			// Circles below participants, phones and groups
			return p2IsCircle ? -1 : 1
		}

		const p1IsGroup = participant1.actorType === ATTENDEE.ACTOR_TYPE.GROUPS
		const p2IsGroup = participant2.actorType === ATTENDEE.ACTOR_TYPE.GROUPS

		if (p1IsGroup !== p2IsGroup) {
			// Groups below participants and phones
			return p2IsGroup ? -1 : 1
		}

		const hasSessions1 = !!participant1.sessionIds.length
		const hasSessions2 = !!participant2.sessionIds.length
		/**
		 * For now the user status is not overwriting the online-offline status anymore
		 * It felt too weird having users appear as offline but they are in the call or chat actively
			if (participant1.status === 'offline') {
				hasSessions1 = false
			}
			if (participant2.status === 'offline') {
				hasSessions2 = false
			}
		 */
		if (hasSessions1 !== hasSessions2) {
			return hasSessions1 ? -1 : 1
		}

		const p1IsPhone = participant1.actorType === ATTENDEE.ACTOR_TYPE.PHONES
		const p2IsPhone = participant2.actorType === ATTENDEE.ACTOR_TYPE.PHONES

		if (p1IsPhone !== p2IsPhone) {
			// Phones below online participants and above offline participants
			return p1IsPhone ? -1 : 1
		}

		const p1inCall = participant1.inCall !== PARTICIPANT.CALL_FLAG.DISCONNECTED
		const p2inCall = participant2.inCall !== PARTICIPANT.CALL_FLAG.DISCONNECTED
		if (p1inCall !== p2inCall) {
			return p1inCall ? -1 : 1
		}

		const p1HandRaised = store.getters.getParticipantRaisedHand(participant1.sessionIds)
		const p2HandRaised = store.getters.getParticipantRaisedHand(participant2.sessionIds)
		if (p1HandRaised.state !== p2HandRaised.state) {
			return p1HandRaised.state ? -1 : 1
		}
		// both had raised hands, then pick whoever raised hand first
		if (p1HandRaised) {
			// use MAX_VALUE if not defined to avoid zeroes making it look like
			// one raised their hands at the birth of time...
			const t1 = p1HandRaised.timestamp || Number.MAX_VALUE
			const t2 = p2HandRaised.timestamp || Number.MAX_VALUE
			if (t1 !== t2) {
				return t1 - t2
			}
		}

		const moderator1 = MODERATOR_TYPES.includes(participant1.participantType)
		const moderator2 = MODERATOR_TYPES.includes(participant2.participantType)

		if (moderator1 !== moderator2) {
			return moderator1 ? -1 : 1
		}

		if (selfIsModerator.value && participant1.attendeePermissions !== participant2.attendeePermissions) {
			return participant2.attendeePermissions - participant1.attendeePermissions
		}
		const participant1Away = isDoNotDisturb(participant1)
		const participant2Away = isDoNotDisturb(participant2)
		if (participant1Away !== participant2Away) {
			return participant1Away ? 1 : -1
		}

		const p1IsGuest = participant1.actorType === ATTENDEE.ACTOR_TYPE.GUESTS
		const p2IsGuest = participant2.actorType === ATTENDEE.ACTOR_TYPE.GUESTS

		if (p1IsGuest !== p2IsGuest) {
			// Guests below participants
			return p2IsGuest ? -1 : 1
		}

		return participant1.displayName.localeCompare(participant2.displayName)
	}

	return {
		sortParticipants,
	}
}
