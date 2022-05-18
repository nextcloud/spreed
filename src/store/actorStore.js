/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

/**
 * This store helps to identify a the current actor in all cases.
 * In Talk not every user is a local nextcloud user, so identifying
 * solely by userId is not enough.
 * If an as no userId, they are a guest and identified by actorType + sessionId.
 */

import { PARTICIPANT } from '../constants.js'

const state = {
	userId: null,
	sessionId: null,
	attendeeId: null,
	actorId: null,
	actorType: null,
	displayName: '',
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
		context.commit('setActorType', 'users')
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
			context.commit('setActorType', 'guests')
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
}

export default { state, mutations, getters, actions }
