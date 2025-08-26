/*
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { getUserAbsence } from '../../services/groupwareService.ts'
import { generateOCSErrorResponse, generateOCSResponse } from '../../test-helpers.js'
import { useGroupwareStore } from '../groupware.ts'

vi.mock('../../services/groupwareService', () => ({
	getUserAbsence: vi.fn(),
}))

describe('groupwareStore', () => {
	const token = 'TOKEN'
	const userId = 'alice'
	const payload = { id: 1, userId: 'alice', firstDay: '2023-11-15', lastDay: '2023-11-17', status: 'absence status', message: 'absence message' }
	let groupwareStore

	beforeEach(async () => {
		setActivePinia(createPinia())
		groupwareStore = useGroupwareStore()
	})

	afterEach(async () => {
		vi.clearAllMocks()
	})

	describe('absence status', () => {
		it('processes a response from server and stores absence status', async () => {
			// Arrange
			const response = generateOCSResponse({ payload })
			getUserAbsence.mockResolvedValueOnce(response)

			// Act
			await groupwareStore.getUserAbsence({ token, userId })

			// Assert
			expect(getUserAbsence).toHaveBeenCalledWith(userId)
			expect(groupwareStore.absence[token]).toEqual(payload)
		})

		it('does not show error if absence status is not found', async () => {
			// Arrange
			const errorNotFound = generateOCSErrorResponse({ payload: null, status: 404 })
			const errorOther = generateOCSErrorResponse({ payload: null, status: 500 })
			getUserAbsence
				.mockRejectedValueOnce(errorNotFound)
				.mockRejectedValueOnce(errorOther)
			console.error = vi.fn()

			// Act
			await groupwareStore.getUserAbsence({ token, userId })
			await groupwareStore.getUserAbsence({ token, userId })

			// Assert
			expect(getUserAbsence).toHaveBeenCalledTimes(2)
			expect(console.error).toHaveBeenCalledTimes(1)
			expect(groupwareStore.absence[token]).toEqual(null)
		})

		it('removes absence status from the store', async () => {
			// Arrange
			const response = generateOCSResponse({ payload })
			getUserAbsence.mockResolvedValueOnce(response)
			const token2 = 'TOKEN_2'

			// Act
			await groupwareStore.getUserAbsence({ token, userId })
			groupwareStore.removeUserAbsence(token)
			groupwareStore.removeUserAbsence(token2)

			// Assert
			expect(groupwareStore.absence[token]).not.toBeDefined()
			expect(groupwareStore.absence[token2]).not.toBeDefined()
		})
	})

	describe('purge store', () => {
		it('clears store for provided token', async () => {
			// Arrange
			const response = generateOCSResponse({ payload })
			getUserAbsence.mockResolvedValueOnce(response)

			await groupwareStore.getUserAbsence({ token: 'token-1', userId })

			// Act
			groupwareStore.purgeGroupwareStore('token-1')

			// Assert
			expect(groupwareStore.absence['token-1']).not.toBeDefined()
		})
	})
})
