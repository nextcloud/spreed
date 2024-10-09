/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import debounce from 'debounce'
import { defineStore } from 'pinia'
import Vue from 'vue'

import { showError, showInfo, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import {
	createPoll,
	getPollData,
	submitVote,
	endPoll,
} from '../services/pollService.ts'
import type {
	ChatMessage,
	createPollParams,
	Poll, votePollParams
} from '../types/index.ts'

type createPollPayload = { token: string } & createPollParams
type submitVotePayload = { token: string, pollId: string } & Pick<votePollParams, 'optionIds'>
type State = {
	polls: Record<string, Record<string, Poll>>,
	debouncedFunctions: Record<string, Record<string, () => void>>,
	activePoll: null,
	pollToastsQueue: Record<string, ReturnType<typeof showInfo>>,
}
export const usePollsStore = defineStore('polls', {
	state: (): State => ({
		polls: {},
		debouncedFunctions: {},
		activePoll: null,
		pollToastsQueue: {},
	}),

	getters: {
		getPoll: (state) => (token: string, pollId: string): Poll => {
			return state.polls[token]?.[pollId]
		},

		isNewPoll: (state) => (pollId: number) => {
			return state.pollToastsQueue[pollId] !== undefined
		},
	},

	actions: {
		addPoll({ token, poll }: { token: string, poll: Poll }) {
			if (!this.polls[token]) {
				Vue.set(this.polls, token, {})
			}
			Vue.set(this.polls[token], poll.id, poll)
		},

		async getPollData({ token, pollId }: { token: string, pollId: string }) {
			try {
				const response = await getPollData(token, pollId)
				this.addPoll({ token, poll: response.data.ocs.data })
			} catch (error) {
				console.error(error)
			}
		},

		/**
		 * In order to limit the amount of requests, we cannot get the
		 * poll data every time someone votes, so we create a debounce
		 * function for each poll and store it in the pollStore
		 *
		 * @param { object } root0 The arguments passed to the action
		 * @param { string } root0.token The token of the conversation
		 * @param { number } root0.pollId The id of the poll
		 */
		debounceGetPollData({ token, pollId }: { token: string, pollId: string }) {
			if (!this.debouncedFunctions[token]) {
				Vue.set(this.debouncedFunctions, token, {})
			}
			// Create the debounced function for getting poll data if not exist yet
			if (!this.debouncedFunctions[token]?.[pollId]) {
				const debouncedFunction = debounce(async () => {
					await this.getPollData({ token, pollId })
				}, 5000)
				Vue.set(this.debouncedFunctions[token], pollId, debouncedFunction)
			}
			// Call the debounced function for getting the poll data
			this.debouncedFunctions[token][pollId]()
		},

		async createPoll({ token, form }: { token: string, form: createPollParams }) {
			try {
				const response = await createPoll({ token, ...form })
				this.addPoll({ token, poll: response.data.ocs.data })

				return response.data.ocs.data
			} catch (error) {
				console.error(error)
			}
		},

		async submitVote({ token, pollId, optionIds }: submitVotePayload) {
			try {
				const response = await submitVote(token, pollId, optionIds)
				this.addPoll({ token, poll: response.data.ocs.data })
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while submitting your vote'))
			}
		},

		async endPoll({ token, pollId }: { token: string, pollId: string }) {
			try {
				const response = await endPoll(token, pollId)
				this.addPoll({ token, poll: response.data.ocs.data })
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while ending the poll'))
			}
		},

		setActivePoll({ token, pollId, name }: { token: string, pollId: string, name: string }) {
			Vue.set(this, 'activePoll', { token, id: pollId, name })
		},

		removeActivePoll() {
			if (this.activePoll) {
				Vue.set(this, 'activePoll', null)
			}
		},

		addPollToast({ token, message }: { token: string, message: ChatMessage }) {
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

		hidePollToast(pollId: string) {
			if (this.pollToastsQueue[pollId]) {
				this.pollToastsQueue[pollId].hideToast()
				Vue.delete(this.pollToastsQueue, pollId)
			}
		},

		hideAllPollToasts() {
			for (const pollId in this.pollToastsQueue) {
				this.hidePollToast(pollId)
			}
		},
	},
})
