/**
 * @copyright Copyright (c) 2022 Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @author Marco Ambrosini <marcoambrosini@pm.me>
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
import debounce from 'debounce'
import Vue from 'vue'

import { showError, showInfo, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'

import pollService from '../services/pollService.js'

const state = {
	polls: {},
	pollDebounceFunctions: {},
	activePoll: null,
	pollToastsQueue: {},
}

const getters = {
	getPoll: (state) => (token, id) => {
		return state.polls?.[token]?.[id]
	},

	activePoll: (state) => {
		return state.activePoll
	},

	getNewPolls: (state) => {
		return state.pollToastsQueue
	},
}

const mutations = {
	addPoll(state, { token, poll }) {
		if (!state.polls[token]) {
			Vue.set(state.polls, token, {})
		}
		Vue.set(state.polls[token], poll.id, poll)
	},

	setActivePoll(state, { token, pollId, name }) {
		Vue.set(state, 'activePoll', { token, id: pollId, name })
	},

	removeActivePoll(state) {
		if (state.activePoll) {
			Vue.set(state, 'activePoll', null)
		}
	},

	addPollToast(state, { pollId, toast }) {
		Vue.set(state.pollToastsQueue, pollId, toast)
	},

	hidePollToast(state, id) {
		if (state.pollToastsQueue[id]) {
			state.pollToastsQueue[id].hideToast()
			Vue.delete(state.pollToastsQueue, id)
		}
	},

	hideAllPollToasts(state) {
		for (const id in state.pollToastsQueue) {
			state.pollToastsQueue[id].hideToast()
			Vue.delete(state.pollToastsQueue, id)
		}
	},

	// Add debounce function for getting the poll data
	addDebounceGetPollDataFunction(state, { token, pollId, debounceGetPollDataFunction }) {
		if (!state.pollDebounceFunctions[token]) {
			Vue.set(state.pollDebounceFunctions, token, {})
		}
		Vue.set(state.pollDebounceFunctions[token], pollId, debounceGetPollDataFunction)
	},
}

const actions = {
	addPoll(context, { token, poll }) {
		context.commit('addPoll', { token, poll })
	},

	async getPollData(context, { token, pollId }) {
		try {
			const response = await pollService.getPollData(token, pollId)
			const poll = response.data.ocs.data
			context.dispatch('addPoll', { token, poll })
			console.debug('polldata', response)
		} catch (error) {
			console.debug(error)
		}
	},

	/**
	 * In order to limit the amount of requests, we cannot get the
	 * poll data every time someone votes, so we create a debounce
	 * function for each poll and store it in the pollStore
	 *
	 * @param { object } context The store context
	 * @param { object } root0 The arguments passed to the action
	 * @param { string } root0.token The token of the conversation
	 * @param { number }root0.pollId The id of the poll
	 */
	debounceGetPollData(context, { token, pollId }) {
		// Create debounce function for getting this particular poll data
		// if it does not exist yet
		if (!context.state.pollDebounceFunctions[token]?.[pollId]) {
			const debounceGetPollDataFunction = debounce(async () => {
				await context.dispatch('getPollData', {
					token,
					pollId,
				})
			}, 5000)
			// Add the debounce function to the state object
			context.commit('addDebounceGetPollDataFunction', {
				token,
				pollId,
				debounceGetPollDataFunction,
			})
		}
		// Call the debounce function for getting the poll data
		context.state.pollDebounceFunctions[token][pollId]()
	},

	async submitVote(context, { token, pollId, vote }) {
		console.debug('Submitting vote')
		try {
			const response = await pollService.submitVote(token, pollId, vote)
			const poll = response.data.ocs.data
			context.dispatch('addPoll', { token, poll })
		} catch (error) {
			console.error(error)
			showError(t('spreed', 'An error occurred while submitting your vote'))
		}
	},

	async endPoll(context, { token, pollId }) {
		console.debug('Ending poll')
		try {
			const response = await pollService.endPoll(token, pollId)
			const poll = response.data.ocs.data
			context.dispatch('addPoll', { token, poll })
		} catch (error) {
			console.error(error)
			showError(t('spreed', 'An error occurred while ending the poll'))
		}
	},

	setActivePoll(context, { token, pollId, name }) {
		context.commit('setActivePoll', { token, pollId, name })
	},

	removeActivePoll(context) {
		context.commit('removeActivePoll')
	},

	addPollToast(context, { token, message }) {
		const pollId = message.messageParameters.object.id
		const name = message.messageParameters.object.name

		const toast = showInfo(t('spreed', 'Poll "{name}" was created by {user}. Click to vote', {
			name,
			user: message.actorDisplayName,
		}), {
			onClick: () => {
				if (!context.state.activePoll) {
					context.dispatch('setActivePoll', { token, pollId, name })
				}
			},
			timeout: TOAST_PERMANENT_TIMEOUT,
		})

		context.commit('addPollToast', { pollId, toast })
	},

	hidePollToast(context, id) {
		context.commit('hidePollToast', id)
	},

	hideAllPollToasts(context) {
		context.commit('hideAllPollToasts')
	},
}

export default { state, mutations, getters, actions }
