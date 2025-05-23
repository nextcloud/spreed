/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createPinia, setActivePinia } from 'pinia'
import { sharedItemsOrder } from '../../components/RightSidebar/SharedItems/sharedItemsConstants.js'
import { SHARED_ITEM } from '../../constants.ts'
import { getSharedItems, getSharedItemsOverview } from '../../services/sharedItemsService.js'
import { generateOCSErrorResponse, generateOCSResponse } from '../../test-helpers.js'
import { useSharedItemsStore } from '../sharedItems.js'

jest.mock('../../services/sharedItemsService', () => ({
	getSharedItems: jest.fn(),
	getSharedItemsOverview: jest.fn(),
}))

describe('sharedItemsStore', () => {
	const token = 'TOKEN'
	let sharedItemsStore
	const limitOverview = 7
	const limitGeneral = 20
	const payloadOverview = {}
	const result = {}

	beforeEach(async () => {
		setActivePinia(createPinia())
		sharedItemsStore = useSharedItemsStore()
		sharedItemsOrder.forEach((type, index) => {
			payloadOverview[type] = [{ id: 100 + index, message: type }]
			result[type] = { [100 + index]: { id: 100 + index, message: type } }
		})
	})

	afterEach(async () => {
		jest.clearAllMocks()
	})

	describe('read/write operations', () => {
		it('returns an empty object when sharedItems are not initialized yet for conversation', async () => {
			// Assert: check initial state of the store
			expect(sharedItemsStore.sharedItems(token)).toEqual({})
		})

		it('processes a single message and add each unique item only once', () => {
			// Arrange
			const message = {
				id: 1,
				token,
				message: '{file}',
				messageParameters: { file: { mimetype: 'image/jpeg' } },
			}

			// Act
			sharedItemsStore.addSharedItemFromMessage(token, message)
			sharedItemsStore.addSharedItemFromMessage(token, message)

			// Assert
			expect(sharedItemsStore.sharedItems(token)).toEqual({ media: { 1: message } })
		})

		it('does not overwrite store with overview response', async () => {
			// Arrange
			const message = {
				id: 100,
				token,
				message: '{file}',
				messageParameters: { file: { mimetype: 'image/jpeg' } },
			}
			sharedItemsStore.addSharedItemFromMessage(token, message)

			// Act
			sharedItemsStore.addSharedItemsFromOverview(token, payloadOverview)

			// Assert
			expect(sharedItemsStore.sharedItems(token).media).toEqual({ 100: message })
		})

		it('processes an array of messages and add each unique item only once', () => {
			// Arrange
			const message1 = {
				id: 1,
				token,
				message: '{file}',
				messageParameters: { file: { mimetype: 'image/jpeg' } },
			}
			const message2 = {
				id: 2,
				token,
				message: '{file}',
				messageParameters: { file: { mimetype: 'image/jpeg' } },
			}

			// Act
			sharedItemsStore.addSharedItemsFromMessages(token, SHARED_ITEM.TYPES.MEDIA, [message1, message1, message2])

			// Assert
			expect(sharedItemsStore.sharedItems(token)).toEqual({ media: { 1: message1, 2: message2 } })
		})
	})

	describe('server requests', () => {
		it('skips an overview request to server if loaded already', async () => {
			// Arrange
			const response = generateOCSResponse({ payload: payloadOverview })
			getSharedItemsOverview.mockResolvedValue(response)
			await sharedItemsStore.getSharedItemsOverview(token)

			// Act
			await sharedItemsStore.getSharedItemsOverview(token)

			// Assert
			expect(getSharedItemsOverview).toHaveBeenCalledTimes(1)
		})

		it('processes an overview response from server', async () => {
			// Arrange: one of types omitted to check that only non-empty keys are stored
			payloadOverview[SHARED_ITEM.TYPES.OTHER] = []
			result[SHARED_ITEM.TYPES.OTHER] = undefined
			const response = generateOCSResponse({ payload: payloadOverview })
			getSharedItemsOverview.mockResolvedValueOnce(response)

			// Act: load sharedItemsOverview from server
			await sharedItemsStore.getSharedItemsOverview(token)

			// Assert
			expect(getSharedItemsOverview).toHaveBeenCalledWith(token, limitOverview)
			expect(sharedItemsStore.sharedItems(token)).toEqual(result)
		})

		it('processes a general response from server', async () => {
			// Arrange
			const message = {
				id: 1,
				token,
				message: '{file}',
				messageParameters: { file: { mimetype: 'image/jpeg' } },
			}
			result.media['1'] = message

			const responseOverview = generateOCSResponse({ payload: payloadOverview })
			getSharedItemsOverview.mockResolvedValueOnce(responseOverview)
			const response = generateOCSResponse({ payload: { 1: message } })
			getSharedItems.mockResolvedValueOnce(response)
			await sharedItemsStore.getSharedItemsOverview(token)

			// Act: load sharedItemsOverview from server
			await sharedItemsStore.getSharedItems(token, SHARED_ITEM.TYPES.MEDIA)

			// Assert
			expect(getSharedItems).toHaveBeenCalledWith(token, SHARED_ITEM.TYPES.MEDIA, 100, limitGeneral)
			expect(sharedItemsStore.sharedItems(token)).toEqual(result)
		})

		it('processes an empty general response from server', async () => {
			// Arrange
			const responseOverview = generateOCSResponse({ payload: payloadOverview })
			getSharedItemsOverview.mockResolvedValueOnce(responseOverview)
			const response = generateOCSResponse({ payload: [] })
			getSharedItems.mockResolvedValueOnce(response)
			await sharedItemsStore.getSharedItemsOverview(token)

			// Act: load sharedItemsOverview from server
			const output = await sharedItemsStore.getSharedItems(token, SHARED_ITEM.TYPES.MEDIA)

			// Assert
			expect(getSharedItems).toHaveBeenCalledWith(token, SHARED_ITEM.TYPES.MEDIA, 100, limitGeneral)
			expect(output).toEqual({ hasMoreItems: false, messages: [] })
			expect(sharedItemsStore.sharedItems(token)).toEqual(result)
		})
	})

	describe('handle exceptions', () => {
		beforeEach(() => {
			console.error = jest.fn()
		})

		it('handles error in server request for getSharedItemsOverview', async () => {
			// Arrange
			const response = generateOCSErrorResponse({ status: 404, payload: [] })
			getSharedItemsOverview.mockRejectedValueOnce(response)

			// Act
			await sharedItemsStore.getSharedItemsOverview(token)

			// Assert: store hasn't changed
			expect(sharedItemsStore.sharedItems(token)).toEqual({})
		})

		it('skips server request without initially loaded overview', async () => {
			// Act
			await sharedItemsStore.getSharedItems(token, SHARED_ITEM.TYPES.MEDIA)

			// Assert: store hasn't changed
			expect(getSharedItems).not.toHaveBeenCalled()
			expect(sharedItemsStore.sharedItems(token)).toEqual({})
		})

		it('handles error in server request for getSharedItems', async () => {
			// Arrange
			const responseOverview = generateOCSResponse({ payload: payloadOverview })
			getSharedItemsOverview.mockResolvedValueOnce(responseOverview)
			await sharedItemsStore.getSharedItemsOverview(token)

			const response = generateOCSErrorResponse({ status: 404, payload: [] })
			getSharedItems.mockRejectedValueOnce(response)

			// Act
			await sharedItemsStore.getSharedItems(token, SHARED_ITEM.TYPES.MEDIA)

			// Assert: store hasn't changed
			expect(sharedItemsStore.sharedItems(token)).toEqual(result)
		})
	})
})
