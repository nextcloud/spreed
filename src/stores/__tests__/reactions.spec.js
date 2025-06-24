import { showError } from '@nextcloud/dialogs'
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createPinia, setActivePinia } from 'pinia'
import { addReactionToMessage, getReactionsDetails, removeReactionFromMessage } from '../../services/reactionsService.ts'
import vuexStore from '../../store/index.js'
import { generateOCSErrorResponse, generateOCSResponse } from '../../test-helpers.js'
import { useReactionsStore } from '../reactions.js'

jest.mock('../../services/reactionsService', () => ({
	getReactionsDetails: jest.fn(),
	addReactionToMessage: jest.fn(),
	removeReactionFromMessage: jest.fn(),
}))

describe('reactionsStore', () => {
	let reactionsStore
	let token
	let messageId
	let reactions

	beforeEach(() => {
		setActivePinia(createPinia())
		reactionsStore = useReactionsStore()

		token = 'token1'
		messageId = 'parent-id'
		reactions = {
			'🎄': [
				{ displayName: 'user1', actorId: 'actorId1', actorType: 'users' },
				{ displayName: 'user2', actorId: 'actorId2', actorType: 'users' },
			],
			'🔥': [
				{ displayName: 'user3', actorId: 'actorId3', actorType: 'users' },
				{ displayName: 'user4', actorId: 'actorId4', actorType: 'users' },
			],
			'🔒': [
				{ displayName: 'user3', actorId: 'actorId3', actorType: 'users' },
				{ displayName: 'user4', actorId: 'actorId4', actorType: 'users' },
			],
		}

		reactionsStore.updateReactions({
			token,
			messageId,
			reactionsDetails: reactions,
		})
	})

	afterEach(() => {
		jest.clearAllMocks()
		reactionsStore.resetReactions(token, messageId)
	})

	it('fetches reactions from the store', () => {
		// Arrange
		const response = generateOCSResponse({ payload: reactions })
		getReactionsDetails.mockResolvedValue(response)
		console.debug = jest.fn()

		// Act
		reactionsStore.fetchReactions()

		// Assert
		expect(reactionsStore.getReactions(token, messageId)).toEqual(reactions)
	})
	it('does not update reactions in the store twice', () => {
		// Arrange
		const newReactions = {
			'🎄': [
				{ actorDisplayName: 'user1', actorId: 'actorId1', actorType: 'users' },
			],
			'😅': [
				{ actorDisplayName: 'user1', actorId: 'actorId1', actorType: 'users' },
				{ actorDisplayName: 'user2', actorId: 'actorId2', actorType: 'users' },
			],
			'💜': [
				{ actorDisplayName: 'user3', actorId: 'actorId3', actorType: 'users' },
				{ actorDisplayName: 'user4', actorId: 'actorId4', actorType: 'users' },
			],
		}
		// Act
		reactionsStore.updateReactions({
			token,
			messageId,
			reactionsDetails: newReactions,
		})
		reactionsStore.updateReactions({
			token,
			messageId,
			reactionsDetails: newReactions,
		})

		// Assert
		expect(reactionsStore.getReactions(token, messageId)).toEqual(newReactions)
	})
	it('resets the store when there is no reaction', () => {
		// Arrange
		const emptyReactions = {}
		jest.spyOn(reactionsStore, 'resetReactions')
		// Act
		reactionsStore.updateReactions({
			token,
			messageId,
			reactionsDetails: emptyReactions,
		})
		// Assert
		expect(reactionsStore.getReactions(token, messageId)).toEqual(undefined)
	})
	it('adds reaction from system messages', () => {
		// Arrange
		const message = {
			systemMessage: 'reaction',
			actorDisplayName: 'Test Actor',
			actorId: '123',
			actorType: 'user',
			timestamp: Date.now(),
			token,
			parent: {
				id: messageId,
			},
			message: '😅',
		}
		expect(Object.keys(reactionsStore.getReactions(token, messageId))).toEqual(['🎄', '🔥', '🔒'])
		// Act
		reactionsStore.processReaction(token, message)

		// Assert
		expect(Object.keys(reactionsStore.getReactions(token, messageId))).toContain('😅')
	})
	it('does not add a reaction actor when it already exists', () => {
		// Arrange
		const message = {
			systemMessage: 'reaction',
			actorDisplayName: 'Test Actor',
			actorId: 'actorId1',
			actorType: 'users',
			timestamp: Date.now(),
			token,
			parent: {
				id: messageId,
			},
			message: '🎄',
		}
		expect(Object.keys(reactionsStore.getReactions(token, messageId))).toEqual(['🎄', '🔥', '🔒'])
		// Act
		reactionsStore.processReaction(token, message)

		// Assert
		const actors = reactionsStore.getReactions(token, messageId)['🎄']
		expect(actors.length).toEqual(2) // should not add a new actor
	})
	it('removes a reaction from the store', async () => {
		// Arrange
		const message = {
			systemMessage: 'reaction_revoked',
			actorDisplayName: 'Test Actor',
			actorId: '123',
			actorType: 'user',
			timestamp: Date.now(),
			token,
			parent: {
				id: messageId,
			},
			message: 'reaction removed',
		}
		const actualReactions = {
			'🎄': [
				{ displayName: 'user1', actorId: 'actorId1' },
				{ displayName: 'user2', actorId: 'actorId2' },
			],
			'🔥': [
				{ displayName: 'user3', actorId: 'actorId3' },
				{ displayName: 'user4', actorId: 'actorId4' },
			],
		}
		const response = generateOCSResponse({ payload: actualReactions })
		getReactionsDetails.mockResolvedValue(response)
		jest.spyOn(reactionsStore, 'removeReaction')
		jest.spyOn(reactionsStore, 'fetchReactions')
		console.debug = jest.fn()

		// Act
		await reactionsStore.processReaction(token, message)

		// Assert
		expect(getReactionsDetails).toHaveBeenCalled()
		expect(Object.keys(reactionsStore.reactions[token][messageId])).toEqual(['🎄', '🔥'])
	})
	it('purges the reactions store', () => {
		// Act
		reactionsStore.purgeReactionsStore(token)
		// Assert
		expect(reactionsStore.getReactions(token, messageId)).toEqual(undefined)
	})
	it('does not fetch reactions when receiving a reaction_deleted system message', async () => {
		// Arrange
		const message = {
			systemMessage: 'reaction_deleted',
			actorDisplayName: 'Test Actor',
			actorId: '123',
			actorType: 'user',
			timestamp: Date.now(),
			token,
			parent: {
				id: messageId,
			},
			message: 'reaction removed',
		}

		// Act
		await reactionsStore.processReaction(token, message)

		// Assert
		expect(getReactionsDetails).not.toHaveBeenCalled()
	})

	describe('error handling', () => {
		it('does not add a reaction when the API call fails', async () => {
			// Arrange
			const errorResponse = generateOCSErrorResponse({ status: 500, payload: [] })
			addReactionToMessage.mockResolvedValue(errorResponse)
			jest.spyOn(vuexStore, 'commit')

			const message = {
				actorId: 'admin',
				actorType: 'users',
				id: messageId,
				markdown: true,
				message: 'This is a message to have reactions on',
				reactions: { '🎄': 2, '🔥': 2, '🔒': 2 },
				reactionsSelf: ['🔥'],
				timestamp: 1703668230,
				token,
			}
			vuexStore.commit('addMessage', { token, message }) // add a message to the store

			// Act
			await reactionsStore.addReactionToMessage({ token, messageId, selectedEmoji: '😅' })

			// Assert
			expect(vuexStore.commit).toHaveBeenNthCalledWith(2, 'addReactionToMessage', {
				token,
				messageId,
				reaction: '😅',
			})
			expect(showError).toHaveBeenCalled()
			expect(vuexStore.commit).toHaveBeenNthCalledWith(3, 'removeReactionFromMessage', {
				token,
				messageId,
				reaction: '😅',
			})
			expect(Object.keys(reactionsStore.getReactions(token, messageId))).toEqual(['🎄', '🔥', '🔒']) // no reaction added
		})
		it('does not remove a reaction when the API call fails', async () => {
			// Arrange
			const errorResponse = generateOCSErrorResponse({ status: 500, payload: [] })
			removeReactionFromMessage.mockResolvedValue(errorResponse)
			jest.spyOn(vuexStore, 'commit')
			console.error = jest.fn()

			const message = {
				actorId: 'admin',
				actorType: 'users',
				id: messageId,
				markdown: true,
				message: 'This is a message to have reactions on',
				reactions: { '🎄': 2, '🔥': 2, '🔒': 2 },
				reactionsSelf: ['🔥'],
				timestamp: 1703668230,
				token,
			}

			vuexStore.commit('addMessage', { token, message }) // add a message to the store

			// Act
			await reactionsStore.removeReactionFromMessage({ token, messageId, selectedEmoji: '🎄' })

			// Assert
			expect(vuexStore.commit).toHaveBeenNthCalledWith(2, 'removeReactionFromMessage', {
				token,
				messageId,
				reaction: '🎄',
			})
			expect(showError).toHaveBeenCalled()
			expect(vuexStore.commit).toHaveBeenNthCalledWith(3, 'addReactionToMessage', {
				token,
				messageId,
				reaction: '🎄',
			})
			expect(reactionsStore.getReactions(token, messageId)['🎄'].length).toEqual(2) // no reaction removed
		})
		it('shows an error when the API call of fetching reactions fails', async () => {
			// Arrange
			const errorResponse = generateOCSErrorResponse({ status: 500, payload: [] })
			getReactionsDetails.mockResolvedValue(errorResponse)
			console.debug = jest.fn()

			// Act
			await reactionsStore.fetchReactions({ token, messageId })

			// Assert
			expect(console.debug).toHaveBeenCalled()
		})
	})
})
