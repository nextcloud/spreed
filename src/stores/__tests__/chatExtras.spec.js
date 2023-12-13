import { setActivePinia, createPinia } from 'pinia'

import { getUserAbsence } from '../../services/participantsService.js'
import { generateOCSErrorResponse, generateOCSResponse } from '../../test-helpers.js'
import { useChatExtrasStore } from '../chatExtras.js'

jest.mock('../../services/participantsService', () => ({
	getUserAbsence: jest.fn(),
}))

describe('chatExtrasStore', () => {
	const token = 'TOKEN'
	const userId = 'alice'
	const payload = { id: 1, userId: 'alice', firstDay: '2023-11-15', lastDay: '2023-11-17', status: 'absence status', message: 'absence message' }
	let chatExtrasStore

	beforeEach(async () => {
		setActivePinia(createPinia())
		chatExtrasStore = useChatExtrasStore()
	})

	afterEach(async () => {
		jest.clearAllMocks()
	})

	describe('absence status', () => {
		it('processes a response from server and stores absence status', async () => {
			// Arrange
			const response = generateOCSResponse({ payload })
			getUserAbsence.mockResolvedValueOnce(response)

			// Act
			await chatExtrasStore.getUserAbsence({ token, userId })

			// Assert
			expect(getUserAbsence).toHaveBeenCalledWith(userId)
			expect(chatExtrasStore.absence[token]).toEqual(payload)
		})

		it('does not show error if absence status is not found', async () => {
			// Arrange
			const errorNotFound = generateOCSErrorResponse({ payload: null, status: 404 })
			const errorOther = generateOCSErrorResponse({ payload: null, status: 500 })
			getUserAbsence
				.mockRejectedValueOnce(errorNotFound)
				.mockRejectedValueOnce(errorOther)
			console.error = jest.fn()

			// Act
			await chatExtrasStore.getUserAbsence({ token, userId })
			await chatExtrasStore.getUserAbsence({ token, userId })

			// Assert
			expect(getUserAbsence).toHaveBeenCalledTimes(2)
			expect(console.error).toHaveBeenCalledTimes(1)
			expect(chatExtrasStore.absence[token]).toEqual(null)
		})

		it('removes absence status from the store', async () => {
			// Arrange
			const response = generateOCSResponse({ payload })
			getUserAbsence.mockResolvedValueOnce(response)
			const token2 = 'TOKEN_2'

			// Act
			await chatExtrasStore.getUserAbsence({ token, userId })
			chatExtrasStore.removeUserAbsence(token)
			chatExtrasStore.removeUserAbsence(token2)

			// Assert
			expect(chatExtrasStore.absence[token]).not.toBeDefined()
			expect(chatExtrasStore.absence[token2]).not.toBeDefined()
		})

	})

	describe('reply message', () => {
		it('adds reply message id to the store', () => {
			// Act
			chatExtrasStore.setParentIdToReply({ token, id: 101 })

			// Assert
			expect(chatExtrasStore.getParentIdToReply(token)).toBe(101)
		})

		it('clears reply message', () => {
			// Arrange
			chatExtrasStore.setParentIdToReply({ token, id: 101 })

			// Act
			chatExtrasStore.removeParentIdToReply(token)

			// Assert
			expect(chatExtrasStore.getParentIdToReply(token)).not.toBeDefined()
		})
	})

	describe('current input message', () => {
		it('sets current input message', () => {
			// Act
			chatExtrasStore.setChatInput({ token: 'token-1', text: 'message-1' })

			// Assert
			expect(chatExtrasStore.getChatInput('token-1')).toStrictEqual('message-1')
		})

		it('clears current input message', () => {
			// Arrange
			chatExtrasStore.setChatInput({ token: 'token-1', text: 'message-1' })

			// Act
			chatExtrasStore.removeChatInput('token-1')

			// Assert
			expect(chatExtrasStore.chatInput['token-1']).not.toBeDefined()
			expect(chatExtrasStore.getChatInput('token-1')).toBe('')
		})
	})

	describe('purge store', () => {
		it('clears store for provided token', async () => {
			// Arrange
			const response = generateOCSResponse({ payload })
			getUserAbsence.mockResolvedValueOnce(response)

			await chatExtrasStore.getUserAbsence({ token: 'token-1', userId })
			chatExtrasStore.setParentIdToReply({ token: 'token-1', id: 101 })
			chatExtrasStore.setChatInput({ token: 'token-1', text: 'message-1' })

			// Act
			chatExtrasStore.purgeChatExtras('token-1')

			// Assert
			expect(chatExtrasStore.absence['token-1']).not.toBeDefined()
			expect(chatExtrasStore.parentToReply['token-1']).not.toBeDefined()
			expect(chatExtrasStore.chatInput['token-1']).not.toBeDefined()
		})
	})
})
