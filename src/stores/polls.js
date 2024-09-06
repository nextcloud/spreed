/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import debounce from 'debounce'
import { defineStore } from 'pinia'
import Vue from 'vue'

import { showError, showInfo, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import pollService from '../services/pollService.js'

export const usePollsStore = defineStore('polls', {
	state: () => ({
		polls: {},
		pollDebounceFunctions: {},
		activePoll: null,
		pollToastsQueue: {},
	}),

	getters: {
		getPoll: (state) => (token, id) => {
			return state.polls?.[token]?.[id]
		},

		activePoll: (state) => {
			return state.activePoll
		},

		getNewPolls: (state) => {
			return state.pollToastsQueue
		},
	},

	actions: {
		addPoll({ token, poll }) {
			if (!this.polls[token]) {
				Vue.set(this.polls, token, {})
			}
			Vue.set(this.polls[token], poll.id, poll)
		},

		async getPollData({ token, pollId }) {
			try {
				const response = await pollService.getPollData(token, pollId)
				const poll = response.data.ocs.data
				this.addPoll({ token, poll })
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
		 * @param { object } root0 The arguments passed to the action
		 * @param { string } root0.token The token of the conversation
		 * @param { number }root0.pollId The id of the poll
		 */
		debounceGetPollData({ token, pollId }) {
			// Create debounce function for getting this particular poll data
			// if it does not exist yet
			if (!this.pollDebounceFunctions[token]?.[pollId]) {
				const debounceGetPollDataFunction = debounce(async () => {
					await this.getPollData({
						token,
						pollId,
					})
				}, 5000)
				// Add the debounce function to the state object
				if (!this.pollDebounceFunctions[token]) {
					Vue.set(this.pollDebounceFunctions, token, {})
				}
				Vue.set(this.pollDebounceFunctions[token], pollId, debounceGetPollDataFunction)
			}
			// Call the debounce function for getting the poll data
			this.pollDebounceFunctions[token][pollId]()
		},

		async submitVote({ token, pollId, vote }) {
			console.debug('Submitting vote')
			try {
				const response = await pollService.submitVote(token, pollId, vote)
				const poll = response.data.ocs.data
				this.addPoll({ token, poll })
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while submitting your vote'))
			}
		},

		async endPoll({ token, pollId }) {
			console.debug('Ending poll')
			try {
				const response = await pollService.endPoll(token, pollId)
				const poll = response.data.ocs.data
				this.addPoll({ token, poll })
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while ending the poll'))
			}
		},

		setActivePoll({ token, pollId, name }) {
			Vue.set(this, 'activePoll', { token, id: pollId, name })
		},

		removeActivePoll() {
			if (this.activePoll) {
				Vue.set(this, 'activePoll', null)
			}
		},

		addPollToast({ token, message }) {
			const pollId = message.messageParameters.object.id
			const name = message.messageParameters.object.name

			const toast = showInfo(t('spreed', 'Poll "{name}" was created by {user}. Click to vote', {
				name,
				user: message.actorDisplayName,
			}), {
				onClick: () => {
					if (!this.activePoll) {
						this.setActivePoll({ token, pollId, name })
					}
				},
				timeout: TOAST_PERMANENT_TIMEOUT,
			})

			Vue.set(this.pollToastsQueue, pollId, toast)
		},

		hidePollToast(id) {
			if (this.pollToastsQueue[id]) {
				this.pollToastsQueue[id].hideToast()
				Vue.delete(this.pollToastsQueue, id)
			}
		},

		hideAllPollToasts() {
			for (const id in this.pollToastsQueue) {
				this.pollToastsQueue[id].hideToast()
				Vue.delete(this.pollToastsQueue, id)
			}
		},
	},
})
