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
			expect(chatExtrasStore.absence[token]).toEqual(undefined)
			expect(chatExtrasStore.absence[token2]).toEqual(undefined)
		})

	})
})
