/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * This store helps to identify a current actor in all cases.
 * In Talk not every user is a local nextcloud user, so identifying
 * solely by userId is not enough.
 * If an as no userId, they are a guest and identified by actorType + sessionId.
 */

import type { NextcloudUser } from '@nextcloud/auth'
import type { Participant } from '../types/index.ts'

import { getCurrentUser } from '@nextcloud/auth'
import { loadState } from '@nextcloud/initial-state'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { ATTENDEE, PARTICIPANT } from '../constants.ts'
import { getTeams } from '../services/teamsService.ts'

export const useActorStore = defineStore('actor', () => {
	const userId = ref<string | null>(null)
	const sessionId = ref<string | null>(null)
	const attendeeId = ref<number | null>(null)
	const actorId = ref<string | null>(null)
	const actorType = ref<string | null>(null)
	const displayName = ref<string>('')
	const actorGroups = ref<string[]>(loadState('spreed', 'user_group_ids', []))
	const actorTeams = ref<string[]>([])

	const isLoggedIn = computed(() => userId.value !== null)
	// TODO check usage for computed below, migrate to isLoggedIn where appropriate
	const isActorUser = computed(() => actorType.value === ATTENDEE.ACTOR_TYPE.USERS)
	const isActorGuest = computed(() => actorType.value === ATTENDEE.ACTOR_TYPE.GUESTS)
	const participantIdentifier = computed(() => ({
		attendeeId: attendeeId.value,
		actorType: actorType.value,
		actorId: actorId.value,
		sessionId: sessionId.value,
	}))

	// Initialize the store
	initialize()

	/**
	 * Initialize the actor store.
	 */
	function initialize() {
		if (getCurrentUser()) {
			console.debug('Setting current user')
			setCurrentUser(getCurrentUser())
			getCurrentUserTeams()
		} else {
			console.debug('Can not set current user because it\'s a guest')
		}
	}

	/**
	 * Check if the actor is a member of a group
	 *
	 * @param groupId The group id
	 */
	function isActorMemberOfGroup(groupId: string) {
		return actorGroups.value.includes(groupId)
	}

	/**
	 * Check if the actor is a member of a team
	 *
	 * @param teamId The team id
	 */
	function isActorMemberOfTeam(teamId: string) {
		return actorTeams.value.includes(teamId)
	}

	/**
	 * Check if the message is from the current actor
	 *
	 * @param payload object to check for
	 * @param payload.actorId
	 * @param payload.actorType
	 */
	function checkIfSelfIsActor(payload: { actorId?: string, actorType?: string }) {
		return payload.actorId === actorId.value
			&& payload.actorType === actorType.value
	}

	/**
	 * Set the display name of the actor
	 *
	 * @param newDisplayName The name to set
	 */
	function setDisplayName(newDisplayName: string) {
		displayName.value = newDisplayName
	}

	/**
	 * Set the actor from the current user
	 *
	 * @param user A NextcloudUser object as returned by @nextcloud/auth
	 * @param user.uid The user id of the user
	 * @param user.displayName The display name of the user
	 */
	function setCurrentUser(user: NextcloudUser | null) {
		if (!user) {
			return
		}
		userId.value = user.uid
		displayName.value = user.displayName || user.uid
		actorType.value = ATTENDEE.ACTOR_TYPE.USERS
		actorId.value = user.uid
	}

	/**
	 * Set the actor from the current participant
	 *
	 * @param participant The participant data
	 * @param participant.attendeeId The attendee id of the participant
	 * @param participant.participantType The type of the participant
	 * @param participant.sessionId The session id of the participant
	 * @param participant.actorId The actor id of the participant
	 */
	function setCurrentParticipant(participant: Participant & { sessionId: string }) {
		sessionId.value = participant.sessionId
		attendeeId.value = participant.attendeeId
		// FIXME other actor types like EMAILS
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
		isLoggedIn,
		isActorUser,
		isActorGuest,
		participantIdentifier,

		isActorMemberOfGroup,
		isActorMemberOfTeam,
		checkIfSelfIsActor,

		initialize,
		setDisplayName,
		setCurrentUser,
		setCurrentParticipant,
		getCurrentUserTeams,
	}
})
