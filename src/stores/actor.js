/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * This store helps to identify a the current actor in all cases.
 * In Talk not every user is a local nextcloud user, so identifying
 * solely by userId is not enough.
 * If an as no userId, they are a guest and identified by actorType + sessionId.
 */

import { loadState } from '@nextcloud/initial-state'
import { defineStore } from 'pinia'
import { ATTENDEE, PARTICIPANT } from '../constants.ts'
import { getTeams } from '../services/teamsService.ts'
import { ref, computed } from 'vue'

export const useActorStore = defineStore('actor', () => {
	const userId = ref(null)
	const sessionId = ref(null)
	const attendeeId = ref(null)
	const actorId = ref(null)
	const actorType = ref(null)
	const displayName = ref('')
	const actorGroups = ref(loadState('spreed', 'user_group_ids', []))
	const actorTeams = ref([])

	const isActorUser = computed(() => actorType.value === ATTENDEE.ACTOR_TYPE.USERS)
	const isActorGuest = computed(() => actorType.value === ATTENDEE.ACTOR_TYPE.GUESTS)
	const getParticipantIdentifier = computed(() => ({
		attendeeId: attendeeId.value,
		actorType: actorType.value,
		actorId: actorId.value,
		sessionId: sessionId.value,
	}))

	/**
	 * Check if the actor is a member of a group
	 *
	 * @param {string} groupId The group id
	 */
	function isActorMemberOfGroup(groupId) {
		return actorGroups.value.includes(groupId)
	}

	/**
	 * Check if the actor is a member of a team
	 *
	 * @param {string} teamId The team id
	 */
	function isActorMemberOfTeam(teamId) {
		return actorTeams.value.includes(teamId)
	}

	/**
	 * Set the userId
	 *
	 * @param {string} displayName The name
	 */
	function setDisplayName(displayName) {
		displayName.value = displayName
	}

	/**
	 * Set the actor from the current user
	 *
	 * @param {object} user A NextcloudUser object as returned by @nextcloud/auth
	 * @param {string} user.uid The user id of the user
	 * @param {string|null} user.displayName The display name of the user
	 */
	function setCurrentUser(user) {
		userId.value = user.uid
		displayName.value = user.displayName || user.uid
		actorType.value = ATTENDEE.ACTOR_TYPE.USERS
		actorId.value = user.uid
	}

	/**
	 * Set the actor from the current participant
	 *
	 * @param {object} participant The participant data
	 * @param {number} participant.attendeeId The attendee id of the participant
	 * @param {number} participant.participantType The type of the participant
	 * @param {string} participant.sessionId The session id of the participant
	 * @param {string} participant.actorId The actor id of the participant
	 */
	function setCurrentParticipant(participant) {
		sessionId.value = participant.sessionId
		attendeeId.value = participant.attendeeId

		if (participant.participantType === PARTICIPANT.TYPE.GUEST
			|| participant.participantType === PARTICIPANT.TYPE.GUEST_MODERATOR) {
			// FIXME displayName.value = ''
			userId.value = null
			actorType.value = ATTENDEE.ACTOR_TYPE.GUESTS
			actorId.value = participant.actorId
		}
	}
	/**
	 * Sets current user teams, if circles app enabled
	 *
	 */
	async function getCurrentUserTeams() {
		if (!loadState('spreed', 'circles_enabled', false)) {
			return
		}

		try {
			const response = await getTeams()
			const teams = response.data.ocs.data.map((team) => team.id)
			actorTeams.value = teams
		} catch (error) {
			console.error(error)
		}
	}

	return {
		userId,
		sessionId,
		attendeeId,
		actorId,
		actorType,
		displayName,
		actorGroups,
		actorTeams,
		isActorUser,
		isActorGuest,
		getParticipantIdentifier,
		
		isActorMemberOfGroup,
		isActorMemberOfTeam,

		setDisplayName,
		setCurrentUser,
		setCurrentParticipant,
		getCurrentUserTeams,
	}
})
