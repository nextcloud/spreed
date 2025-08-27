/*
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showError } from '@nextcloud/dialogs'
import { flushPromises } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, test, vi } from 'vitest'
import { createStore, useStore } from 'vuex'
import {
	ATTENDEE,
	CHAT,
	MESSAGE,
} from '../constants.ts'
import {
	fetchNoteToSelfConversation,
} from '../services/conversationsService.ts'
import {
	deleteMessage,
	editMessage,
	fetchMessages,
	getMessageContext,
	pollNewMessages,
	postNewMessage,
	postRichObjectToConversation,
	updateLastReadMessage,
} from '../services/messagesService.ts'
import { useActorStore } from '../stores/actor.ts'
import { useChatStore } from '../stores/chat.ts'
import { useGuestNameStore } from '../stores/guestName.js'
import { useReactionsStore } from '../stores/reactions.js'
import { generateOCSErrorResponse, generateOCSResponse } from '../test-helpers.js'
import CancelableRequest from '../utils/cancelableRequest.js'
import messagesStore from './messagesStore.js'
import storeConfig from './storeConfig.js'

vi.mock('../services/messagesService', () => ({
	deleteMessage: vi.fn(),
	editMessage: vi.fn(),
	updateLastReadMessage: vi.fn(),
	fetchMessages: vi.fn(),
	getMessageContext: vi.fn(),
	pollNewMessages: vi.fn(),
	postNewMessage: vi.fn(),
	postRichObjectToConversation: vi.fn(),
}))

vi.mock('../services/conversationsService', () => ({
	fetchNoteToSelfConversation: vi.fn(),
}))

vi.mock('../utils/cancelableRequest')

// Test actions with 'chat-read-last' feature
vi.mock('@nextcloud/capabilities', () => ({
	getCapabilities: vi.fn(() => ({
		spreed: {
			features: ['chat-read-last'],
			'features-local': [],
			'config-local': { chat: [] },
		},
	})),
}))

vi.mock('vuex', async () => {
	const vuex = await vi.importActual('vuex')
	return {
		...vuex,
		useStore: vi.fn(),
	}
})

describe('messagesStore', () => {
	const TOKEN = 'XXTOKENXX'
	const conversation = {
		token: TOKEN,
		lastMessage: {
			id: 123,
		},
	}

	let testStoreConfig
	let store = null
	let conversationMock
	let updateConversationLastMessageMock
	let updateConversationLastReadMessageMock
	let updateConversationLastActiveAction
	let reactionsStore
	let actorStore
	let chatStore

	beforeEach(() => {
		setActivePinia(createPinia())
		reactionsStore = useReactionsStore()
		actorStore = useActorStore()
		chatStore = useChatStore()

		testStoreConfig = cloneDeep(storeConfig)

		actorStore.actorId = 'actor-id-1'
		actorStore.userId = 'actor-id-1'
		actorStore.actorType = ATTENDEE.ACTOR_TYPE.USERS
		actorStore.displayName = 'actor-display-name-1'

		conversationMock = vi.fn().mockReturnValue(conversation)
		updateConversationLastMessageMock = vi.fn()
		updateConversationLastReadMessageMock = vi.fn()
		updateConversationLastActiveAction = vi.fn()

		testStoreConfig.modules.conversationsStore.getters.conversation = vi.fn().mockReturnValue(conversationMock)
		testStoreConfig.modules.conversationsStore.actions.updateConversationLastMessage = updateConversationLastMessageMock
		testStoreConfig.modules.conversationsStore.actions.updateConversationLastReadMessage = updateConversationLastReadMessageMock
		testStoreConfig.modules.conversationsStore.actions.updateConversationLastActive = updateConversationLastActiveAction

		store = createStore(testStoreConfig)
		useStore.mockReturnValue(store)
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	describe('processMessage', () => {
		test('adds message to the store by token', () => {
			const message1 = {
				id: 1,
				token: TOKEN,
			}

			store.dispatch('processMessage', { token: TOKEN, message: message1 })
			expect(store.getters.messagesList(TOKEN)[0]).toStrictEqual(message1)
		})

		test('doesn\'t add specific messages to the store', () => {
			reactionsStore.resetReactions = vi.fn()
			reactionsStore.processReaction = vi.fn()

			const messages = [{
				id: 2,
				token: TOKEN,
				systemMessage: 'message_deleted',
				parent: { id: 1 },
			}, {
				id: 3,
				token: TOKEN,
				systemMessage: 'reaction',
				parent: { id: 1 },
			}, {
				id: 4,
				token: TOKEN,
				systemMessage: 'reaction_deleted',
				parent: { id: 1 },
			}, {
				id: 5,
				token: TOKEN,
				systemMessage: 'reaction_revoked',
				parent: { id: 1 },
			}, {
				id: 6,
				token: TOKEN,
				systemMessage: 'poll_voted',
				messageParameters: {
					poll: { id: 1 },
				},
			}]

			messages.forEach((message) => {
				store.dispatch('processMessage', { token: TOKEN, message })
			})

			expect(store.getters.messagesList(TOKEN)).toHaveLength(0)
		})

		test('adds user\'s message with included parent to the store', () => {
			const parentMessage = {
				id: 1,
				token: TOKEN,
			}
			const message1 = {
				id: 2,
				token: TOKEN,
				parent: parentMessage,
				messageType: MESSAGE.TYPE.COMMENT,
			}

			store.dispatch('processMessage', { token: TOKEN, message: message1 })
			expect(store.getters.messagesList(TOKEN)).toMatchObject([message1])
		})

		test('deletes matching temporary message when referenced', () => {
			const temporaryMessage = {
				id: 'temp-1',
				referenceId: 'reference-1',
				token: TOKEN,
			}
			store.dispatch('addTemporaryMessage', { token: TOKEN, message: temporaryMessage })

			const message1 = {
				id: 1,
				token: TOKEN,
				referenceId: 'reference-1',
			}

			store.dispatch('processMessage', { token: TOKEN, message: message1 })
			expect(store.getters.messagesList(TOKEN)).toStrictEqual([message1])
		})

		test('updates last read message when replacing matching temporary message', () => {
			conversationMock.mockReturnValueOnce({
				token: TOKEN,
				lastReadMessage: 100,
				lastMessage: {
					id: 123,
				},
			})
			const response = generateOCSResponse({ payload: conversation })
			updateLastReadMessage.mockResolvedValueOnce(response)

			const temporaryMessage = {
				id: 'temp-1',
				referenceId: 'reference-1',
				token: TOKEN,
			}
			store.dispatch('addTemporaryMessage', { token: TOKEN, message: temporaryMessage })

			const message1 = {
				id: 123,
				token: TOKEN,
				actorId: 'actor-id-1',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				referenceId: 'reference-1',
			}

			store.dispatch('processMessage', { token: TOKEN, message: message1 })
			expect(store.getters.messagesList(TOKEN)).toStrictEqual([message1])
			expect(updateLastReadMessage).toHaveBeenCalledWith(TOKEN, message1.id)
		})

		test('replaces existing message', () => {
			const message1 = {
				id: 1,
				token: TOKEN,
				message: 'hello',
			}
			const message2 = Object.assign({}, message1, { message: 'replaced' })

			store.dispatch('processMessage', { token: TOKEN, message: message1 })
			store.dispatch('processMessage', { token: TOKEN, message: message2 })
			expect(store.getters.messagesList(TOKEN)).toStrictEqual([message2])
		})

		test('replaces existing message from system message and drops outdated properties, apart from parent', () => {
			const message1 = {
				id: 2,
				token: TOKEN,
				message: 'helo-helo',
				'outdated-property': 'outdated-value',
				parent: {
					id: 1,
					token: TOKEN,
					message: 'hello',
					timestamp: 100,
				},
				timestamp: 200,
			}
			const message2 = {
				id: 4,
				token: TOKEN,
				message: '👍',
				messageType: MESSAGE.TYPE.SYSTEM,
				systemMessage: 'reaction',
				parent: {
					id: 2,
					token: TOKEN,
					message: 'hello-hello',
					lastEditTimestamp: 300,
					timestamp: 200,
					reactions: { '👍': 1 },
				},
				timestamp: 400,
			}

			store.dispatch('processMessage', { token: TOKEN, message: message1 })
			store.dispatch('processMessage', { token: TOKEN, message: message2 })
			expect(store.getters.messagesList(TOKEN)).toStrictEqual([{
				...message2.parent,
				parent: message1.parent,
			}])
		})
	})

	describe('messages list', () => {
		test('returns messages list', () => {
			const message1 = {
				id: 1,
				token: TOKEN,
			}
			const message2 = {
				id: 2,
				token: 'token-2',
			}
			const message3 = {
				id: 3,
				token: TOKEN,
			}

			store.dispatch('processMessage', { token: message1.token, message: message1 })
			store.dispatch('processMessage', { token: message2.token, message: message2 })
			store.dispatch('processMessage', { token: message3.token, message: message3 })
			expect(store.getters.messagesList(TOKEN)[0]).toStrictEqual(message1)
			expect(store.getters.messagesList(TOKEN)[1]).toStrictEqual(message3)
			expect(store.getters.messagesList('token-2')[0]).toStrictEqual(message2)

			// with messages getter
			expect(store.getters.messagesList(TOKEN)).toStrictEqual([
				message1,
				message3,
			])
			expect(store.getters.messagesList('token-2')).toStrictEqual([
				message2,
			])
		})

		const testCases = [
			// Default
			[1, 200, 1, 200, 200, undefined],
			[1, 400, 201, 400, 200, undefined],
			// with lastReadMessage
			[201, 600, 401, 600, 200, 200],
			[1, 400, 101, 300, 200, 200],
			[1, 400, 201, 400, 200, 300],
			// Border values
			[1, 400, 1, 101, 101, 1],
			[1, 400, 301, 400, 100, 400],
			[1, 400, 1, 199, 199, 99],
			[1, 400, 1, 200, 200, 100],
			[1, 400, 2, 201, 200, 101],
			[1, 400, 202, 400, 199, 301],
			[1, 400, 201, 400, 200, 300],
			[1, 400, 200, 399, 200, 299],
		]

		it.each(testCases)(
			'eases list from [%s - %s] to [%s - %s] (length: %s) with lastReadMessage %s',
			(oldFirst, oldLast, newFirst, newLast, length, lastReadMessage) => {
			// Arrange
				conversationMock.mockReturnValue({ lastReadMessage })
				for (let id = oldFirst; id <= oldLast; id++) {
					store.dispatch('processMessage', { token: TOKEN, message: { token: TOKEN, id } })
				}

				// Act
				store.dispatch('easeMessageList', { token: TOKEN })

				// Assert
				expect(store.getters.messagesList(TOKEN)).toHaveLength(length)
				expect(store.getters.messagesList(TOKEN).at(0)).toStrictEqual({ token: TOKEN, id: newFirst })
				expect(store.getters.messagesList(TOKEN).at(-1)).toStrictEqual({ token: TOKEN, id: newLast })
				if (oldFirst < lastReadMessage && lastReadMessage < oldLast) {
					expect(store.getters.message(TOKEN, lastReadMessage)).toBeDefined()
				}
			},
		)
	})

	describe('delete message', () => {
		let message

		beforeEach(() => {
			reactionsStore.resetReactions = vi.fn()

			message = {
				id: 10,
				token: TOKEN,
				message: 'hello',
			}

			store.dispatch('processMessage', { token: TOKEN, message: cloneDeep(message) })
		})

		test('deletes from server and replaces deleted message with response', async () => {
			const payload = {
				id: 11,
				token: TOKEN,
				message: '(deleted)',
				systemMessage: 'message_deleted',
				parent: {
					id: 10,
					token: TOKEN,
					message: 'parent message deleted',
					messageType: MESSAGE.TYPE.COMMENT_DELETED,
				},
			}
			const response = generateOCSResponse({ payload })
			deleteMessage.mockResolvedValueOnce(response)

			const status = await store.dispatch('deleteMessage', { token: message.token, id: message.id, placeholder: 'placeholder-text' })

			expect(deleteMessage).toHaveBeenCalledWith({ token: message.token, id: message.id })
			expect(status).toBe(200)

			expect(store.getters.messagesList(TOKEN)).toMatchObject([{
				id: 10,
				token: TOKEN,
				message: 'parent message deleted',
				messageType: MESSAGE.TYPE.COMMENT_DELETED,
			}])
		})

		test('deletes from server and replaces deleted message as parent with response', async () => {
			const childMessage = {
				id: 11,
				token: TOKEN,
				message: 'reply to hello',
				parent: cloneDeep(message),
			}
			store.dispatch('processMessage', { token: TOKEN, message: childMessage })

			const deletedParent = {
				id: 10,
				token: TOKEN,
				message: 'parent message deleted',
				messageType: MESSAGE.TYPE.COMMENT_DELETED,
			}
			const payload = {
				id: 12,
				token: TOKEN,
				message: '(deleted)',
				systemMessage: 'message_deleted',
				parent: cloneDeep(deletedParent),
			}
			const response = generateOCSResponse({ payload })
			deleteMessage.mockResolvedValueOnce(response)

			await store.dispatch('deleteMessage', { token: message.token, id: message.id, placeholder: 'placeholder-text' })

			expect(deleteMessage).toHaveBeenCalledWith({ token: message.token, id: message.id })
			expect(store.getters.messagesList(TOKEN)).toMatchObject([deletedParent, {
				id: 11,
				token: TOKEN,
				message: 'reply to hello',
				parent: deletedParent,
			}])
		})

		test('deletes from server but doesn\'t replace if deleted message is not in the store', async () => {
			const payload = {
				id: 11,
				token: TOKEN,
				message: '(deleted)',
				systemMessage: 'message_deleted',
				parent: {
					id: 9,
					token: TOKEN,
					message: 'parent message deleted',
					messageType: MESSAGE.TYPE.COMMENT_DELETED,
				},
			}
			const response = generateOCSResponse({ payload })
			deleteMessage.mockResolvedValueOnce(response)

			const status = await store.dispatch('deleteMessage', { token: TOKEN, id: 9, placeholder: 'placeholder-text' })

			expect(deleteMessage).toHaveBeenCalledWith({ token: TOKEN, id: 9 })
			expect(status).toBe(200)

			expect(store.getters.messagesList(TOKEN)).toMatchObject([message])
		})

		test('keeps message in list if an error status comes from server', async () => {
			const error = generateOCSErrorResponse({ payload: {}, status: 400 })
			deleteMessage.mockRejectedValueOnce(error)

			await store.dispatch('deleteMessage', { token: message.token, id: message.id, placeholder: 'placeholder-text' })
				.catch((error) => {
					expect(error.status).toBe(400)

					expect(store.getters.messagesList(TOKEN)).toMatchObject([message])
				})
		})

		test('shows placeholder while deletion is in progress', () => {
			store.dispatch('deleteMessage', {
				token: message.token,
				id: message.id,
				placeholder: 'placeholder-message',
			}).catch(() => {})

			expect(store.getters.messagesList(TOKEN)).toStrictEqual([{
				id: 10,
				token: TOKEN,
				message: 'placeholder-message',
				messageType: MESSAGE.TYPE.COMMENT_DELETED,
			}])
		})
	})

	describe('edit message', () => {
		let message

		beforeEach(() => {
			message = {
				id: 10,
				token: TOKEN,
				message: 'hello',
			}

			store.dispatch('processMessage', { token: TOKEN, message: cloneDeep(message) })
		})

		test('edits at server and replaces edited message with response', async () => {
			const payload = {
				id: 11,
				token: TOKEN,
				message: 'You edited a message',
				systemMessage: 'message_edited',
				parent: {
					id: 10,
					token: TOKEN,
					message: 'hello edited',
					messageType: MESSAGE.TYPE.COMMENT,
				},
			}
			const response = generateOCSResponse({ payload })
			editMessage.mockResolvedValueOnce(response)

			await store.dispatch('editMessage', { token: message.token, messageId: message.id, updatedMessage: 'hello edited' })

			expect(editMessage).toHaveBeenCalledWith({ token: message.token, messageId: message.id, updatedMessage: 'hello edited' })

			expect(store.getters.messagesList(TOKEN)).toMatchObject([{
				id: 10,
				token: TOKEN,
				message: 'hello edited',
				messageType: MESSAGE.TYPE.COMMENT,
			}])
		})

		test('edits at server and replaces edited message as parent with response', async () => {
			const childMessage = {
				id: 11,
				token: TOKEN,
				message: 'reply to hello',
				parent: cloneDeep(message),
			}
			store.dispatch('processMessage', { token: TOKEN, message: childMessage })
			const editedParent = {
				id: 10,
				token: TOKEN,
				message: 'hello edited',
				messageType: MESSAGE.TYPE.COMMENT,
			}
			const payload = {
				id: 12,
				token: TOKEN,
				message: 'You edited a message',
				systemMessage: 'message_edited',
				parent: cloneDeep(editedParent),
			}
			const response = generateOCSResponse({ payload })
			editMessage.mockResolvedValueOnce(response)

			await store.dispatch('editMessage', { token: message.token, messageId: message.id, updatedMessage: 'hello edited' })

			expect(editMessage).toHaveBeenCalledWith({ token: message.token, messageId: message.id, updatedMessage: 'hello edited' })

			expect(store.getters.messagesList(TOKEN)).toMatchObject([editedParent, {
				id: 11,
				token: TOKEN,
				message: 'reply to hello',
				parent: editedParent,
			}])
		})
	})

	test('deletes messages by token from store only', () => {
		const message1 = {
			id: 1,
			token: TOKEN,
		}

		store.dispatch('processMessage', { token: TOKEN, message: message1 })
		expect(store.getters.messagesList(TOKEN)[0]).toStrictEqual(message1)

		store.dispatch('purgeMessagesStore', TOKEN)
		expect(store.getters.messagesList(TOKEN)).toStrictEqual([])

		expect(deleteMessage).not.toHaveBeenCalled()
	})

	describe('temporary messages', () => {
		beforeEach(() => {
			vi.useFakeTimers().setSystemTime(new Date('2020-01-01T20:00:00'))
		})

		afterEach(() => {
			vi.useRealTimers()
		})

		test('adds temporary message to the list', () => {
			const temporaryMessage = {
				token: TOKEN,
				id: 'temp-1577908800000',
				timestamp: 0,
				systemMessage: '',
				messageType: MESSAGE.TYPE.COMMENT,
				message: 'original',
			}

			store.dispatch('addTemporaryMessage', { token: TOKEN, message: temporaryMessage })

			expect(store.getters.messagesList(TOKEN)).toMatchObject([temporaryMessage])

			expect(updateConversationLastActiveAction).toHaveBeenCalledWith(expect.anything(), TOKEN)
		})

		test('marks temporary message as failed', () => {
			const temporaryMessage = {
				token: TOKEN,
				id: 'temp-1577908800000',
				timestamp: 0,
				systemMessage: '',
				messageType: MESSAGE.TYPE.COMMENT,
				message: 'original',
			}

			store.dispatch('addTemporaryMessage', { token: TOKEN, message: temporaryMessage })
			store.dispatch('markTemporaryMessageAsFailed', {
				token: TOKEN,
				id: temporaryMessage.id,
				reason: 'failure-reason',
			})

			expect(store.getters.messagesList(TOKEN)).toMatchObject([{
				...temporaryMessage,
				sendingFailure: 'failure-reason',
			}])
		})

		test('removeTemporaryMessageFromStore', () => {
			const temporaryMessage = {
				token: TOKEN,
				id: 'temp-1577908800000',
				timestamp: 0,
				systemMessage: '',
				messageType: MESSAGE.TYPE.COMMENT,
				message: 'original',
			}

			store.dispatch('addTemporaryMessage', { token: TOKEN, message: temporaryMessage })
			store.dispatch('removeTemporaryMessageFromStore', { token: TOKEN, id: 'temp-1577908800000' })

			expect(store.getters.messagesList(TOKEN)).toStrictEqual([])
		})

		test('gets temporary message by reference', () => {
			const temporaryMessage = {
				token: TOKEN,
				id: 'temp-1577908800000',
				timestamp: 0,
				systemMessage: '',
				messageType: MESSAGE.TYPE.COMMENT,
				message: 'original',
				referenceId: 'reference-1',
			}

			store.dispatch('addTemporaryMessage', { token: TOKEN, message: temporaryMessage })

			expect(store.getters.getTemporaryReferences(TOKEN, 'reference-1')).toMatchObject([temporaryMessage])
		})
	})

	describe('last read message markers', () => {
		beforeEach(() => {
			const response = generateOCSResponse({ payload: conversation })
			actorStore.userId = 'user-1'
			actorStore.actorType = ATTENDEE.ACTOR_TYPE.USERS
			updateLastReadMessage.mockResolvedValue(response)
		})

		test('stores visual last read message id per token', () => {
			store.dispatch('setVisualLastReadMessageId', { token: TOKEN, id: 1 })
			store.dispatch('setVisualLastReadMessageId', { token: 'token-2', id: 2 })

			expect(store.getters.getVisualLastReadMessageId(TOKEN)).toBe(1)
			expect(store.getters.getVisualLastReadMessageId('token-2')).toBe(2)
		})

		test('clears last read message', async () => {
			actorStore.userId = 'user-1'

			store.dispatch('setVisualLastReadMessageId', { token: TOKEN, id: 100 })
			await store.dispatch('clearLastReadMessage', {
				token: TOKEN,
				updateVisually: false,
			})

			expect(conversationMock).toHaveBeenCalled()
			expect(updateConversationLastReadMessageMock).toHaveBeenCalledWith(expect.anything(), {
				token: TOKEN,
				lastReadMessage: 123,
			})

			expect(updateLastReadMessage).toHaveBeenCalledWith(TOKEN, null)
			expect(store.getters.getVisualLastReadMessageId(TOKEN)).toBe(100)
		})

		test('clears last read message for federated conversation', async () => {
			conversationMock.mockReturnValue({
				lastMessage: {},
				remoteServer: 'nextcloud.com',
			})

			store.commit('addMessage', { token: TOKEN, message: { id: 123 } })
			store.dispatch('setVisualLastReadMessageId', { token: TOKEN, id: 100 })
			await store.dispatch('clearLastReadMessage', {
				token: TOKEN,
				updateVisually: true,
			})

			expect(conversationMock).toHaveBeenCalled()
			expect(updateConversationLastReadMessageMock).not.toHaveBeenCalled()

			expect(updateLastReadMessage).toHaveBeenCalledWith(TOKEN, null)
			expect(store.getters.getVisualLastReadMessageId(TOKEN)).toBe(123)
		})

		test('clears last read message and update visually', async () => {
			store.dispatch('setVisualLastReadMessageId', { token: TOKEN, id: 100 })
			await store.dispatch('clearLastReadMessage', {
				token: TOKEN,
				updateVisually: true,
			})

			expect(conversationMock).toHaveBeenCalled()
			expect(updateConversationLastReadMessageMock).toHaveBeenCalledWith(expect.anything(), {
				token: TOKEN,
				lastReadMessage: 123,
			})

			expect(updateLastReadMessage).toHaveBeenCalledWith(TOKEN, null)
			expect(store.getters.getVisualLastReadMessageId(TOKEN)).toBe(123)
		})

		test('clears last read message for guests', async () => {
			actorStore.userId = null
			actorStore.actorType = ATTENDEE.ACTOR_TYPE.GUESTS

			store.dispatch('setVisualLastReadMessageId', { token: TOKEN, id: 100 })
			await store.dispatch('clearLastReadMessage', {
				token: TOKEN,
				updateVisually: true,
			})

			expect(conversationMock).toHaveBeenCalled()
			expect(actorStore.isActorGuest).toBe(true)
			expect(updateConversationLastReadMessageMock).toHaveBeenCalledWith(expect.anything(), {
				token: TOKEN,
				lastReadMessage: 123,
			})

			expect(updateLastReadMessage).not.toHaveBeenCalled()
			expect(store.getters.getVisualLastReadMessageId(TOKEN)).toBe(123)
		})

		test('updates last read message', async () => {
			const response = generateOCSResponse({
				payload: {
					unreadMessages: 0,
					unreadMention: false,
				},
			})
			updateLastReadMessage.mockResolvedValue(response)

			store.dispatch('setVisualLastReadMessageId', { token: TOKEN, id: 100 })
			await store.dispatch('updateLastReadMessage', {
				token: TOKEN,
				id: 200,
				updateVisually: false,
			})

			expect(conversationMock).toHaveBeenCalled()
			expect(updateConversationLastReadMessageMock).toHaveBeenCalledWith(expect.anything(), {
				token: TOKEN,
				lastReadMessage: 200,
			})

			expect(updateLastReadMessage).toHaveBeenCalledWith(TOKEN, 200)
			expect(store.getters.getVisualLastReadMessageId(TOKEN)).toBe(100)
		})

		test('updates last read message and update visually', async () => {
			const response = generateOCSResponse({
				payload: {
					unreadMessages: 0,
					unreadMention: false,
				},
			})
			updateLastReadMessage.mockResolvedValue(response)

			store.dispatch('setVisualLastReadMessageId', { token: TOKEN, id: 100 })
			await store.dispatch('updateLastReadMessage', {
				token: TOKEN,
				id: 200,
				updateVisually: true,
			})

			expect(conversationMock).toHaveBeenCalled()
			expect(updateConversationLastReadMessageMock).toHaveBeenCalledWith(expect.anything(), {
				token: TOKEN,
				lastReadMessage: 200,
			})

			expect(updateLastReadMessage).toHaveBeenCalledWith(TOKEN, 200)
			expect(store.getters.getVisualLastReadMessageId(TOKEN)).toBe(200)
		})

		test('updates last read message for guests', async () => {
			actorStore.userId = null
			actorStore.actorType = ATTENDEE.ACTOR_TYPE.GUESTS

			store.dispatch('setVisualLastReadMessageId', { token: TOKEN, id: 100 })
			await store.dispatch('updateLastReadMessage', {
				token: TOKEN,
				id: 200,
				updateVisually: true,
			})

			expect(conversationMock).toHaveBeenCalled()
			expect(actorStore.isActorGuest).toBe(true)
			expect(updateConversationLastReadMessageMock).toHaveBeenCalledWith(expect.anything(), {
				token: TOKEN,
				lastReadMessage: 200,
			})

			expect(updateLastReadMessage).not.toHaveBeenCalled()
			expect(store.getters.getVisualLastReadMessageId(TOKEN)).toBe(200)
		})
	})

	describe('fetchMessages', () => {
		let updateLastCommonReadMessageAction
		let addGuestNameAction
		let cancelFunctionMock

		const oldMessagesList = [{
			id: 98,
			token: TOKEN,
			actorType: ATTENDEE.ACTOR_TYPE.USERS,
		}, {
			id: 99,
			token: TOKEN,
			actorType: ATTENDEE.ACTOR_TYPE.GUESTS,
		}]
		const originalMessagesList = [{
			id: 100,
			token: TOKEN,
			actorType: ATTENDEE.ACTOR_TYPE.USERS,
		}]
		const newMessagesList = [{
			id: 101,
			token: TOKEN,
			actorType: ATTENDEE.ACTOR_TYPE.USERS,
		}, {
			id: 102,
			token: TOKEN,
			actorType: ATTENDEE.ACTOR_TYPE.GUESTS,
		}]

		beforeEach(() => {
			testStoreConfig = cloneDeep(messagesStore)
			const guestNameStore = useGuestNameStore()

			updateLastCommonReadMessageAction = vi.fn()
			addGuestNameAction = vi.fn()
			testStoreConfig.actions.updateLastCommonReadMessage = updateLastCommonReadMessageAction
			guestNameStore.addGuestName = addGuestNameAction

			cancelFunctionMock = vi.fn()
			CancelableRequest.mockImplementation((request) => {
				return {
					request,
					cancel: cancelFunctionMock,
				}
			})

			store = createStore(testStoreConfig)

			for (const index in originalMessagesList) {
				store.commit('addMessage', { token: TOKEN, message: originalMessagesList[index] })
			}
		})

		const testCasesOld = [
			[true, CHAT.FETCH_OLD, [...oldMessagesList, originalMessagesList.at(0)].reverse()],
			[false, CHAT.FETCH_OLD, [...oldMessagesList].reverse()],
			[true, CHAT.FETCH_NEW, [originalMessagesList.at(-1), ...newMessagesList]],
			[false, CHAT.FETCH_NEW, newMessagesList],
		]
		test.each(testCasesOld)('fetches messages from server: including last known - %s, look into future - %s', async (includeLastKnown, lookIntoFuture, payload) => {
			const response = generateOCSResponse({
				headers: {
					'x-chat-last-common-read': '123',
					'x-chat-last-given': payload.at(-1).id.toString(),
				},
				payload,
			})
			fetchMessages.mockResolvedValueOnce(response)
			const expectedMessages = lookIntoFuture
				? [originalMessagesList[0], ...newMessagesList]
				: [...oldMessagesList, originalMessagesList[0]]
			const expectedMessageFromGuest = expectedMessages.find((message) => message.actorType === ATTENDEE.ACTOR_TYPE.GUESTS)

			await store.dispatch('fetchMessages', {
				token: TOKEN,
				lastKnownMessageId: 100,
				includeLastKnown,
				lookIntoFuture,
				requestOptions: {
					dummyOption: true,
				},
				minimumVisible: 0,
			})

			expect(fetchMessages).toHaveBeenCalledWith({
				token: TOKEN,
				lastKnownMessageId: 100,
				includeLastKnown,
				lookIntoFuture,
				limit: CHAT.FETCH_LIMIT,
			}, {
				dummyOption: true,
			})

			expect(updateLastCommonReadMessageAction)
				.toHaveBeenCalledWith(expect.anything(), { token: TOKEN, lastCommonReadMessage: 123 })

			expect(addGuestNameAction).toHaveBeenCalledWith(expectedMessageFromGuest, { noUpdate: true })

			expect(store.getters.messagesList(TOKEN)).toStrictEqual(expectedMessages)
		})

		test('cancels fetching messages', () => {
			store.dispatch('fetchMessages', {
				token: TOKEN,
				lastKnownMessageId: 100,
			}).catch(() => {})

			expect(store.state.cancelFetchMessages).toBe(cancelFunctionMock)

			expect(cancelFunctionMock).not.toHaveBeenCalled()

			store.dispatch('cancelFetchMessages')

			expect(cancelFunctionMock).toHaveBeenCalledWith('canceled')

			expect(store.state.cancelFetchMessages).toBe(null)
		})

		test('cancels fetching messages when fetching again', async () => {
			store.dispatch('fetchMessages', {
				token: TOKEN,
				lastKnownMessageId: 100,
			}).catch(() => {})

			expect(store.state.cancelFetchMessages).toBe(cancelFunctionMock)

			store.dispatch('fetchMessages', {
				token: TOKEN,
				lastKnownMessageId: 100,
			}).catch(() => {})

			expect(cancelFunctionMock).toHaveBeenCalledWith('canceled')
		})
	})

	describe('get message context', () => {
		let updateLastCommonReadMessageAction
		let addGuestNameAction
		let cancelFunctionMock

		beforeEach(() => {
			testStoreConfig = cloneDeep(messagesStore)
			const guestNameStore = useGuestNameStore()

			updateLastCommonReadMessageAction = vi.fn()
			addGuestNameAction = vi.fn()
			testStoreConfig.actions.updateLastCommonReadMessage = updateLastCommonReadMessageAction
			guestNameStore.addGuestName = addGuestNameAction

			cancelFunctionMock = vi.fn()
			CancelableRequest.mockImplementation((request) => {
				return {
					request,
					cancel: cancelFunctionMock,
				}
			})

			store = createStore(testStoreConfig)
		})

		test('get context around specified message id', async () => {
			const messages = [{
				id: 1,
				token: TOKEN,
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
			}, {
				id: 2,
				token: TOKEN,
				actorType: ATTENDEE.ACTOR_TYPE.GUESTS,
			}]
			const response = generateOCSResponse({
				headers: {
					'x-chat-last-common-read': '1',
					'x-chat-last-given': '2',
				},
				payload: messages,
			})
			getMessageContext.mockResolvedValueOnce(response)

			await store.dispatch('getMessageContext', {
				token: TOKEN,
				messageId: 1,
				requestOptions: {
					dummyOption: true,
				},
				minimumVisible: 0,
			})

			expect(getMessageContext).toHaveBeenCalledWith({
				token: TOKEN,
				messageId: 1,
				limit: CHAT.FETCH_LIMIT / 2,
			}, {
				dummyOption: true,
			})

			expect(updateLastCommonReadMessageAction)
				.toHaveBeenCalledWith(expect.anything(), { token: TOKEN, lastCommonReadMessage: 1 })

			expect(addGuestNameAction).toHaveBeenCalledWith(messages[1], { noUpdate: true })

			expect(store.getters.messagesList(TOKEN)).toStrictEqual(messages)
		})

		test('fetch additional messages around context', async () => {
			const messagesContext = [{
				id: 3,
				token: TOKEN,
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
			}, {
				id: 4,
				token: TOKEN,
				actorType: ATTENDEE.ACTOR_TYPE.GUESTS,
			}]
			const messagesFetch = [{
				id: 1,
				token: TOKEN,
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
			}, {
				id: 2,
				token: TOKEN,
				actorType: ATTENDEE.ACTOR_TYPE.GUESTS,
			}]
			const responseContext = generateOCSResponse({
				headers: {
					'x-chat-last-common-read': '2',
					'x-chat-last-given': '4',
				},
				payload: messagesContext,
			})
			getMessageContext.mockResolvedValueOnce(responseContext)

			const responseFetch = generateOCSResponse({
				headers: {
					'x-chat-last-common-read': '2',
					'x-chat-last-given': '1',
				},
				payload: messagesFetch,
			})
			fetchMessages.mockResolvedValueOnce(responseFetch)

			await store.dispatch('getMessageContext', {
				token: TOKEN,
				messageId: 3,
				requestOptions: {
					dummyOption: true,
				},
				minimumVisible: 2,
			})

			expect(getMessageContext).toHaveBeenCalledWith({
				token: TOKEN,
				messageId: 3,
				limit: CHAT.FETCH_LIMIT / 2,
			}, {
				dummyOption: true,
			})
			expect(fetchMessages).toHaveBeenCalledWith({
				token: TOKEN,
				lastKnownMessageId: 3,
				includeLastKnown: false,
				lookIntoFuture: CHAT.FETCH_OLD,
				limit: CHAT.FETCH_LIMIT,
			}, undefined)

			expect(updateLastCommonReadMessageAction).toHaveBeenCalledTimes(2)
			expect(updateLastCommonReadMessageAction).toHaveBeenNthCalledWith(1, expect.anything(), { token: TOKEN, lastCommonReadMessage: 2 })
			expect(updateLastCommonReadMessageAction).toHaveBeenNthCalledWith(2, expect.anything(), { token: TOKEN, lastCommonReadMessage: 2 })

			expect(addGuestNameAction).toHaveBeenCalledWith(messagesContext[1], { noUpdate: true })

			expect(store.getters.messagesList(TOKEN)).toStrictEqual([...messagesFetch, ...messagesContext])
		})
	})

	describe('look for new messages', () => {
		let updateLastCommonReadMessageAction
		let updateConversationLastMessageAction
		let updateUnreadMessagesMutation
		let addGuestNameAction
		let cancelFunctionMocks
		let conversationMock

		beforeEach(() => {
			testStoreConfig = cloneDeep(messagesStore)
			const guestNameStore = useGuestNameStore()

			conversationMock = vi.fn()
			testStoreConfig.getters.conversation = vi.fn().mockReturnValue(conversationMock)

			updateConversationLastMessageAction = vi.fn()
			updateLastCommonReadMessageAction = vi.fn()
			updateUnreadMessagesMutation = vi.fn()
			addGuestNameAction = vi.fn()
			testStoreConfig.actions.updateConversationLastMessage = updateConversationLastMessageAction
			testStoreConfig.actions.updateLastCommonReadMessage = updateLastCommonReadMessageAction
			guestNameStore.addGuestName = addGuestNameAction
			testStoreConfig.mutations.updateUnreadMessages = updateUnreadMessagesMutation

			cancelFunctionMocks = []
			CancelableRequest.mockImplementation((request) => {
				const cancelFunctionMock = vi.fn()
				cancelFunctionMocks.push(cancelFunctionMock)
				return {
					request,
					cancel: cancelFunctionMock,
				}
			})

			store = createStore(testStoreConfig)
		})

		afterEach(() => {
			vi.clearAllMocks()
		})

		test('looks for new messages', async () => {
			const messages = [{
				id: 1,
				token: TOKEN,
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
			}, {
				id: 2,
				token: TOKEN,
				actorType: ATTENDEE.ACTOR_TYPE.GUESTS,
			}]
			const response = generateOCSResponse({
				headers: {
					'x-chat-last-common-read': '123',
					'x-chat-last-given': '100',
				},
				payload: messages,
			})
			pollNewMessages.mockResolvedValueOnce(response)

			// smaller number to make it update
			conversationMock.mockReturnValue({
				lastMessage: { id: 1 },
			})

			await store.dispatch('pollNewMessages', {
				token: TOKEN,
				requestId: 'request1',
				lastKnownMessageId: 100,
				requestOptions: {
					dummyOption: true,
				},
			})

			expect(pollNewMessages).toHaveBeenCalledWith({
				token: TOKEN,
				lastKnownMessageId: 100,
				limit: CHAT.FETCH_LIMIT,
			}, {
				dummyOption: true,
			})

			expect(conversationMock).toHaveBeenCalledWith(TOKEN)
			expect(updateConversationLastMessageAction)
				.toHaveBeenCalledWith(expect.anything(), { token: TOKEN, lastMessage: messages[1] })
			expect(updateLastCommonReadMessageAction)
				.toHaveBeenCalledWith(expect.anything(), { token: TOKEN, lastCommonReadMessage: 123 })

			expect(addGuestNameAction).toHaveBeenCalledWith(messages[1], { noUpdate: false })

			expect(store.getters.messagesList(TOKEN)).toStrictEqual(messages)
		})

		test('looks for new messages does not update last message if lower', async () => {
			const messages = [{
				id: 1,
				token: TOKEN,
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
			}, {
				id: 2,
				token: TOKEN,
				actorType: ATTENDEE.ACTOR_TYPE.GUESTS,
			}]
			const response = generateOCSResponse({
				payload: messages,
			})
			pollNewMessages.mockResolvedValueOnce(response)

			// smaller number to make it update
			conversationMock.mockReturnValue({ lastMessage: { id: 500 } })

			await store.dispatch('pollNewMessages', {
				token: TOKEN,
				requestId: 'request1',
				lastKnownMessageId: 100,
				requestOptions: {
					dummyOption: true,
				},
			})

			expect(updateConversationLastMessageAction)
				.not.toHaveBeenCalled()
			expect(updateLastCommonReadMessageAction)
				.not.toHaveBeenCalled()
		})

		test('does not look for new messages if lastKnownMessageId is falsy', async () => {
			// Arrange: prepare cancelable request from previous call of the function
			const cancelFunctionMock = vi.fn()
			cancelFunctionMocks.push(cancelFunctionMock)
			store.commit('setCancelPollNewMessages', { cancelFunction: cancelFunctionMock, requestId: 'request1' })
			console.warn = vi.fn()

			// Act
			store.dispatch('pollNewMessages', {
				token: TOKEN,
				requestId: 'request1',
				lastKnownMessageId: null,
			})

			// Assert
			expect(cancelFunctionMocks[0]).toHaveBeenCalledWith('canceled')
			expect(pollNewMessages).not.toHaveBeenCalled()
		})

		test('cancels look for new messages', async () => {
			store.dispatch('pollNewMessages', {
				token: TOKEN,
				requestId: 'request1',
				lastKnownMessageId: 100,
			}).catch(() => {})

			expect(cancelFunctionMocks[0]).not.toHaveBeenCalled()

			store.dispatch('cancelPollNewMessages', { requestId: 'request1' })

			expect(cancelFunctionMocks[0]).toHaveBeenCalledWith('canceled')
		})

		test('cancels look for new messages when called again', async () => {
			store.dispatch('pollNewMessages', {
				token: TOKEN,
				requestId: 'request1',
				lastKnownMessageId: 100,
			}).catch(() => {})

			store.dispatch('pollNewMessages', {
				token: TOKEN,
				requestId: 'request1',
				lastKnownMessageId: 100,
			}).catch(() => {})

			expect(cancelFunctionMocks[0]).toHaveBeenCalledWith('canceled')
		})

		test('cancels look for new messages call individually', async () => {
			store.dispatch('pollNewMessages', {
				token: TOKEN,
				requestId: 'request1',
				lastKnownMessageId: 100,
			}).catch(() => {})

			store.dispatch('pollNewMessages', {
				token: TOKEN,
				requestId: 'request2',
				lastKnownMessageId: 100,
			}).catch(() => {})

			store.dispatch('cancelPollNewMessages', { requestId: 'request1' })
			expect(cancelFunctionMocks[0]).toHaveBeenCalledWith('canceled')
			expect(cancelFunctionMocks[1]).not.toHaveBeenCalled()

			store.dispatch('cancelPollNewMessages', { requestId: 'request2' })
			expect(cancelFunctionMocks[1]).toHaveBeenCalledWith('canceled')
		})

		describe('updates unread counters immediately', () => {
			let testConversation

			beforeEach(() => {
				testConversation = {
					lastMessage: { id: 100 },
					lastReadMessage: 100,
					unreadMessages: 144,
					unreadMention: false,
				}
			})

			/**
			 * @param {Array} messages List of messages the API call returned
			 * @param {object} expectedPayload The parameters that should be updated when receiving the messages
			 */
			async function testUpdateMessageCounters(messages, expectedPayload) {
				const response = generateOCSResponse({
					headers: {
						'x-chat-last-common-read': '123',
						'x-chat-last-given': '100',
					},
					payload: messages,
				})
				pollNewMessages.mockResolvedValueOnce(response)

				// smaller number to make it update
				conversationMock.mockReturnValue(testConversation)

				await store.dispatch('pollNewMessages', {
					token: TOKEN,
					requestId: 'request1',
					lastKnownMessageId: 100,
				})

				if (expectedPayload) {
					expect(updateUnreadMessagesMutation).toHaveBeenCalledWith(expect.anything(), expectedPayload)
				} else {
					expect(updateUnreadMessagesMutation).not.toHaveBeenCalled()
				}
			}

			describe('updating unread messages counter', () => {
				test('updates unread message counter for regular messages', async () => {
					const messages = [{
						id: 101,
						token: TOKEN,
						actorType: ATTENDEE.ACTOR_TYPE.USERS,
					}, {
						id: 102,
						token: TOKEN,
						actorType: ATTENDEE.ACTOR_TYPE.GUESTS,
					}]
					const expectedPayload = {
						token: TOKEN,
						unreadMessages: 146,
						unreadMention: undefined,
					}
					await testUpdateMessageCounters(messages, expectedPayload)
				})

				test('skips system messages when counting unread messages', async () => {
					const messages = [{
						id: 101,
						token: TOKEN,
						actorType: ATTENDEE.ACTOR_TYPE.USERS,
					}, {
						id: 102,
						token: TOKEN,
						actorType: ATTENDEE.ACTOR_TYPE.USERS,
						systemMessage: 'i_am_the_system',
					}]
					const expectedPayload = {
						token: TOKEN,
						unreadMessages: 145,
						unreadMention: undefined,
					}
					await testUpdateMessageCounters(messages, expectedPayload)
				})

				test('only counts unread messages from the last unread message', async () => {
					const messages = [{
						id: 99,
						token: TOKEN,
						actorType: ATTENDEE.ACTOR_TYPE.USERS,
					}, {
						// this is the last unread message
						id: 100,
						token: TOKEN,
						actorType: ATTENDEE.ACTOR_TYPE.USERS,
					}, {
						id: 101,
						token: TOKEN,
						actorType: ATTENDEE.ACTOR_TYPE.USERS,
					}, {
						id: 102,
						token: TOKEN,
						actorType: ATTENDEE.ACTOR_TYPE.GUESTS,
					}]
					const expectedPayload = {
						token: TOKEN,
						unreadMessages: 146,
						unreadMention: undefined,
					}
					await testUpdateMessageCounters(messages, expectedPayload)
				})

				test('does not update counter if no new messages were found', async () => {
					const messages = [{
						// this one is the last read message so doesn't count
						id: 100,
						token: TOKEN,
						actorType: ATTENDEE.ACTOR_TYPE.USERS,
					}]
					await testUpdateMessageCounters(messages, null)
				})

				test('does not update counter if the conversation store is already in sync', async () => {
					// same as the retrieved message, conversation is in sync
					testConversation.lastMessage.id = 102
					const messages = [{
						id: 101,
						token: TOKEN,
						actorType: ATTENDEE.ACTOR_TYPE.USERS,
					}, {
						id: 102,
						token: TOKEN,
						actorType: ATTENDEE.ACTOR_TYPE.GUESTS,
					}]
					await testUpdateMessageCounters(messages, false)
				})
			})

			describe('updating unread mention flag', () => {
				/**
				 * @param {object} messageParameters The rich-object-string parameters of the message
				 * @param {boolean} expectedValue New state of the mention flag
				 */
				async function testMentionFlag(messageParameters, expectedValue) {
					const messages = [{
						id: 101,
						token: TOKEN,
						actorType: ATTENDEE.ACTOR_TYPE.USERS,
						messageParameters,
					}]
					const expectedPayload = {
						token: TOKEN,
						unreadMessages: 145,
						unreadMention: expectedValue,
					}
					await testUpdateMessageCounters(messages, expectedPayload)
				}

				test('updates unread mention flag for global message', async () => {
					await testMentionFlag({
						'mention-1': {
							type: 'call',
						},
					}, true)
				})

				test('updates unread mention flag for guest mention', async () => {
					actorStore.actorId = 'me_as_guest'
					actorStore.actorType = ATTENDEE.ACTOR_TYPE.GUESTS
					await testMentionFlag({
						'mention-0': {
							type: 'user',
							id: 'some_user',
						},
						'mention-1': {
							type: 'guest',
							id: 'guest/me_as_guest',
						},
					}, true)
				})

				test('does not update unread mention flag for a different guest mention', async () => {
					actorStore.actorId = 'me_as_guest'
					actorStore.actorType = ATTENDEE.ACTOR_TYPE.GUESTS
					await testMentionFlag({
						'mention-1': {
							type: 'guest',
							id: 'guest/someone_else_as_guest',
						},
					}, undefined)
				})

				test('updates unread mention flag for user mention', async () => {
					actorStore.actorId = 'me_as_user'
					actorStore.userId = 'me_as_user'
					actorStore.actorType = ATTENDEE.ACTOR_TYPE.USERS
					await testMentionFlag({
						'mention-0': {
							type: 'user',
							id: 'some_user',
						},
						'mention-1': {
							type: 'user',
							id: 'me_as_user',
						},
					}, true)
				})

				test('does not update unread mention flag for another user mention', async () => {
					actorStore.actorId = 'me_as_user'
					actorStore.userId = 'me_as_user'
					actorStore.actorType = ATTENDEE.ACTOR_TYPE.USERS
					await testMentionFlag({
						'mention-1': {
							type: 'user',
							id: 'another_user',
						},
					}, undefined)
				})

				test('does not update unread mention flag when no params', async () => {
					await testMentionFlag({}, undefined)
					await testMentionFlag(null, undefined)
				})

				test('does not update unread mention flag when already set', async () => {
					testConversation.unreadMention = true
					await testMentionFlag({
						'mention-1': {
							type: 'call',
						},
					}, undefined)
				})

				test('does not update unread mention flag for non-mention parameter', async () => {
					testConversation.unreadMention = true
					await testMentionFlag({
						'file-1': {
							type: 'file',
						},
					}, undefined)
				})

				test('does not update unread mention flag for previously read messages', async () => {
					const messages = [{
						// this message was already read
						id: 100,
						token: TOKEN,
						actorType: ATTENDEE.ACTOR_TYPE.USERS,
						messageParameters: {
							'mention-1': {
								type: 'call',
							},
						},
					}, {
						id: 101,
						token: TOKEN,
						actorType: ATTENDEE.ACTOR_TYPE.USERS,
					}]
					const expectedPayload = {
						token: TOKEN,
						unreadMessages: 145,
						unreadMention: undefined,
					}
					await testUpdateMessageCounters(messages, expectedPayload)
				})
			})
		})
	})

	describe('posting new message', () => {
		let message1
		let conversationMock
		let updateLastCommonReadMessageAction
		let updateConversationLastMessageAction
		let cancelFunctionMocks

		beforeEach(() => {
			testStoreConfig = cloneDeep(messagesStore)

			vi.useFakeTimers()

			console.error = vi.fn()

			conversationMock = vi.fn()
			actorStore.actorId = 'actor-id-1'
			actorStore.actorType = ATTENDEE.ACTOR_TYPE.USERS
			updateConversationLastMessageAction = vi.fn()
			updateLastCommonReadMessageAction = vi.fn()
			testStoreConfig.getters.conversation = vi.fn().mockReturnValue(conversationMock)
			testStoreConfig.actions.updateConversationLastMessage = updateConversationLastMessageAction
			testStoreConfig.actions.updateLastCommonReadMessage = updateLastCommonReadMessageAction
			// mock this complex local action as we already tested it elsewhere
			testStoreConfig.actions.updateConversationLastActive = updateConversationLastActiveAction
			testStoreConfig.actions.updateConversationLastReadMessage = vi.fn()
			testStoreConfig.actions.addConversation = vi.fn()

			cancelFunctionMocks = []
			CancelableRequest.mockImplementation((request) => {
				const cancelFunctionMock = vi.fn()
				cancelFunctionMocks.push(cancelFunctionMock)
				return {
					request,
					cancel: cancelFunctionMock,
				}
			})

			store = createStore(testStoreConfig)
			message1 = {
				id: 1,
				token: TOKEN,
				message: 'first',
			}

			store.dispatch('processMessage', { token: TOKEN, message: message1 })
		})

		afterEach(() => {
			vi.clearAllMocks()
		})

		test('posts new message', async () => {
			conversationMock.mockReturnValue({
				token: TOKEN,
				lastMessage: { id: 100 },
				lastReadMessage: 50,
			})
			actorStore.userId = 'current-user'

			const baseMessage = {
				actorId: 'actor-id-1',
				actorDisplayName: 'actor-display-name-1',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'blah',
				token: TOKEN,
				referenceId: 'abc123',
			}

			const temporaryMessage = {
				...baseMessage,
				id: 'temp-123',
				sendingFailure: '',
				silent: false,
			}

			const messageResponse = {
				...baseMessage,
				id: 200,
			}

			const response = generateOCSResponse({
				headers: {
					'x-chat-last-common-read': '100',
				},
				payload: messageResponse,
			})
			postNewMessage.mockResolvedValueOnce(response)

			const response2 = generateOCSResponse({
				payload: {
					unreadMessages: 0,
					unreadMention: false,
				},
			})
			updateLastReadMessage.mockResolvedValue(response2)
			store.dispatch('postNewMessage', { token: TOKEN, temporaryMessage }).catch(() => {
			})
			expect(postNewMessage).toHaveBeenCalledWith({
				token: TOKEN,
				message: 'blah',
				actorDisplayName: 'actor-display-name-1',
				referenceId: 'abc123',
				replyTo: undefined,
				silent: false,
				threadTitle: undefined,
			}, undefined)
			expect(store.getters.isSendingMessages).toBe(true)

			await flushPromises()
			expect(store.getters.isSendingMessages).toBe(false)

			expect(updateLastCommonReadMessageAction).toHaveBeenCalledWith(
				expect.anything(),
				{ token: TOKEN, lastCommonReadMessage: 100 },
			)

			expect(store.getters.messagesList(TOKEN)).toStrictEqual([message1, messageResponse])

			expect(updateConversationLastMessageAction)
				.toHaveBeenCalledWith(expect.anything(), { token: TOKEN, lastMessage: messageResponse })

			expect(updateLastReadMessage).toHaveBeenCalledWith(TOKEN, 200)
		})

		test('cancels posting new messages individually', () => {
			const temporaryMessage = {
				id: 'temp-123',
				message: 'blah',
				token: TOKEN,
				sendingFailure: '',
			}
			const temporaryMessage2 = {
				id: 'temp-456',
				message: 'second',
				token: TOKEN,
				sendingFailure: '',
			}

			store.dispatch('postNewMessage', { token: TOKEN, temporaryMessage, options: { silent: false } }).catch(() => {})
			store.dispatch('postNewMessage', { token: TOKEN, temporaryMessage: temporaryMessage2, options: { silent: false } }).catch(() => {})

			expect(cancelFunctionMocks[0]).not.toHaveBeenCalled()
			expect(cancelFunctionMocks[1]).not.toHaveBeenCalled()

			expect(store.getters.isSendingMessages).toBe(true)

			store.dispatch('cancelPostNewMessage', { messageId: 'temp-123' })

			expect(cancelFunctionMocks[0]).toHaveBeenCalledWith('canceled')
			expect(cancelFunctionMocks[1]).not.toHaveBeenCalled()

			expect(store.getters.isSendingMessages).toBe(true)

			store.dispatch('cancelPostNewMessage', { messageId: 'temp-456' })

			expect(cancelFunctionMocks[1]).toHaveBeenCalledWith('canceled')

			expect(store.getters.isSendingMessages).toBe(false)
		})

		/**
		 * @param {number} statusCode Return code of the API request
		 * @param {string} reasonCode The reason for the return code
		 */
		async function testMarkMessageErrors(statusCode, reasonCode) {
			const temporaryMessage = {
				id: 'temp-123',
				message: 'blah',
				token: TOKEN,
				sendingFailure: '',
			}

			const response = {
				status: statusCode,
			}

			console.error = vi.fn()

			postNewMessage.mockRejectedValueOnce({ isAxiosError: true, response })
			await expect(store.dispatch('postNewMessage', { token: TOKEN, temporaryMessage, options: { silent: false } })).rejects.toMatchObject({ response })

			expect(store.getters.isSendingMessages).toBe(false)

			expect(store.getters.messagesList(TOKEN)).toStrictEqual([
				message1,
				{
					id: 'temp-123',
					message: 'blah',
					token: TOKEN,
					sendingFailure: reasonCode,
				},
			])

			expect(showError).toHaveBeenCalled()
			expect(console.error).toHaveBeenCalled()
		}

		test('marks message as failed on permission denied', async () => {
			await testMarkMessageErrors(403, 'read-only')
		})

		test('marks message as failed when lobby enabled', async () => {
			await testMarkMessageErrors(412, 'lobby')
		})

		test('marks message as failed with generic error', async () => {
			await testMarkMessageErrors(500, 'other')
		})

		test('cancels after timeout', () => {
			const temporaryMessage = {
				id: 'temp-123',
				message: 'blah',
				token: TOKEN,
				sendingFailure: '',
			}

			store.dispatch('postNewMessage', { token: TOKEN, temporaryMessage, options: { silent: false } }).catch(() => {})

			vi.advanceTimersByTime(60000)

			expect(cancelFunctionMocks[0]).toHaveBeenCalledWith('canceled')

			expect(store.getters.messagesList(TOKEN)).toStrictEqual([
				message1,
				{
					id: 'temp-123',
					message: 'blah',
					token: TOKEN,
					sendingFailure: 'timeout',
				},
			])
		})

		test('does not timeout after request returns', async () => {
			const temporaryMessage = {
				id: 'temp-123',
				message: 'blah',
				token: TOKEN,
				sendingFailure: '',
			}

			const payload = {
				id: 200,
				token: TOKEN,
				message: 'blah',
			}
			const response = generateOCSResponse({ payload })
			postNewMessage.mockResolvedValueOnce(response)

			await store.dispatch('postNewMessage', { token: TOKEN, temporaryMessage, options: { silent: false } })

			vi.advanceTimersByTime(60000)

			expect(cancelFunctionMocks[0]).not.toHaveBeenCalled()
		})
	})

	describe('Forward a message', () => {
		let conversations
		let message1
		let messageToBeForwarded
		let targetToken
		let messageExpected

		beforeEach(() => {
			message1 = {
				id: 1,
				token: TOKEN,
				message: 'simple text message',
				messageParameters: {},
			}
			conversations = [
				{
					token: TOKEN,
					type: 3,
					displayName: 'conversation 1',
				},
				{
					token: 'token-self',
					type: 6,
					displayName: 'Note to self',
				},
				{
					token: 'token-2',
					type: 3,
					displayName: 'conversation 2',
				},
			]
		})

		test('forwards a message to the conversation when a token is given', () => {
			// Arrange
			targetToken = 'token-2'
			messageToBeForwarded = message1
			messageExpected = cloneDeep(message1)
			messageExpected.token = targetToken

			// Act
			store.dispatch('forwardMessage', { targetToken, messageToBeForwarded })

			// Assert
			expect(postNewMessage).toHaveBeenCalledWith({ ...messageExpected, silent: false })
		})
		test('forwards a message to Note to self when no token is given ', () => {
			// Arrange
			targetToken = 'token-self'
			messageToBeForwarded = message1
			messageExpected = cloneDeep(message1)
			messageExpected.token = targetToken

			store.dispatch('addConversation', conversations[1])

			// Act
			store.dispatch('forwardMessage', { messageToBeForwarded })

			// Assert
			expect(postNewMessage).toHaveBeenCalledWith({ ...messageExpected, silent: false })
		})

		test('generates Note to self when it does not exist ', async () => {
			// Arrange
			messageToBeForwarded = message1
			messageExpected = cloneDeep(message1)
			messageExpected.token = 'token-self'

			const response = {
				data: {
					ocs: {
						data: conversations[1],
					},
				},
			}
			fetchNoteToSelfConversation.mockResolvedValueOnce(response)

			// Act
			store.dispatch('forwardMessage', { messageToBeForwarded })
			await flushPromises()

			// Assert
			expect(store.getters.conversationsList).toContainEqual(conversations[1])
			expect(postNewMessage).toHaveBeenCalledWith({ ...messageExpected, silent: false })
		})
		test('removes parent message ', () => {
			// Arrange : prepare the expected message to be forwarded
			messageToBeForwarded = {
				id: 1,
				token: TOKEN,
				parent: message1,
				message: 'simple text message',
				messageParameters: {},
			}
			messageExpected = cloneDeep(messageToBeForwarded)
			targetToken = 'token-2'
			messageExpected.token = targetToken
			delete messageExpected.parent

			// Act
			store.dispatch('forwardMessage', { targetToken, messageToBeForwarded })

			// Assert
			expect(postNewMessage).toHaveBeenCalledWith({ ...messageExpected, silent: false })
		})
		test('forwards an object message', () => {
			// Arrange
			messageToBeForwarded = {
				id: 1,
				token: TOKEN,
				message: '{object}',
				messageParameters: {
					object: {
						id: '100',
						type: 'deck-card',
					},
				},
			}
			const objectToBeForwarded = messageToBeForwarded.messageParameters.object
			targetToken = 'token-2'

			// Act
			store.dispatch('forwardMessage', { targetToken, messageToBeForwarded })

			// Assert
			expect(postRichObjectToConversation).toHaveBeenCalledWith(
				targetToken,
				{
					objectId: objectToBeForwarded.id,
					objectType: objectToBeForwarded.type,
					metaData: JSON.stringify(objectToBeForwarded),
					referenceId: '',
				},
			)
		})
		test('forwards a message with mentions and remove the latter', () => {
			// Arrange
			messageToBeForwarded = {
				id: 1,
				token: TOKEN,
				message: 'Hello {mention-user1}, {mention-user2}, and {mention-call1}',
				messageParameters: {
					'mention-user1': {
						id: 'taylor',
						name: 'Taylor',
						type: 'user',
					},
					'mention-user2': {
						id: 'adam driver',
						name: 'Adam',
						type: 'user',
					},
					'mention-call1': {
						id: TOKEN,
						name: 'Team X',
						type: 'call',
					},
				},
			}
			targetToken = 'token-2'
			messageExpected = cloneDeep(messageToBeForwarded)
			messageExpected.message = 'Hello @"taylor", @"adam driver", and **Team X**'
			messageExpected.token = targetToken

			// Act
			store.dispatch('forwardMessage', { targetToken, messageToBeForwarded })

			// Assert
			expect(postNewMessage).toHaveBeenCalledWith({ ...messageExpected, silent: false })
		})
	})
})
