import mockConsole from 'jest-mock-console'
import { createLocalVue } from '@vue/test-utils'
import messagesStore from './messagesStore'
import Vuex from 'vuex'
import { cloneDeep } from 'lodash'
import {
	ATTENDEE,
} from '../constants'
import {
	deleteMessage,
	updateLastReadMessage,
	fetchMessages,
	lookForNewMessages,
	postNewMessage,
} from '../services/messagesService'
import CancelableRequest from '../utils/cancelableRequest'
import { showError } from '@nextcloud/dialogs'

jest.mock('../services/messagesService', () => ({
	deleteMessage: jest.fn(),
	updateLastReadMessage: jest.fn(),
	fetchMessages: jest.fn(),
	lookForNewMessages: jest.fn(),
	postNewMessage: jest.fn(),
}))

jest.mock('../utils/cancelableRequest')
jest.mock('@nextcloud/dialogs', () => ({
	showError: jest.fn(),
}))

describe('messagesStore', () => {
	const TOKEN = 'XXTOKENXX'
	let localVue = null
	let testStoreConfig
	let store = null
	let updateConversationLastActiveAction

	beforeEach(() => {
		localVue = createLocalVue()
		localVue.use(Vuex)

		testStoreConfig = cloneDeep(messagesStore)

		updateConversationLastActiveAction = jest.fn()
		testStoreConfig.actions.updateConversationLastActive = updateConversationLastActiveAction

		store = new Vuex.Store(testStoreConfig)
	})

	afterEach(() => {
		jest.clearAllMocks()
	})

	describe('processMessage', () => {
		test('adds message to the list by token', () => {
			const message1 = {
				id: 1,
				token: TOKEN,
			}

			store.dispatch('processMessage', message1)
			expect(store.getters.messagesList(TOKEN)[0]).toBe(message1)
		})

		test('adds message with its parent to the list', () => {
			const parentMessage = {
				id: 1,
				token: TOKEN,
			}
			const message1 = {
				id: 2,
				token: TOKEN,
				parent: parentMessage,
			}

			store.dispatch('processMessage', message1)
			expect(store.getters.messagesList(TOKEN)[0]).toBe(parentMessage)
			expect(store.getters.messagesList(TOKEN)[1]).toStrictEqual({
				id: 2,
				token: TOKEN,
				parent: 1,
			})
		})

		test('deletes matching temporary message when referenced', () => {
			const temporaryMessage = {
				id: 'temp-1',
				referenceId: 'reference-1',
				token: TOKEN,
			}
			store.dispatch('addTemporaryMessage', temporaryMessage)

			const message1 = {
				id: 1,
				token: TOKEN,
				referenceId: 'reference-1',
			}

			store.dispatch('processMessage', message1)
			expect(store.getters.messagesList(TOKEN)).toStrictEqual([message1])
		})

		test('replaces existing message', () => {
			const message1 = {
				id: 1,
				token: TOKEN,
				message: 'hello',
			}
			const message2 = Object.assign({}, message1, { message: 'replaced' })

			store.dispatch('processMessage', message1)
			store.dispatch('processMessage', message2)
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

		store.dispatch('processMessage', message1)
		store.dispatch('processMessage', message2)
		store.dispatch('processMessage', message3)
		expect(store.getters.messagesList(TOKEN)[0]).toStrictEqual(message1)
		expect(store.getters.messagesList(TOKEN)[1]).toStrictEqual(message3)
		expect(store.getters.messagesList('token-2')[0]).toStrictEqual(message2)

		// by id
		expect(store.getters.messages(TOKEN)[1]).toStrictEqual(message1)
		expect(store.getters.messages(TOKEN)[3]).toStrictEqual(message3)
		expect(store.getters.messages('token-2')[2]).toStrictEqual(message2)

		// with messages getter
		expect(store.getters.messages(TOKEN)).toStrictEqual({
			1: message1,
			3: message3,
		})
		expect(store.getters.messages('token-2')).toStrictEqual({
			2: message2,
		})
	})

	describe('delete message', () => {
		let message

		beforeEach(() => {
			message = {
				id: 10,
				token: TOKEN,
				message: 'hello',
			}

			store.dispatch('processMessage', message)
		})

		test('deletes from server and replaces with returned system message', async () => {
			deleteMessage.mockResolvedValueOnce({
				status: 200,
				data: {
					ocs: {
						data: {
							id: 10,
							token: TOKEN,
							message: '(deleted)',
						},
					},
				},
			})

			const status = await store.dispatch('deleteMessage', { message, placeholder: 'placeholder-text' })

			expect(deleteMessage).toHaveBeenCalledWith(message)
			expect(status).toBe(200)

			expect(store.getters.messagesList(TOKEN)).toStrictEqual([{
				id: 10,
				token: TOKEN,
				message: '(deleted)',
				messageType: 'comment_deleted',
			}])
		})

		test('deletes from server and replaces with returned system message including parent', async () => {
			deleteMessage.mockResolvedValueOnce({
				status: 200,
				data: {
					ocs: {
						data: {
							id: 10,
							token: TOKEN,
							message: '(deleted)',
							parent: {
								id: 5,
								token: TOKEN,
								message: 'parent message',
							},
						},
					},
				},
			})

			const status = await store.dispatch('deleteMessage', { message, placeholder: 'placeholder-text' })

			expect(deleteMessage).toHaveBeenCalledWith(message)
			expect(status).toBe(200)

			expect(store.getters.messagesList(TOKEN)).toStrictEqual([{
				id: 5,
				token: TOKEN,
				message: 'parent message',
			}, {
				id: 10,
				token: TOKEN,
				message: '(deleted)',
				messageType: 'comment_deleted',
				parent: 5,
			}])
		})

		test('shows placeholder while deletion is in progress', () => {
			store.dispatch('deleteMessage', {
				message,
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

	test('deletes messages by token from store only', () => {
		const message1 = {
			id: 1,
			token: TOKEN,
		}

		store.dispatch('processMessage', message1)
		expect(store.getters.messagesList(TOKEN)[0]).toBe(message1)

		store.dispatch('deleteMessages', TOKEN)
		expect(store.getters.messagesList(TOKEN)).toStrictEqual([])

		expect(deleteMessage).not.toHaveBeenCalled()
	})

	describe('temporary messages', () => {
		let mockDate
		let getMessageToBeRepliedMock
		let getActorIdMock
		let getActorTypeMock
		let getDisplayNameMock

		beforeEach(() => {
			mockDate = new Date('2020-01-01 20:00:00')
			jest.spyOn(global, 'Date')
				.mockImplementation(() => mockDate)

			testStoreConfig = cloneDeep(messagesStore)

			getMessageToBeRepliedMock = jest.fn().mockReturnValue(() => undefined)
			getActorIdMock = jest.fn().mockReturnValue(() => 'actor-id-1')
			getActorTypeMock = jest.fn().mockReturnValue(() => ATTENDEE.ACTOR_TYPE.USERS)
			getDisplayNameMock = jest.fn().mockReturnValue(() => 'actor-display-name-1')
			testStoreConfig.getters.getMessageToBeReplied = getMessageToBeRepliedMock
			testStoreConfig.getters.getActorId = getActorIdMock
			testStoreConfig.getters.getActorType = getActorTypeMock
			testStoreConfig.getters.getDisplayName = getDisplayNameMock
			testStoreConfig.actions.updateConversationLastActive = updateConversationLastActiveAction

			store = new Vuex.Store(testStoreConfig)
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

			expect(getMessageToBeRepliedMock).toHaveBeenCalled()
			expect(getActorIdMock).toHaveBeenCalled()
			expect(getActorTypeMock).toHaveBeenCalled()
			expect(getDisplayNameMock).toHaveBeenCalled()

			expect(temporaryMessage).toStrictEqual({
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
			getMessageToBeRepliedMock.mockReset()
			getMessageToBeRepliedMock.mockReturnValue(() => ({
				id: 123,
			}))

			const temporaryMessage = await store.dispatch('createTemporaryMessage', {
				text: 'blah',
				token: TOKEN,
				uploadId: null,
				index: null,
				file: null,
				localUrl: null,
			})

			expect(temporaryMessage).toStrictEqual({
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
				parent: 123,
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

			expect(temporaryMessage).toStrictEqual({
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

			store.dispatch('addTemporaryMessage', temporaryMessage)

			expect(store.getters.messagesList(TOKEN)).toStrictEqual([{
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
			temporaryMessage.message = 'replaced'
			store.dispatch('addTemporaryMessage', temporaryMessage)

			expect(store.getters.messagesList(TOKEN)).toStrictEqual([{
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

			store.dispatch('addTemporaryMessage', temporaryMessage)
			store.dispatch('markTemporaryMessageAsFailed', {
				message: temporaryMessage,
				reason: 'failure-reason',
			})

			expect(store.getters.messagesList(TOKEN)).toStrictEqual([{
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

			store.dispatch('addTemporaryMessage', temporaryMessage)
			store.dispatch('removeTemporaryMessageFromStore', temporaryMessage)

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

			store.dispatch('addTemporaryMessage', temporaryMessage)

			expect(store.getters.getTemporaryReferences(TOKEN, temporaryMessage.referenceId)).toStrictEqual([{
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
		let conversationsMock
		let markConversationReadAction
		let getUserIdMock
		let updateConversationLastReadMessageMock

		beforeEach(() => {
			const conversations = {}
			conversations[TOKEN] = {
				lastMessage: {
					id: 123,
				},
			}

			testStoreConfig = cloneDeep(messagesStore)

			getUserIdMock = jest.fn()
			conversationsMock = jest.fn().mockReturnValue(conversations)
			markConversationReadAction = jest.fn()
			updateConversationLastReadMessageMock = jest.fn()
			testStoreConfig.getters.conversations = conversationsMock
			testStoreConfig.getters.getUserId = getUserIdMock
			testStoreConfig.actions.markConversationRead = markConversationReadAction
			testStoreConfig.actions.updateConversationLastReadMessage = updateConversationLastReadMessageMock

			updateLastReadMessage.mockResolvedValueOnce()

			store = new Vuex.Store(testStoreConfig)
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

			expect(conversationsMock).toHaveBeenCalled()
			expect(markConversationReadAction).toHaveBeenCalledWith(expect.anything(), TOKEN)
			expect(getUserIdMock).toHaveBeenCalled()
			expect(updateConversationLastReadMessageMock).toHaveBeenCalledWith(expect.anything(), {
				token: TOKEN,
				lastReadMessage: 123,
			})

			expect(updateLastReadMessage).toHaveBeenCalledWith(TOKEN, 123)
			expect(store.getters.getVisualLastReadMessageId(TOKEN)).toBe(100)
		})

		test('clears last read message and update visually', async () => {
			getUserIdMock.mockReturnValue(() => 'user-1')

			store.dispatch('setVisualLastReadMessageId', { token: TOKEN, id: 100 })
			await store.dispatch('clearLastReadMessage', {
				token: TOKEN,
				updateVisually: true,
			})

			expect(conversationsMock).toHaveBeenCalled()
			expect(markConversationReadAction).toHaveBeenCalledWith(expect.anything(), TOKEN)
			expect(getUserIdMock).toHaveBeenCalled()
			expect(updateConversationLastReadMessageMock).toHaveBeenCalledWith(expect.anything(), {
				token: TOKEN,
				lastReadMessage: 123,
			})

			expect(updateLastReadMessage).toHaveBeenCalledWith(TOKEN, 123)
			expect(store.getters.getVisualLastReadMessageId(TOKEN)).toBe(123)
		})

		test('clears last read message for guests', async () => {
			getUserIdMock.mockReturnValue(() => null)

			store.dispatch('setVisualLastReadMessageId', { token: TOKEN, id: 100 })
			await store.dispatch('clearLastReadMessage', {
				token: TOKEN,
				updateVisually: true,
			})

			expect(conversationsMock).toHaveBeenCalled()
			expect(markConversationReadAction).toHaveBeenCalledWith(expect.anything(), TOKEN)
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

			store.dispatch('setVisualLastReadMessageId', { token: TOKEN, id: 100 })
			await store.dispatch('updateLastReadMessage', {
				token: TOKEN,
				id: 200,
				updateVisually: false,
			})

			expect(conversationsMock).toHaveBeenCalled()
			expect(markConversationReadAction).not.toHaveBeenCalled()
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

			store.dispatch('setVisualLastReadMessageId', { token: TOKEN, id: 100 })
			await store.dispatch('updateLastReadMessage', {
				token: TOKEN,
				id: 200,
				updateVisually: true,
			})

			expect(conversationsMock).toHaveBeenCalled()
			expect(markConversationReadAction).not.toHaveBeenCalled()
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

			expect(conversationsMock).toHaveBeenCalled()
			expect(markConversationReadAction).not.toHaveBeenCalled()
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
		let setGuestNameIfEmptyAction
		let cancelFunctionMock

		beforeEach(() => {
			testStoreConfig = cloneDeep(messagesStore)

			updateLastCommonReadMessageAction = jest.fn()
			setGuestNameIfEmptyAction = jest.fn()
			testStoreConfig.actions.updateLastCommonReadMessage = updateLastCommonReadMessageAction
			testStoreConfig.actions.setGuestNameIfEmpty = setGuestNameIfEmptyAction

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
			const response = {
				headers: {
					'x-chat-last-common-read': '123',
					'x-chat-last-given': '100',
				},
				data: {
					ocs: {
						data: messages,
					},
				},
			}

			fetchMessages.mockResolvedValueOnce(response)

			await store.dispatch('fetchMessages', {
				token: TOKEN,
				lastKnownMessageId: 100,
				includeLastKnown: true,
				requestOptions: {
					dummyOption: true,
				},
			})

			expect(fetchMessages).toHaveBeenCalledWith({
				token: TOKEN,
				lastKnownMessageId: 100,
				includeLastKnown: true,
			}, {
				dummyOption: true,
			})

			expect(updateLastCommonReadMessageAction)
				.toHaveBeenCalledWith(expect.anything(), { token: TOKEN, lastCommonReadMessage: 123 })

			expect(setGuestNameIfEmptyAction).toHaveBeenCalledWith(expect.anything(), messages[1])

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
			const response = {
				headers: {
					'x-chat-last-common-read': '123',
					'x-chat-last-given': '100',
				},
				data: {
					ocs: {
						data: messages,
					},
				},
			}

			fetchMessages.mockResolvedValueOnce(response)

			await store.dispatch('fetchMessages', {
				token: TOKEN,
				lastKnownMessageId: 100,
				includeLastKnown: false,
				requestOptions: {
					dummyOption: true,
				},
			})

			expect(fetchMessages).toHaveBeenCalledWith({
				token: TOKEN,
				lastKnownMessageId: 100,
				includeLastKnown: false,
			}, {
				dummyOption: true,
			})

			expect(updateLastCommonReadMessageAction)
				.toHaveBeenCalledWith(expect.anything(), { token: TOKEN, lastCommonReadMessage: 123 })

			expect(setGuestNameIfEmptyAction).toHaveBeenCalledWith(expect.anything(), messages[1])

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

	describe('look for new messages', () => {
		let updateLastCommonReadMessageAction
		let updateConversationLastMessageAction
		let updateUnreadMessagesMutation
		let forceGuestNameAction
		let cancelFunctionMocks
		let conversationMock

		beforeEach(() => {
			testStoreConfig = cloneDeep(messagesStore)

			conversationMock = jest.fn()
			updateConversationLastMessageAction = jest.fn()
			updateLastCommonReadMessageAction = jest.fn()
			updateUnreadMessagesMutation = jest.fn()
			forceGuestNameAction = jest.fn()
			testStoreConfig.getters.conversation = jest.fn().mockReturnValue(conversationMock)
			testStoreConfig.actions.updateConversationLastMessage = updateConversationLastMessageAction
			testStoreConfig.actions.updateLastCommonReadMessage = updateLastCommonReadMessageAction
			testStoreConfig.actions.forceGuestName = forceGuestNameAction
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
			const response = {
				headers: {
					'x-chat-last-common-read': '123',
					'x-chat-last-given': '100',
				},
				data: {
					ocs: {
						data: messages,
					},
				},
			}

			lookForNewMessages.mockResolvedValueOnce(response)

			// smaller number to make it update
			conversationMock.mockReturnValue({
				lastMessage: { id: 1 },
			})

			await store.dispatch('lookForNewMessages', {
				token: TOKEN,
				lastKnownMessageId: 100,
				requestOptions: {
					dummyOption: true,
				},
			})

			expect(lookForNewMessages).toHaveBeenCalledWith({
				token: TOKEN,
				lastKnownMessageId: 100,
			}, {
				dummyOption: true,
			})

			expect(conversationMock).toHaveBeenCalledWith(TOKEN)
			expect(updateConversationLastMessageAction)
				.toHaveBeenCalledWith(expect.anything(), { token: TOKEN, lastMessage: messages[1] })
			expect(updateLastCommonReadMessageAction)
				.toHaveBeenCalledWith(expect.anything(), { token: TOKEN, lastCommonReadMessage: 123 })

			expect(forceGuestNameAction).toHaveBeenCalledWith(expect.anything(), messages[1])

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
			const response = {
				headers: {},
				data: {
					ocs: {
						data: messages,
					},
				},
			}

			lookForNewMessages.mockResolvedValueOnce(response)

			// smaller number to make it update
			conversationMock.mockReturnValue({ lastMessage: { id: 500 } })

			await store.dispatch('lookForNewMessages', {
				token: TOKEN,
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
				const response = {
					headers: {
						'x-chat-last-common-read': '123',
						'x-chat-last-given': '100',
					},
					data: {
						ocs: {
							data: messages,
						},
					},
				}

				lookForNewMessages.mockResolvedValueOnce(response)

				// smaller number to make it update
				conversationMock.mockReturnValue(testConversation)

				await store.dispatch('lookForNewMessages', {
					token: TOKEN,
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
				let getActorIdMock
				let getActorTypeMock
				let getUserIdMock

				beforeEach(() => {
					getActorIdMock = jest.fn()
					getActorTypeMock = jest.fn()
					getUserIdMock = jest.fn()
					testStoreConfig.getters.getActorId = getActorIdMock
					testStoreConfig.getters.getActorType = getActorTypeMock
					testStoreConfig.getters.getUserId = getUserIdMock

					store = new Vuex.Store(testStoreConfig)
				})

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
					getActorIdMock.mockReturnValue(() => 'me_as_guest')
					getActorTypeMock.mockReturnValue(() => ATTENDEE.ACTOR_TYPE.GUESTS)
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
					getActorIdMock.mockReturnValue(() => 'me_as_guest')
					getActorTypeMock.mockReturnValue(() => ATTENDEE.ACTOR_TYPE.GUESTS)
					await testMentionFlag({
						'mention-1': {
							type: 'guest',
							id: 'guest/someone_else_as_guest',
						},
					}, undefined)
				})

				test('updates unread mention flag for user mention', async () => {
					getUserIdMock.mockReturnValue(() => 'me_as_user')
					getActorTypeMock.mockReturnValue(() => ATTENDEE.ACTOR_TYPE.USERS)
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
					getUserIdMock.mockReturnValue(() => 'me_as_user')
					getActorTypeMock.mockReturnValue(() => ATTENDEE.ACTOR_TYPE.USERS)
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
		let updateLastReadMessageAction
		let updateConversationLastMessageAction
		let cancelFunctionMocks
		let restoreConsole

		beforeEach(() => {
			testStoreConfig = cloneDeep(messagesStore)

			jest.useFakeTimers()

			restoreConsole = mockConsole(['error'])
			conversationMock = jest.fn()
			updateConversationLastMessageAction = jest.fn()
			updateLastCommonReadMessageAction = jest.fn()
			updateLastReadMessageAction = jest.fn()
			testStoreConfig.getters.conversation = jest.fn().mockReturnValue(conversationMock)
			testStoreConfig.actions.updateConversationLastMessage = updateConversationLastMessageAction
			testStoreConfig.actions.updateLastCommonReadMessage = updateLastCommonReadMessageAction
			// mock this complex local action as we already tested it elsewhere
			testStoreConfig.actions.updateLastReadMessage = updateLastReadMessageAction
			testStoreConfig.actions.updateConversationLastActive = updateConversationLastActiveAction

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

			store.dispatch('processMessage', message1)
		})

		afterEach(() => {
			restoreConsole()
		})

		test('posts new message', async () => {
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

			const response = {
				headers: {
					'x-chat-last-common-read': '100',
				},
				data: {
					ocs: {
						data: messageResponse,
					},
				},
			}

			store.dispatch('addTemporaryMessage', temporaryMessage)

			conversationMock.mockReturnValue({
				token: TOKEN,
				lastMessage: { id: 100 },
				lastReadMessage: 50,
			})

			let resolvePromise
			postNewMessage.mockReturnValueOnce(new Promise((resolve, reject) => {
				resolvePromise = resolve
			}))

			const returnedPromise = store.dispatch('postNewMessage', temporaryMessage).catch(() => {})
			expect(store.getters.isSendingMessages).toBe(true)

			resolvePromise(response)

			const receivedResponse = await returnedPromise

			expect(store.getters.isSendingMessages).toBe(false)

			expect(receivedResponse).toBe(response)

			expect(postNewMessage).toHaveBeenCalledWith(temporaryMessage)

			expect(updateLastCommonReadMessageAction).toHaveBeenCalledWith(
				expect.anything(),
				{ token: TOKEN, lastCommonReadMessage: 100 },
			)

			expect(store.getters.messagesList(TOKEN)).toStrictEqual([message1, messageResponse])

			expect(updateConversationLastMessageAction)
				.toHaveBeenCalledWith(expect.anything(), { token: TOKEN, lastMessage: messageResponse })

			expect(updateLastReadMessageAction).toHaveBeenCalledWith(expect.anything(), {
				token: TOKEN,
				id: 200,
				updateVisually: true,
			})
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

			store.dispatch('postNewMessage', temporaryMessage).catch(() => {})
			store.dispatch('postNewMessage', temporaryMessage2).catch(() => {})

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

			store.dispatch('addTemporaryMessage', temporaryMessage)

			postNewMessage.mockRejectedValueOnce({ isAxiosError: true, response })
			await expect(
				store.dispatch('postNewMessage', temporaryMessage)
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

			store.dispatch('addTemporaryMessage', temporaryMessage)
			store.dispatch('postNewMessage', temporaryMessage).catch(() => {})

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

			const response = {
				headers: {},
				data: {
					ocs: {
						data: {
							id: 200,
							token: TOKEN,
							message: 'blah',
						},
					},
				},
			}

			postNewMessage.mockResolvedValueOnce(response)

			store.dispatch('addTemporaryMessage', temporaryMessage)
			await store.dispatch('postNewMessage', temporaryMessage)

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
			const conversationMock = jest.fn().mockReturnValue({
				lastMessage: { id: lastConversationMessageId },
			})
			testStoreConfig.getters.conversation = jest.fn().mockReturnValue(conversationMock)
			store = new Vuex.Store(testStoreConfig)
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
})
