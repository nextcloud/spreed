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
import { ATTENDEE, PARTICIPANT } from '../constants.ts'
import { getTeams } from '../services/teamsService.ts'

const state = {
	userId: null,
	sessionId: null,
	attendeeId: null,
	actorId: null,
	actorType: null,
	displayName: '',
	actorGroups: loadState('spreed', 'user_group_ids', []),
	actorTeams: [],
}

const getters = {
	getUserId: (state) => () => {
		return state.userId
	},
	getSessionId: (state) => () => {
		return state.sessionId
	},
	getAttendeeId: (state) => () => {
		return state.attendeeId
	},
	getActorId: (state) => () => {
		return state.actorId
	},
	getActorType: (state) => () => {
		return state.actorType
	},
	isActorUser: (state) => () => {
		return state.actorType === ATTENDEE.ACTOR_TYPE.USERS
	},
	isActorGuest: (state) => () => {
		return state.actorType === ATTENDEE.ACTOR_TYPE.GUESTS
	},
	isActorMemberOfGroup: (state) => (groupId) => {
		return state.actorGroups.includes(groupId)
	},
	isActorMemberOfTeam: (state) => (teamId) => {
		return state.actorTeams.includes(teamId)
	},
	getDisplayName: (state) => () => {
		return state.displayName
	},
	getParticipantIdentifier: (state) => () => {
		return {
			attendeeId: state.attendeeId,
			actorType: state.actorType,
			actorId: state.actorId,
			sessionId: state.sessionId,
		}
	},
}

const mutations = {
	/**
	 * Set the userId
	 *
	 * @param {object} state current store state;
	 * @param {string} userId The user id
	 */
	setUserId(state, userId) {
		state.userId = userId
		state.actorId = userId
	},
	/**
	 * Set the attendeeId
	 *
	 * @param {object} state current store state;
	 * @param {string} attendeeId The actors attendee id
	 */
	setAttendeeId(state, attendeeId) {
		state.attendeeId = attendeeId
	},
	/**
	 * Set the sessionId
	 *
	 * @param {object} state current store state;
	 * @param {string} sessionId The actors session id
	 */
	setSessionId(state, sessionId) {
		state.sessionId = sessionId
	},
	/**
	 * Set the actorId
	 *
	 * @param {object} state current store state;
	 * @param {string} actorId The actor id
	 */
	setActorId(state, actorId) {
		state.actorId = actorId
	},
	/**
	 * Set the userId
	 *
	 * @param {object} state current store state;
	 * @param {string} displayName The name
	 */
	setDisplayName(state, displayName) {
		state.displayName = displayName
	},
	/**
	 * Set the userId
	 *
	 * @param {object} state current store state;
	 * @param {actorType} actorType The actor type of the user
	 */
	setActorType(state, actorType) {
		state.actorType = actorType
	},
	/**
	 * Set the user teams ids
	 *
	 * @param {object} state current store state;
	 * @param {Array} teams Teams ids of the current user
	 */
	setCurrentUserTeams(state, teams) {
		state.actorTeams = teams
	},
}

const actions = {

	/**
	 * Set the actor from the current user
	 *
	 * @param {object} context default store context;
	 * @param {object} user A NextcloudUser object as returned by @nextcloud/auth
	 * @param {string} user.uid The user id of the user
	 * @param {string|null} user.displayName The display name of the user
	 */
	setCurrentUser(context, user) {
		context.commit('setUserId', user.uid)
		context.commit('setDisplayName', user.displayName || user.uid)
		context.commit('setActorType', ATTENDEE.ACTOR_TYPE.USERS)
		context.commit('setActorId', user.uid)
	},

	/**
	 * Set the actor from the current participant
	 *
	 * @param {object} context default store context;
	 * @param {object} participant The participant data
	 * @param {number} participant.attendeeId The attendee id of the participant
	 * @param {number} participant.participantType The type of the participant
	 * @param {string} participant.sessionId The session id of the participant
	 * @param {string} participant.actorId The actor id of the participant
	 */
	setCurrentParticipant(context, participant) {
		context.commit('setSessionId', participant.sessionId)
		context.commit('setAttendeeId', participant.attendeeId)

		if (participant.participantType === PARTICIPANT.TYPE.GUEST
			|| participant.participantType === PARTICIPANT.TYPE.GUEST_MODERATOR) {
			context.commit('setUserId', null)
			context.commit('setActorType', ATTENDEE.ACTOR_TYPE.GUESTS)
			context.commit('setActorId', participant.actorId)
			// FIXME context.commit('setDisplayName', '')
		}
	},
	/**
	 * Sets displayName only, we currently use this for guests user names.
	 *
	 * @param {object} context default store context;
	 * @param {string} displayName the display name to be set;
	 */
	setDisplayName(context, displayName) {
		context.commit('setDisplayName', displayName)
	},
	/**
	 * Sets current user teams, if circles app enabled
	 *
	 * @param {object} context default store context;
	 */
	async getCurrentUserTeams(context) {
		if (!loadState('spreed', 'circles_enabled', false)) {
			return
		}

		try {
			const response = await getTeams()
			const teams = response.data.ocs.data.map((team) => team.id)
			context.commit('setCurrentUserTeams', teams)
		} catch (error) {
			console.error(error)
		}
	},
}

export default { state, mutations, getters, actions }
