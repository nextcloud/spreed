import { createLocalVue } from '@vue/test-utils'
import flushPromises from 'flush-promises'
import { cloneDeep } from 'lodash'
import { createPinia, setActivePinia } from 'pinia'
import Vuex from 'vuex'

import { showError } from '@nextcloud/dialogs'

import storeConfig from './storeConfig.js'
// eslint-disable-next-line import/order -- required for testing
import messagesStore from './messagesStore.js'
import {
	ATTENDEE, CHAT,
} from '../constants.js'
import {
	fetchNoteToSelfConversation,
} from '../services/conversationsService.js'
import {
	deleteMessage,
	editMessage,
	updateLastReadMessage,
	fetchMessages,
	getMessageContext,
	lookForNewMessages,
	postNewMessage,
	postRichObjectToConversation,
} from '../services/messagesService.ts'
import { useChatExtrasStore } from '../stores/chatExtras.js'
import { useGuestNameStore } from '../stores/guestName.js'
import { useReactionsStore } from '../stores/reactions.js'
import { generateOCSErrorResponse, generateOCSResponse } from '../test-helpers.js'
import CancelableRequest from '../utils/cancelableRequest.js'

jest.mock('../services/messagesService', () => ({
	deleteMessage: jest.fn(),
	editMessage: jest.fn(),
	updateLastReadMessage: jest.fn(),
	fetchMessages: jest.fn(),
	getMessageContext: jest.fn(),
	lookForNewMessages: jest.fn(),
	postNewMessage: jest.fn(),
	postRichObjectToConversation: jest.fn(),
}))

jest.mock('../services/conversationsService', () => ({
	fetchNoteToSelfConversation: jest.fn(),
}))

jest.mock('../utils/cancelableRequest')
jest.mock('@nextcloud/dialogs', () => ({
	showError: jest.fn(),
}))

// Test actions with 'chat-read-last' feature
jest.mock('@nextcloud/capabilities', () => ({
	getCapabilities: jest.fn(() => ({
		spreed: {
			features: ['chat-read-last'],
		},
	}))
}))

describe('messagesStore', () => {
	const TOKEN = 'XXTOKENXX'
	const conversation = {
		token: TOKEN,
		lastMessage: {
			id: 123,
		},
	}

	let localVue = null
	let testStoreConfig
	let store = null
	let getActorIdMock
	let getUserIdMock
	let getActorTypeMock
	let getDisplayNameMock
	let conversationMock
	let updateConversationLastMessageMock
	let updateConversationLastReadMessageMock
	let updateConversationLastActiveAction
	let reactionsStore

	beforeEach(() => {
		localVue = createLocalVue()
		localVue.use(Vuex)
		setActivePinia(createPinia())
		reactionsStore = useReactionsStore()

		testStoreConfig = cloneDeep(storeConfig)

		getActorIdMock = jest.fn().mockReturnValue(() => 'actor-id-1')
		getUserIdMock = jest.fn().mockReturnValue(() => 'actor-id-1')
		getActorTypeMock = jest.fn().mockReturnValue(() => ATTENDEE.ACTOR_TYPE.USERS)
		getDisplayNameMock = jest.fn().mockReturnValue(() => 'actor-display-name-1')
		conversationMock = jest.fn().mockReturnValue(conversation)
		updateConversationLastMessageMock = jest.fn()
		updateConversationLastReadMessageMock = jest.fn()
		updateConversationLastActiveAction = jest.fn()

		testStoreConfig.modules.actorStore.getters.getActorId = getActorIdMock
		testStoreConfig.modules.actorStore.getters.getUserId = getUserIdMock
		testStoreConfig.modules.actorStore.getters.getActorType = getActorTypeMock
		testStoreConfig.modules.actorStore.getters.getDisplayName = getDisplayNameMock
		testStoreConfig.modules.conversationsStore.getters.conversation = jest.fn().mockReturnValue(conversationMock)
		testStoreConfig.modules.conversationsStore.actions.updateConversationLastMessage = updateConversationLastMessageMock
		testStoreConfig.modules.conversationsStore.actions.updateConversationLastReadMessage = updateConversationLastReadMessageMock
		testStoreConfig.modules.conversationsStore.actions.updateConversationLastActive = updateConversationLastActiveAction
		testStoreConfig.modules.pollStore.getters.debounceGetPollData = jest.fn()

		store = new Vuex.Store(testStoreConfig)
	})

	afterEach(() => {
		jest.clearAllMocks()
	})

	describe('processMessage', () => {
		test('adds message to the store by token', () => {
			const message1 = {
				id: 1,
				token: TOKEN,
			}

			store.dispatch('processMessage', { token: TOKEN, message: message1 })
			expect(store.getters.messagesList(TOKEN)[0]).toBe(message1)
		})

		test('doesn\'t add specific messages to the store', () => {
			reactionsStore.resetReactions = jest.fn()
			reactionsStore.processReaction = jest.fn()

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

			messages.forEach(message => {
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
				messageType: 'comment',
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
	})

	test('message list', () => {
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

	describe('delete message', () => {
		let message

		beforeEach(() => {
			reactionsStore.resetReactions = jest.fn()

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
					messageType: 'comment_deleted',
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
				messageType: 'comment_deleted',
			}])
		})

		test('deletes from server and replaces deleted message as parent with response', async () => {
			const childMessage = {
				id: 11,
				token: TOKEN,
				message: 'reply to hello',
				parent: cloneDeep(message)
			}
			store.dispatch('processMessage', { token: TOKEN, message: childMessage })

			const deletedParent = {
				id: 10,
				token: TOKEN,
				message: 'parent message deleted',
				messageType: 'comment_deleted',
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
					messageType: 'comment_deleted',
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
				.catch(error => {
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
				messageType: 'comment_deleted',
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
					messageType: 'comment',
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
				messageType: 'comment',
			}])
		})

		test('edits at server and replaces edited message as parent with response', async () => {
			const childMessage = {
				id: 11,
				token: TOKEN,
				message: 'reply to hello',
				parent: cloneDeep(message)
			}
			store.dispatch('processMessage', { token: TOKEN, message: childMessage })
			const editedParent = {
				id: 10,
				token: TOKEN,
				message: 'hello edited',
				messageType: 'comment',
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
		expect(store.getters.messagesList(TOKEN)[0]).toBe(message1)

		store.dispatch('purgeMessagesStore', TOKEN)
		expect(store.getters.messagesList(TOKEN)).toStrictEqual([])

		expect(deleteMessage).not.toHaveBeenCalled()
	})

	describe('temporary messages', () => {
		let mockDate
		let chatExtraStore

		beforeEach(() => {
			mockDate = new Date('2020-01-01 20:00:00')
			jest.spyOn(global, 'Date')
				.mockImplementation(() => mockDate)
			chatExtraStore = useChatExtrasStore()
		})

		test('creates temporary message', async () => {
			const temporaryMessage = await store.dispatch('createTemporaryMessage', {
				text: 'blah',
				token: TOKEN,
				uploadId: null,
				index: null,
				file: null,
				localUrl: null,
			})

			expect(getActorIdMock).toHaveBeenCalled()
			expect(getActorTypeMock).toHaveBeenCalled()
			expect(getDisplayNameMock).toHaveBeenCalled()

			expect(temporaryMessage).toMatchObject({
				id: 'temp-1577908800000',
				actorId: 'actor-id-1',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				actorDisplayName: 'actor-display-name-1',
				timestamp: 0,
				systemMessage: '',
				messageType: '',
				message: 'blah',
				messageParameters: {},
				token: TOKEN,
				isReplyable: false,
				sendingFailure: '',
				reactions: {},
				referenceId: expect.stringMatching(/^[a-zA-Z0-9]{64}$/),
			})
		})

		test('creates temporary message with message to be replied', async () => {
			const parent = {
				id: 123,
				token: TOKEN,
				message: 'hello',
			}

			store.dispatch('processMessage', { token: TOKEN, message: parent })
			chatExtraStore.setParentIdToReply({ token: TOKEN, id: 123 })

			const temporaryMessage = await store.dispatch('createTemporaryMessage', {
				text: 'blah',
				token: TOKEN,
				uploadId: null,
				index: null,
				file: null,
				localUrl: null,
			})

			expect(temporaryMessage).toMatchObject({
				id: 'temp-1577908800000',
				actorId: 'actor-id-1',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				actorDisplayName: 'actor-display-name-1',
				timestamp: 0,
				systemMessage: '',
				messageType: '',
				message: 'blah',
				messageParameters: {},
				token: TOKEN,
				parent,
				isReplyable: false,
				sendingFailure: '',
				reactions: {},
				referenceId: expect.stringMatching(/^[a-zA-Z0-9]{64}$/),
			})
		})

		test('creates temporary message with file', async () => {
			const file = {
				type: 'text/plain',
				name: 'original-name.txt',
				newName: 'new-name.txt',
			}
			const temporaryMessage = await store.dispatch('createTemporaryMessage', {
				text: 'blah',
				token: TOKEN,
				uploadId: 'upload-id-1',
				index: 'upload-index-1',
				file,
				localUrl: 'local-url://original-name.txt',
			})

			expect(temporaryMessage).toMatchObject({
				id: expect.stringMatching(/^temp-1577908800000-upload-id-1-0\.[0-9]*$/),
				actorId: 'actor-id-1',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				actorDisplayName: 'actor-display-name-1',
				timestamp: 0,
				systemMessage: '',
				messageType: '',
				message: 'blah',
				messageParameters: {
					file: {
						type: 'file',
						file,
						mimetype: 'text/plain',
						id: expect.stringMatching(/^temp-1577908800000-upload-id-1-0\.[0-9]*$/),
						name: 'new-name.txt',
						uploadId: 'upload-id-1',
						localUrl: 'local-url://original-name.txt',
						index: 'upload-index-1',
					},
				},
				token: TOKEN,
				isReplyable: false,
				sendingFailure: '',
				reactions: {},
				referenceId: expect.stringMatching(/^[a-zA-Z0-9]{64}$/),
			})
		})

		test('adds temporary message to the list', async () => {
			const temporaryMessage = await store.dispatch('createTemporaryMessage', {
				text: 'blah',
				token: TOKEN,
				uploadId: null,
				index: null,
				file: null,
				localUrl: null,
			})

			store.dispatch('addTemporaryMessage', { token: TOKEN, message: temporaryMessage })

			expect(store.getters.messagesList(TOKEN)).toMatchObject([{
				id: 'temp-1577908800000',
				actorId: 'actor-id-1',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				actorDisplayName: 'actor-display-name-1',
				timestamp: 0,
				systemMessage: '',
				messageType: '',
				message: 'blah',
				messageParameters: {},
				token: TOKEN,
				isReplyable: false,
				sendingFailure: '',
				reactions: {},
				referenceId: expect.stringMatching(/^[a-zA-Z0-9]{64}$/),
			}])

			expect(updateConversationLastActiveAction).toHaveBeenCalledWith(expect.anything(), TOKEN)

			// add again just replaces it
			store.dispatch('addTemporaryMessage', {
				token: TOKEN,
				message: { ...temporaryMessage, message: 'replaced' }
			})

			expect(store.getters.messagesList(TOKEN)).toMatchObject([{
				id: 'temp-1577908800000',
				actorId: 'actor-id-1',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				actorDisplayName: 'actor-display-name-1',
				timestamp: 0,
				systemMessage: '',
				messageType: '',
				message: 'replaced',
				messageParameters: {},
				token: TOKEN,
				isReplyable: false,
				sendingFailure: '',
				reactions: {},
				referenceId: expect.stringMatching(/^[a-zA-Z0-9]{64}$/),
			}])
		})

		test('marks temporary message as failed', async () => {
			const temporaryMessage = await store.dispatch('createTemporaryMessage', {
				text: 'blah',
				token: TOKEN,
				uploadId: null,
				index: null,
				file: null,
				localUrl: null,
			})

			store.dispatch('addTemporaryMessage', { token: TOKEN, message: temporaryMessage })
			store.dispatch('markTemporaryMessageAsFailed', {
				token: TOKEN,
				id: temporaryMessage.id,
				reason: 'failure-reason',
			})

			expect(store.getters.messagesList(TOKEN)).toMatchObject([{
				id: 'temp-1577908800000',
				actorId: 'actor-id-1',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				actorDisplayName: 'actor-display-name-1',
				timestamp: 0,
				systemMessage: '',
				messageType: '',
				message: 'blah',
				messageParameters: {},
				token: TOKEN,
				isReplyable: false,
				sendingFailure: 'failure-reason',
				reactions: {},
				referenceId: expect.stringMatching(/^[a-zA-Z0-9]{64}$/),
			}])
		})

		test('removeTemporaryMessageFromStore', async () => {
			const temporaryMessage = await store.dispatch('createTemporaryMessage', {
				text: 'blah',
				token: TOKEN,
				uploadId: null,
				index: null,
				file: null,
				localUrl: null,
			})

			store.dispatch('addTemporaryMessage', { token: TOKEN, message: temporaryMessage })
			store.dispatch('removeTemporaryMessageFromStore', { token: TOKEN, id: temporaryMessage.id })

			expect(store.getters.messagesList(TOKEN)).toStrictEqual([])
		})

		test('gets temporary message by reference', async () => {
			const temporaryMessage = await store.dispatch('createTemporaryMessage', {
				text: 'blah',
				token: TOKEN,
				uploadId: null,
				index: null,
				file: null,
				localUrl: null,
			})

			store.dispatch('addTemporaryMessage', { token: TOKEN, message: temporaryMessage })

			expect(store.getters.getTemporaryReferences(TOKEN, temporaryMessage.referenceId)).toMatchObject([{
				id: 'temp-1577908800000',
				actorId: 'actor-id-1',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				actorDisplayName: 'actor-display-name-1',
				timestamp: 0,
				systemMessage: '',
				messageType: '',
				message: 'blah',
				messageParameters: {},
				token: TOKEN,
				isReplyable: false,
				sendingFailure: '',
				reactions: {},
				referenceId: expect.stringMatching(/^[a-zA-Z0-9]{64}$/),
			}])
		})
	})

	test('stores first and last known message ids by token', () => {
		store.dispatch('setFirstKnownMessageId', { token: TOKEN, id: 1 })
		store.dispatch('setFirstKnownMessageId', { token: 'token-2', id: 2 })
		store.dispatch('setLastKnownMessageId', { token: TOKEN, id: 3 })
		store.dispatch('setLastKnownMessageId', { token: 'token-2', id: 4 })

		expect(store.getters.getFirstKnownMessageId(TOKEN)).toBe(1)
		expect(store.getters.getFirstKnownMessageId('token-2')).toBe(2)

		expect(store.getters.getLastKnownMessageId(TOKEN)).toBe(3)
		expect(store.getters.getLastKnownMessageId('token-2')).toBe(4)
	})

	describe('last read message markers', () => {
		beforeEach(() => {
			const response = generateOCSResponse({ payload: conversation })
			updateLastReadMessage.mockResolvedValue(response)
		})

		test('stores visual last read message id per token', () => {
			store.dispatch('setVisualLastReadMessageId', { token: TOKEN, id: 1 })
			store.dispatch('setVisualLastReadMessageId', { token: 'token-2', id: 2 })

			expect(store.getters.getVisualLastReadMessageId(TOKEN)).toBe(1)
			expect(store.getters.getVisualLastReadMessageId('token-2')).toBe(2)
		})

		test('clears last read message', async () => {
			getUserIdMock.mockReturnValue(() => 'user-1')

			store.dispatch('setVisualLastReadMessageId', { token: TOKEN, id: 100 })
			await store.dispatch('clearLastReadMessage', {
				token: TOKEN,
				updateVisually: false,
			})

			expect(conversationMock).toHaveBeenCalled()
			expect(getUserIdMock).toHaveBeenCalled()
			expect(updateConversationLastReadMessageMock).toHaveBeenCalledWith(expect.anything(), {
				token: TOKEN,
				lastReadMessage: 123,
			})

			expect(updateLastReadMessage).toHaveBeenCalledWith(TOKEN, null)
			expect(store.getters.getVisualLastReadMessageId(TOKEN)).toBe(100)
		})

		test('clears last read message for federated conversation', async () => {
			getUserIdMock.mockReturnValue(() => 'federated-user-1')
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
			expect(getUserIdMock).toHaveBeenCalled()
			expect(updateConversationLastReadMessageMock).not.toHaveBeenCalled()

			expect(updateLastReadMessage).toHaveBeenCalledWith(TOKEN, null)
			expect(store.getters.getVisualLastReadMessageId(TOKEN)).toBe(123)
		})

		test('clears last read message and update visually', async () => {
			getUserIdMock.mockReturnValue(() => 'user-1')

			store.dispatch('setVisualLastReadMessageId', { token: TOKEN, id: 100 })
			await store.dispatch('clearLastReadMessage', {
				token: TOKEN,
				updateVisually: true,
			})

			expect(conversationMock).toHaveBeenCalled()
			expect(getUserIdMock).toHaveBeenCalled()
			expect(updateConversationLastReadMessageMock).toHaveBeenCalledWith(expect.anything(), {
				token: TOKEN,
				lastReadMessage: 123,
			})

			expect(updateLastReadMessage).toHaveBeenCalledWith(TOKEN, null)
			expect(store.getters.getVisualLastReadMessageId(TOKEN)).toBe(123)
		})

		test('clears last read message for guests', async () => {
			getUserIdMock.mockReturnValue(() => null)

			store.dispatch('setVisualLastReadMessageId', { token: TOKEN, id: 100 })
			await store.dispatch('clearLastReadMessage', {
				token: TOKEN,
				updateVisually: true,
			})

			expect(conversationMock).toHaveBeenCalled()
			expect(getUserIdMock).toHaveBeenCalled()
			expect(updateConversationLastReadMessageMock).toHaveBeenCalledWith(expect.anything(), {
				token: TOKEN,
				lastReadMessage: 123,
			})

			expect(updateLastReadMessage).not.toHaveBeenCalled()
			expect(store.getters.getVisualLastReadMessageId(TOKEN)).toBe(123)
		})

		test('updates last read message', async () => {
			getUserIdMock.mockReturnValue(() => 'user-1')
			const response = generateOCSResponse({
				payload: {
					unreadMessages: 0,
					unreadMention: false,
				}
			})
			updateLastReadMessage.mockResolvedValue(response)

			store.dispatch('setVisualLastReadMessageId', { token: TOKEN, id: 100 })
			await store.dispatch('updateLastReadMessage', {
				token: TOKEN,
				id: 200,
				updateVisually: false,
			})

			expect(conversationMock).toHaveBeenCalled()
			expect(getUserIdMock).toHaveBeenCalled()
			expect(updateConversationLastReadMessageMock).toHaveBeenCalledWith(expect.anything(), {
				token: TOKEN,
				lastReadMessage: 200,
			})

			expect(updateLastReadMessage).toHaveBeenCalledWith(TOKEN, 200)
			expect(store.getters.getVisualLastReadMessageId(TOKEN)).toBe(100)
		})

		test('updates last read message and update visually', async () => {
			getUserIdMock.mockReturnValue(() => 'user-1')
			const response = generateOCSResponse({
				payload: {
					unreadMessages: 0,
					unreadMention: false,
				}
			})
			updateLastReadMessage.mockResolvedValue(response)

			store.dispatch('setVisualLastReadMessageId', { token: TOKEN, id: 100 })
			await store.dispatch('updateLastReadMessage', {
				token: TOKEN,
				id: 200,
				updateVisually: true,
			})

			expect(conversationMock).toHaveBeenCalled()
			expect(getUserIdMock).toHaveBeenCalled()
			expect(updateConversationLastReadMessageMock).toHaveBeenCalledWith(expect.anything(), {
				token: TOKEN,
				lastReadMessage: 200,
			})

			expect(updateLastReadMessage).toHaveBeenCalledWith(TOKEN, 200)
			expect(store.getters.getVisualLastReadMessageId(TOKEN)).toBe(200)
		})

		test('updates last read message for guests', async () => {
			getUserIdMock.mockReturnValue(() => null)

			store.dispatch('setVisualLastReadMessageId', { token: TOKEN, id: 100 })
			await store.dispatch('updateLastReadMessage', {
				token: TOKEN,
				id: 200,
				updateVisually: true,
			})

			expect(conversationMock).toHaveBeenCalled()
			expect(getUserIdMock).toHaveBeenCalled()
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

		beforeEach(() => {
			testStoreConfig = cloneDeep(messagesStore)
			const guestNameStore = useGuestNameStore()

			updateLastCommonReadMessageAction = jest.fn()
			addGuestNameAction = jest.fn()
			testStoreConfig.actions.updateLastCommonReadMessage = updateLastCommonReadMessageAction
			guestNameStore.addGuestName = addGuestNameAction

			cancelFunctionMock = jest.fn()
			CancelableRequest.mockImplementation((request) => {
				return {
					request,
					cancel: cancelFunctionMock,
				}
			})

			store = new Vuex.Store(testStoreConfig)
		})

		test('fetches messages from server including last known', async () => {
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
			fetchMessages.mockResolvedValueOnce(response)

			await store.dispatch('fetchMessages', {
				token: TOKEN,
				lastKnownMessageId: 100,
				includeLastKnown: true,
				requestOptions: {
					dummyOption: true,
				},
				minimumVisible: 0,
			})

			expect(fetchMessages).toHaveBeenCalledWith({
				token: TOKEN,
				lastKnownMessageId: 100,
				includeLastKnown: true,
				limit: CHAT.FETCH_LIMIT,
			}, {
				dummyOption: true,
			})

			expect(updateLastCommonReadMessageAction)
				.toHaveBeenCalledWith(expect.anything(), { token: TOKEN, lastCommonReadMessage: 123 })

			expect(addGuestNameAction).toHaveBeenCalledWith(messages[1], { noUpdate: true })

			expect(store.getters.messagesList(TOKEN)).toStrictEqual(messages)
			expect(store.getters.getFirstKnownMessageId(TOKEN)).toBe(100)
			expect(store.getters.getLastKnownMessageId(TOKEN)).toBe(2)
		})

		test('fetches messages from server excluding last known', async () => {
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
			fetchMessages.mockResolvedValueOnce(response)

			await store.dispatch('fetchMessages', {
				token: TOKEN,
				lastKnownMessageId: 100,
				includeLastKnown: false,
				requestOptions: {
					dummyOption: true,
				},
				minimumVisible: 0,
			})

			expect(fetchMessages).toHaveBeenCalledWith({
				token: TOKEN,
				lastKnownMessageId: 100,
				includeLastKnown: false,
				limit: CHAT.FETCH_LIMIT,
			}, {
				dummyOption: true,
			})

			expect(updateLastCommonReadMessageAction)
				.toHaveBeenCalledWith(expect.anything(), { token: TOKEN, lastCommonReadMessage: 123 })

			expect(addGuestNameAction).toHaveBeenCalledWith(messages[1], { noUpdate: true })

			expect(store.getters.messagesList(TOKEN)).toStrictEqual(messages)
			expect(store.getters.getFirstKnownMessageId(TOKEN)).toBe(100)
			expect(store.getters.getLastKnownMessageId(TOKEN)).toBe(null)
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

			updateLastCommonReadMessageAction = jest.fn()
			addGuestNameAction = jest.fn()
			testStoreConfig.actions.updateLastCommonReadMessage = updateLastCommonReadMessageAction
			guestNameStore.addGuestName = addGuestNameAction

			cancelFunctionMock = jest.fn()
			CancelableRequest.mockImplementation((request) => {
				return {
					request,
					cancel: cancelFunctionMock,
				}
			})

			store = new Vuex.Store(testStoreConfig)
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
			expect(store.getters.getFirstKnownMessageId(TOKEN)).toBe(1)
			expect(store.getters.getLastKnownMessageId(TOKEN)).toBe(2)
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
				limit: CHAT.FETCH_LIMIT,
			}, undefined)

			expect(updateLastCommonReadMessageAction).toHaveBeenCalledTimes(2)
			expect(updateLastCommonReadMessageAction).toHaveBeenNthCalledWith(1, expect.anything(), { token: TOKEN, lastCommonReadMessage: 2 })
			expect(updateLastCommonReadMessageAction).toHaveBeenNthCalledWith(2, expect.anything(), { token: TOKEN, lastCommonReadMessage: 2 })

			expect(addGuestNameAction).toHaveBeenCalledWith(messagesContext[1], { noUpdate: true })

			expect(store.getters.messagesList(TOKEN)).toStrictEqual([...messagesFetch, ...messagesContext])
			expect(store.getters.getFirstKnownMessageId(TOKEN)).toBe(1)
			expect(store.getters.getLastKnownMessageId(TOKEN)).toBe(4)
		})
	})

	describe('look for new messages', () => {
		let updateLastCommonReadMessageAction
		let updateConversationLastMessageAction
		let updateUnreadMessagesMutation
		let addGuestNameAction
		let cancelFunctionMocks
		let conversationMock
		let getActorIdMock
		let getActorTypeMock
		let isActorUserMock
		let isActorGuestMock
		let getUserIdMock

		beforeEach(() => {
			testStoreConfig = cloneDeep(messagesStore)
			const guestNameStore = useGuestNameStore()

			conversationMock = jest.fn()
			getActorIdMock = jest.fn()
			getActorTypeMock = jest.fn()
			isActorUserMock = jest.fn()
			isActorGuestMock = jest.fn()
			getUserIdMock = jest.fn()
			testStoreConfig.getters.conversation = jest.fn().mockReturnValue(conversationMock)
			testStoreConfig.getters.getActorId = jest.fn().mockReturnValue(getActorIdMock)
			testStoreConfig.getters.getActorType = jest.fn().mockReturnValue(getActorTypeMock)
			testStoreConfig.getters.isActorUser = jest.fn().mockReturnValue(isActorUserMock)
			testStoreConfig.getters.isActorGuest = jest.fn().mockReturnValue(isActorGuestMock)
			testStoreConfig.getters.getUserId = jest.fn().mockReturnValue(getUserIdMock)

			updateConversationLastMessageAction = jest.fn()
			updateLastCommonReadMessageAction = jest.fn()
			updateUnreadMessagesMutation = jest.fn()
			addGuestNameAction = jest.fn()
			testStoreConfig.actions.updateConversationLastMessage = updateConversationLastMessageAction
			testStoreConfig.actions.updateLastCommonReadMessage = updateLastCommonReadMessageAction
			guestNameStore.addGuestName = addGuestNameAction
			testStoreConfig.mutations.updateUnreadMessages = updateUnreadMessagesMutation

			cancelFunctionMocks = []
			CancelableRequest.mockImplementation((request) => {
				const cancelFunctionMock = jest.fn()
				cancelFunctionMocks.push(cancelFunctionMock)
				return {
					request,
					cancel: cancelFunctionMock,
				}
			})

			store = new Vuex.Store(testStoreConfig)
		})

		afterEach(() => {
			jest.clearAllMocks()
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
			lookForNewMessages.mockResolvedValueOnce(response)

			// smaller number to make it update
			conversationMock.mockReturnValue({
				lastMessage: { id: 1 },
			})

			await store.dispatch('lookForNewMessages', {
				token: TOKEN,
				requestId: 'request1',
				lastKnownMessageId: 100,
				requestOptions: {
					dummyOption: true,
				},
			})

			expect(lookForNewMessages).toHaveBeenCalledWith({
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
			expect(store.getters.getLastKnownMessageId(TOKEN)).toBe(100)

			// not updated
			expect(store.getters.getFirstKnownMessageId(TOKEN)).toBe(null)
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
			lookForNewMessages.mockResolvedValueOnce(response)

			// smaller number to make it update
			conversationMock.mockReturnValue({ lastMessage: { id: 500 } })

			await store.dispatch('lookForNewMessages', {
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

			expect(store.getters.getLastKnownMessageId(TOKEN)).toBe(null)
		})

		test('does not look for new messages if lastKnownMessageId is falsy', async () => {
			// Arrange: prepare cancelable request from previous call of the function
			const cancelFunctionMock = jest.fn()
			cancelFunctionMocks.push(cancelFunctionMock)
			store.commit('setCancelLookForNewMessages', { cancelFunction: cancelFunctionMock, requestId: 'request1' })
			console.warn = jest.fn()

			// Act
			store.dispatch('lookForNewMessages', {
				token: TOKEN,
				requestId: 'request1',
				lastKnownMessageId: null,
			})

			// Assert
			expect(cancelFunctionMocks[0]).toHaveBeenCalledWith('canceled')
			expect(lookForNewMessages).not.toHaveBeenCalled()
		})

		test('cancels look for new messages', async () => {
			store.dispatch('lookForNewMessages', {
				token: TOKEN,
				requestId: 'request1',
				lastKnownMessageId: 100,
			}).catch(() => {})

			expect(cancelFunctionMocks[0]).not.toHaveBeenCalled()

			store.dispatch('cancelLookForNewMessages', { requestId: 'request1' })

			expect(cancelFunctionMocks[0]).toHaveBeenCalledWith('canceled')
		})

		test('cancels look for new messages when called again', async () => {
			store.dispatch('lookForNewMessages', {
				token: TOKEN,
				requestId: 'request1',
				lastKnownMessageId: 100,
			}).catch(() => {})

			store.dispatch('lookForNewMessages', {
				token: TOKEN,
				requestId: 'request1',
				lastKnownMessageId: 100,
			}).catch(() => {})

			expect(cancelFunctionMocks[0]).toHaveBeenCalledWith('canceled')
		})

		test('cancels look for new messages call individually', async () => {
			store.dispatch('lookForNewMessages', {
				token: TOKEN,
				requestId: 'request1',
				lastKnownMessageId: 100,
			}).catch(() => {})

			store.dispatch('lookForNewMessages', {
				token: TOKEN,
				requestId: 'request2',
				lastKnownMessageId: 100,
			}).catch(() => {})

			store.dispatch('cancelLookForNewMessages', { requestId: 'request1' })
			expect(cancelFunctionMocks[0]).toHaveBeenCalledWith('canceled')
			expect(cancelFunctionMocks[1]).not.toHaveBeenCalled()

			store.dispatch('cancelLookForNewMessages', { requestId: 'request2' })
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
				lookForNewMessages.mockResolvedValueOnce(response)

				// smaller number to make it update
				conversationMock.mockReturnValue(testConversation)

				await store.dispatch('lookForNewMessages', {
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
					getActorIdMock.mockReturnValue('me_as_guest')
					getActorTypeMock.mockReturnValue(ATTENDEE.ACTOR_TYPE.GUESTS)
					isActorUserMock.mockReturnValue(false)
					isActorGuestMock.mockReturnValue(true)
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
					getActorIdMock.mockReturnValue('me_as_guest')
					getActorTypeMock.mockReturnValue(ATTENDEE.ACTOR_TYPE.GUESTS)
					await testMentionFlag({
						'mention-1': {
							type: 'guest',
							id: 'guest/someone_else_as_guest',
						},
					}, undefined)
				})

				test('updates unread mention flag for user mention', async () => {
					getUserIdMock.mockReturnValue('me_as_user')
					getActorIdMock.mockReturnValue('me_as_user')
					getActorTypeMock.mockReturnValue(ATTENDEE.ACTOR_TYPE.USERS)
					isActorUserMock.mockReturnValue(true)
					isActorGuestMock.mockReturnValue(false)
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
					getActorIdMock.mockReturnValue('me_as_user')
					getActorTypeMock.mockReturnValue(ATTENDEE.ACTOR_TYPE.USERS)
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
		let getUserIdMock
		let updateLastCommonReadMessageAction
		let updateConversationLastMessageAction
		let cancelFunctionMocks

		beforeEach(() => {
			testStoreConfig = cloneDeep(messagesStore)

			jest.useFakeTimers()

			console.error = jest.fn()

			conversationMock = jest.fn()
			getUserIdMock = jest.fn()
			updateConversationLastMessageAction = jest.fn()
			updateLastCommonReadMessageAction = jest.fn()
			testStoreConfig.getters.conversation = jest.fn().mockReturnValue(conversationMock)
			testStoreConfig.getters.getUserId = jest.fn().mockReturnValue(getUserIdMock)
			testStoreConfig.actions.updateConversationLastMessage = updateConversationLastMessageAction
			testStoreConfig.actions.updateLastCommonReadMessage = updateLastCommonReadMessageAction
			// mock this complex local action as we already tested it elsewhere
			testStoreConfig.actions.updateConversationLastActive = updateConversationLastActiveAction
			testStoreConfig.actions.updateConversationLastReadMessage = jest.fn()
			testStoreConfig.actions.addConversation = jest.fn()

			cancelFunctionMocks = []
			CancelableRequest.mockImplementation((request) => {
				const cancelFunctionMock = jest.fn()
				cancelFunctionMocks.push(cancelFunctionMock)
				return {
					request,
					cancel: cancelFunctionMock,
				}
			})

			store = new Vuex.Store(testStoreConfig)
			message1 = {
				id: 1,
				token: TOKEN,
				message: 'first',
			}

			store.dispatch('processMessage', { token: TOKEN, message: message1 })
		})

		afterEach(() => {
			jest.clearAllMocks()
		})

		test('posts new message', async () => {
			conversationMock.mockReturnValue({
				token: TOKEN,
				lastMessage: { id: 100 },
				lastReadMessage: 50,
			})
			getUserIdMock.mockReturnValue(() => 'current-user')

			const temporaryMessage = {
				id: 'temp-123',
				message: 'blah',
				token: TOKEN,
				sendingFailure: '',
			}

			const messageResponse = {
				id: 200,
				token: TOKEN,
				message: 'blah',
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
				}
			})
			updateLastReadMessage.mockResolvedValue(response2)
			store.dispatch('postNewMessage', { token: TOKEN, temporaryMessage, options: { silent: false } }).catch(() => {
			})
			expect(postNewMessage).toHaveBeenCalledWith(temporaryMessage, { silent: false })
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

			console.error = jest.fn()

			postNewMessage.mockRejectedValueOnce({ isAxiosError: true, response })
			await expect(
				store.dispatch('postNewMessage', { token: TOKEN, temporaryMessage, options: { silent: false } })
			).rejects.toMatchObject({ response })

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

			jest.advanceTimersByTime(60000)

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

			jest.advanceTimersByTime(60000)

			expect(cancelFunctionMocks[0]).not.toHaveBeenCalled()
		})

	})

	describe('hasMoreMessagesToLoad', () => {
		/**
		 * @param {number} lastKnownMessageId The last known/loaded message id
		 * @param {number} lastConversationMessageId The last message id of the conversation
		 */
		function setupWithValues(lastKnownMessageId, lastConversationMessageId) {
			store.dispatch('setLastKnownMessageId', { token: TOKEN, id: 123 })
			conversationMock.mockReturnValue({
				token: TOKEN,
				lastMessage: { id: lastConversationMessageId },
			})
			store.dispatch('setLastKnownMessageId', { token: TOKEN, id: lastKnownMessageId })
		}

		test('returns true if more messages are available on the server', () => {
			setupWithValues(100, 123)
			expect(store.getters.hasMoreMessagesToLoad(TOKEN)).toBe(true)
		})
		test('returns false if no more messages are available on the server', () => {
			setupWithValues(123, 123)
			expect(store.getters.hasMoreMessagesToLoad(TOKEN)).toBe(false)
		})
		test('returns false if known last message id is past the one from known conversation', () => {
			setupWithValues(200, 123)
			expect(store.getters.hasMoreMessagesToLoad(TOKEN)).toBe(false)
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
			expect(postNewMessage).toHaveBeenCalledWith(messageExpected, { silent: false })
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
			expect(postNewMessage).toHaveBeenCalledWith(messageExpected, { silent: false })
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
			expect(store.getters.conversationsList).toContain(conversations[1])
			expect(postNewMessage).toHaveBeenCalledWith(messageExpected, { silent: false })
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
			expect(postNewMessage).toHaveBeenCalledWith(messageExpected, { silent: false })
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
			expect(postNewMessage).toHaveBeenCalledWith(messageExpected, { silent: false })
		})
	})
})
