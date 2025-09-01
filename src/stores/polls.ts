import type {
	ChatMessage,
	createPollParams,
	Poll,
	PollDraft,
	updatePollDraftParams,
	votePollParams,
} from '../types/index.ts'

import { showError, showInfo, showSuccess, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import debounce from 'debounce'
import { defineStore } from 'pinia'
import {
	createPoll,
	createPollDraft,
	deletePollDraft,
	endPoll,
	getPollData,
	getPollDrafts,
	submitVote,
	updatePollDraft,
} from '../services/pollService.ts'

type submitVotePayload = { token: string, pollId: string } & Pick<votePollParams, 'optionIds'>
type State = {
	polls: Record<string, Record<string, Poll>>
	drafts: Record<string, Record<string, PollDraft>>
	debouncedFunctions: Record<string, Record<string, () => void>>
	activePoll: { token: string, id: string, name: string } | null
	pollToastsQueue: Record<string, ReturnType<typeof showInfo>>
}
export const usePollsStore = defineStore('polls', {
	state: (): State => ({
		polls: {},
		drafts: {},
		debouncedFunctions: {},
		activePoll: null,
		pollToastsQueue: {},
	}),

	getters: {
		getPoll: (state) => (token: string, pollId: string): Poll => {
			return state.polls[token]?.[pollId]
		},

		getDrafts: (state) => (token: string): PollDraft[] => {
			return Object.values(Object(state.drafts[token]))
		},

		draftsLoaded: (state) => (token: string): boolean => {
			return state.drafts[token] !== undefined
		},

		isNewPoll: (state) => (pollId: number) => {
			return state.pollToastsQueue[pollId] !== undefined
		},
	},

	actions: {
		addPoll({ token, poll }: { token: string, poll: Poll }) {
			if (!this.polls[token]) {
				this.polls[token] = {}
			}
			this.polls[token][poll.id] = poll
		},

		addPollDraft({ token, draft }: { token: string, draft: PollDraft }) {
			if (!this.drafts[token]) {
				this.drafts[token] = {}
			}
			this.drafts[token][draft.id] = draft
		},

		async getPollDrafts(token: string) {
			try {
				const response = await getPollDrafts(token)
				if (response.data.ocs.data.length === 0) {
					this.drafts[token] = {}
					return
				}
				for (const draft of response.data.ocs.data) {
					this.addPollDraft({ token, draft })
				}
			} catch (error) {
				console.error(error)
			}
		},

		deleteDraft({ token, pollId }: { token: string, pollId: string }) {
			if (this.drafts[token]?.[pollId]) {
				delete this.drafts[token][pollId]
			}
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
		 * @param payload The arguments passed to the action
		 * @param payload.token The token of the conversation
		 * @param payload.pollId The id of the poll
		 */
		debounceGetPollData({ token, pollId }: { token: string, pollId: string }) {
			if (!this.debouncedFunctions[token]) {
				this.debouncedFunctions[token] = {}
			}
			// Create the debounced function for getting poll data if not exist yet
			if (!this.debouncedFunctions[token]?.[pollId]) {
				const debouncedFunction = debounce(async () => {
					await this.getPollData({ token, pollId })
				}, 5000)
				this.debouncedFunctions[token][pollId] = debouncedFunction
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

		async createPollDraft({ token, form }: { token: string, form: createPollParams }) {
			try {
				const response = await createPollDraft({ token, ...form })
				this.addPollDraft({ token, draft: response.data.ocs.data })

				showSuccess(t('spreed', 'Poll draft has been saved'))
				return response.data.ocs.data
			} catch (error) {
				showError(t('spreed', 'An error occurred while saving the draft'))
				console.error(error)
			}
		},

		async updatePollDraft({ token, pollId, form }: { token: string, pollId: number, form: updatePollDraftParams }) {
			try {
				const response = await updatePollDraft({ token, pollId, ...form })
				this.addPollDraft({ token, draft: response.data.ocs.data })

				showSuccess(t('spreed', 'Poll draft has been saved'))
				return response.data.ocs.data
			} catch (error) {
				showError(t('spreed', 'An error occurred while saving the draft'))
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

		async deletePollDraft({ token, pollId }: { token: string, pollId: string }) {
			try {
				await deletePollDraft(token, pollId)
				this.deleteDraft({ token, pollId })
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while deleting the poll draft'))
			}
		},

		setActivePoll({ token, pollId, name }: { token: string, pollId: string, name: string }) {
			this.activePoll = { token, id: pollId, name }
		},

		removeActivePoll() {
			if (this.activePoll) {
				this.activePoll = null
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

			this.pollToastsQueue[pollId] = toast
		},

		hidePollToast(pollId: string) {
			if (this.pollToastsQueue[pollId]) {
				this.pollToastsQueue[pollId].hideToast()
				delete this.pollToastsQueue[pollId]
			}
		},

		hideAllPollToasts() {
			for (const pollId in this.pollToastsQueue) {
				this.hidePollToast(pollId)
			}
		},
	},
})
