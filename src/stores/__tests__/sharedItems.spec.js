/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createPinia, setActivePinia } from 'pinia'
import { sharedItemsOrder } from '../../components/RightSidebar/SharedItems/sharedItemsConstants.ts'
import { SHARED_ITEM } from '../../constants.ts'
import { getSharedItems, getSharedItemsOverview } from '../../services/sharedItemsService.ts'
import { generateOCSErrorResponse, generateOCSResponse } from '../../test-helpers.js'
import { useSharedItemsStore } from '../sharedItems.ts'

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
			expect(getSharedItemsOverview).toHaveBeenCalledWith({ token, limit: limitOverview })
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
			expect(getSharedItems).toHaveBeenCalledWith({
				token,
				objectType: SHARED_ITEM.TYPES.MEDIA,
				lastKnownMessageId: 100,
				limit: limitGeneral,
			})
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
			expect(getSharedItems).toHaveBeenCalledWith({
				token,
				objectType: SHARED_ITEM.TYPES.MEDIA,
				lastKnownMessageId: 100,
				limit: limitGeneral,
			})
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

	describe('cleanup operations', () => {
		it('deletes shared items of a message from store', () => {
			// Arrange: add some shared items to the store
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
			sharedItemsStore.addSharedItemFromMessage(token, message1)
			sharedItemsStore.addSharedItemFromMessage(token, message2)
			expect(sharedItemsStore.sharedItems(token)).toEqual({ media: { 1: message1, 2: message2 } })
			// Act: delete shared items from the store
			sharedItemsStore.deleteSharedItemFromMessage(token, message1.id)
			// Assert: shared items store should not contain deleted item
			expect(sharedItemsStore.sharedItems(token)).toEqual({ media: { 2: message2 } })
		})

		it('purges the store when e.g. a chat history is cleared', () => {
			// Arrange: add some shared items to the store
			const message1 = {
				id: 1,
				token,
				message: '{file}',
				messageParameters: { file: { mimetype: 'image/jpeg' } },
			}
			sharedItemsStore.addSharedItemFromMessage(token, message1)
			expect(sharedItemsStore.sharedItems(token)).toEqual({ media: { 1: message1 } })
			// Act: purge the store
			sharedItemsStore.purgeSharedItemsStore(token)
			// Assert: shared items store should be empty
			expect(sharedItemsStore.sharedItems(token)).toEqual({})
		})

		it('purges shared items from store starting from a particular message id backwards', () => {
			// Arrange:
			const messages = [{
				id: 1,
				token,
				message: '{file}',
				messageParameters: { file: { mimetype: 'image/jpeg' } },
			}, {
				id: 2,
				token,
				message: '{file}',
				messageParameters: { file: { mimetype: 'image/jpeg' } },
			}, {
				id: 3,
				token,
				message: 'history cleared',
				systemMessage: 'history_cleared',
			}, {
				id: 4,
				token,
				message: '{file}',
				messageParameters: { file: { mimetype: 'image/jpeg' } },
			}]
			messages.filter((message) => !message.systemMessage).forEach((message) => sharedItemsStore.addSharedItemFromMessage(token, message))
			expect(sharedItemsStore.sharedItems(token)).toEqual({ media: { 1: messages[0], 2: messages[1], 4: messages[3] } })
			// Act: purge shared items from the store by message id
			sharedItemsStore.purgeSharedItemsStore(token, messages[2].id)
			// Assert: shared items store should not contain items from the deleted message
			expect(sharedItemsStore.sharedItems(token)).toEqual({ media: { 4: messages[3] } })
		})
	})
})
