/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import flushPromises from 'flush-promises'
import { setActivePinia, createPinia } from 'pinia'

import { ATTENDEE } from '../../constants.ts'
import {
	createPoll,
	createPollDraft,
	getPollData,
	getPollDrafts,
	submitVote,
	endPoll,
} from '../../services/pollService.ts'
import { generateOCSResponse } from '../../test-helpers.js'
import { usePollsStore } from '../polls.ts'

jest.mock('../../services/pollService', () => ({
	createPoll: jest.fn(),
	createPollDraft: jest.fn(),
	getPollData: jest.fn(),
	getPollDrafts: jest.fn(),
	submitVote: jest.fn(),
	endPoll: jest.fn(),
	deletePollDraft: jest.fn(),
}))

describe('pollsStore', () => {
	let pollsStore
	const TOKEN = 'TOKEN'
	const pollRequest = {
		question: 'What is the answer to the universe?',
		options: ['42', '24'],
		resultMode: 0,
		maxVotes: 1,
	}
	const pollDraft = {
		...pollRequest,
		status: 2,
		id: 1,
		actorType: ATTENDEE.ACTOR_TYPE.USERS,
		actorId: 'user',
		actorDisplayName: 'User',
	}
	const poll = {
		...pollRequest,
		id: 1,
		votes: [],
		numVoters: 0,
		actorType: ATTENDEE.ACTOR_TYPE.USERS,
		actorId: 'user',
		actorDisplayName: 'User',
		status: 0,
		votedSelf: [],
	}
	const pollWithVote = {
		...poll,
		votes: { 'option-0': 1 },
		numVoters: 1,
		votedSelf: [0],
	}
	const pollWithVoteEnded = {
		...pollWithVote,
		status: 1,
		details: [
			{
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				actorId: 'user',
				actorDisplayName: 'User',
				optionId: 0,
			},
		],
	}
	const messageWithPoll = {
		id: 123,
		token: TOKEN,
		actorType: ATTENDEE.ACTOR_TYPE.USERS,
		actorId: 'user',
		actorDisplayName: 'User',
		message: '{object}',
		messageType: 'comment',
		messageParameters: {
			actor: {
				type: 'user',
				id: 'user',
				name: 'User',
			},
			object: {
				type: 'talk-poll',
				id: poll.id,
				name: poll.question,
			},
		},
	}

	beforeEach(async () => {
		setActivePinia(createPinia())
		pollsStore = usePollsStore()
	})

	describe('polls management', () => {
		it('receives a poll from server and adds it to the store', async () => {
			// Arrange
			const response = generateOCSResponse({ payload: poll })
			getPollData.mockResolvedValue(response)

			// Act
			await pollsStore.getPollData({ token: TOKEN, pollId: poll.id })

			// Assert
			expect(pollsStore.getPoll(TOKEN, poll.id)).toMatchObject(poll)
		})

		it('debounces a function to get a poll from server', async () => {
			// Arrange
			jest.useFakeTimers()
			const response = generateOCSResponse({ payload: poll })
			getPollData.mockResolvedValue(response)

			// Act
			pollsStore.debounceGetPollData({ token: TOKEN, pollId: poll.id })
			jest.advanceTimersByTime(5000)
			await flushPromises()

			// Assert
			expect(pollsStore.debouncedFunctions[TOKEN][poll.id]).toBeDefined()
			expect(pollsStore.getPoll(TOKEN, poll.id)).toMatchObject(poll)
		})

		it('creates a poll and adds it to the store', async () => {
			// Arrange
			const response = generateOCSResponse({ payload: poll })
			createPoll.mockResolvedValue(response)

			// Act
			await pollsStore.createPoll({ token: TOKEN, form: pollRequest })

			// Assert
			expect(pollsStore.getPoll(TOKEN, poll.id)).toMatchObject(poll)
		})

		it('submits a vote and updates it in the store', async () => {
			// Arrange
			pollsStore.addPoll({ token: TOKEN, poll })
			const response = generateOCSResponse({ payload: pollWithVote })
			submitVote.mockResolvedValue(response)

			// Act
			await pollsStore.submitVote({ token: TOKEN, pollId: poll.id, optionIds: [0] })

			// Assert
			expect(pollsStore.getPoll(TOKEN, poll.id)).toMatchObject(pollWithVote)
		})

		it('ends a poll and updates it in the store', async () => {
			// Arrange
			pollsStore.addPoll({ token: TOKEN, poll: pollWithVote })
			const response = generateOCSResponse({ payload: pollWithVoteEnded })
			endPoll.mockResolvedValue(response)

			// Act
			await pollsStore.endPoll({ token: TOKEN, pollId: poll.id })

			// Assert
			expect(pollsStore.getPoll(TOKEN, poll.id)).toMatchObject(pollWithVoteEnded)
		})
	})

	describe('drafts management', () => {
		it('receives drafts from server and adds them to the store', async () => {
			// Arrange
			const response = generateOCSResponse({ payload: [pollDraft] })
			getPollDrafts.mockResolvedValue(response)

			// Act
			await pollsStore.getPollDrafts(TOKEN)

			// Assert
			expect(pollsStore.getDrafts(TOKEN)).toMatchObject([pollDraft])
		})

		it('receives no drafts from server', async () => {
			// Arrange
			const response = generateOCSResponse({ payload: [] })
			getPollDrafts.mockResolvedValue(response)

			// Act
			await pollsStore.getPollDrafts(TOKEN)

			// Assert
			expect(pollsStore.getDrafts(TOKEN)).toMatchObject([])
		})

		it('creates a draft and adds it to the store', async () => {
			// Arrange
			const response = generateOCSResponse({ payload: pollDraft })
			createPollDraft.mockResolvedValue(response)

			// Act
			await pollsStore.createPollDraft({ token: TOKEN, form: pollRequest })

			// Assert
			expect(pollsStore.getDrafts(TOKEN, poll.id)).toMatchObject([pollDraft])
		})

		it('deletes a draft from the store', async () => {
			// Arrange
			pollsStore.addPollDraft({ token: TOKEN, draft: pollDraft })

			// Act
			await pollsStore.deletePollDraft({ token: TOKEN, pollId: pollDraft.id })

			// Assert
			expect(pollsStore.getDrafts(TOKEN, poll.id)).toMatchObject([])
		})
	})

	describe('poll toasts in call', () => {
		it('adds poll toast to the queue from message', async () => {
			// Act
			pollsStore.addPollToast({ token: TOKEN, message: messageWithPoll })

			// Assert
			expect(pollsStore.isNewPoll(poll.id)).toBeTruthy()
		})

		it('sets active poll from the toast', async () => {
			// Arrange
			pollsStore.addPollToast({ token: TOKEN, message: messageWithPoll })

			// Act
			pollsStore.pollToastsQueue[poll.id].options.onClick()

			// Assert
			expect(pollsStore.activePoll).toMatchObject({ token: TOKEN, id: poll.id, name: poll.question })
		})

		it('removes active poll', async () => {
			// Arrange
			pollsStore.setActivePoll({ token: TOKEN, pollId: poll.id, name: poll.question })

			// Act
			pollsStore.removeActivePoll()

			// Assert
			expect(pollsStore.activePoll).toEqual(null)
		})

		it('hides all poll toasts', async () => {
			// Arrange
			pollsStore.addPollToast({ token: TOKEN, message: messageWithPoll })

			// Act
			pollsStore.hideAllPollToasts()

			// Assert
			expect(pollsStore.pollToastsQueue).toMatchObject({})
		})
	})
})
