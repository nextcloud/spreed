import { createLocalVue } from '@vue/test-utils'
import storeConfig from './storeConfig'
import Vuex from 'vuex'
import { cloneDeep } from 'lodash'
import {
	CONVERSATION,
	WEBINAR,
	PARTICIPANT,
	ATTENDEE,
} from '../constants'
import {
	makePublic,
	makePrivate,
	addToFavorites,
	removeFromFavorites,
	changeLobbyState,
	changeReadOnlyState,
	changeListable,
	createOneToOneConversation,
	setConversationName,
	setConversationDescription,
	setNotificationLevel,
	setSIPEnabled,
	fetchConversation,
	fetchConversations,
	deleteConversation,
	setConversationPermissions,
	setCallPermissions,
} from '../services/conversationsService'

jest.mock('../services/conversationsService', () => ({
	makePublic: jest.fn(),
	makePrivate: jest.fn(),
	addToFavorites: jest.fn(),
	removeFromFavorites: jest.fn(),
	changeLobbyState: jest.fn(),
	changeReadOnlyState: jest.fn(),
	changeListable: jest.fn(),
	createOneToOneConversation: jest.fn(),
	setConversationName: jest.fn(),
	setConversationDescription: jest.fn(),
	setNotificationLevel: jest.fn(),
	setSIPEnabled: jest.fn(),
	fetchConversation: jest.fn(),
	fetchConversations: jest.fn(),
	deleteConversation: jest.fn(),
	setConversationPermissions: jest.fn(),
	setCallPermissions: jest.fn(),
}))

describe('conversationsStore', () => {
	const testToken = 'XXTOKENXX'
	const previousLastMessage = {
		actorType: 'users',
		actorId: 'admin',
		systemMessage: '',
		id: 31,
		message: 'Message 1',
	}
	let testStoreConfig = null
	let testConversation
	let localVue = null
	let store = null
	let addParticipantOnceAction = null
	const permissions = PARTICIPANT.PERMISSIONS.MAX_CUSTOM

	beforeEach(() => {
		localVue = createLocalVue()
		localVue.use(Vuex)

		testConversation = {
			token: testToken,
			participantFlags: PARTICIPANT.CALL_FLAG.DISCONNECTED,
			participantType: PARTICIPANT.TYPE.USER,
			lastPing: 600,
			sessionId: 'session-id-1',
			attendeeId: 'attendee-id-1',
			actorType: ATTENDEE.ACTOR_TYPE.USERS,
			actorId: 'actor-id',
			defaultPermissions: PARTICIPANT.PERMISSIONS.CUSTOM,
			callPermissions: PARTICIPANT.PERMISSIONS.CUSTOM,
		}

		testStoreConfig = cloneDeep(storeConfig)

		addParticipantOnceAction = jest.fn()
		testStoreConfig.modules.participantsStore.actions.addParticipantOnce = addParticipantOnceAction
	})

	afterEach(() => {
		jest.clearAllMocks()
	})

	describe('conversation list', () => {
		let deleteMessagesAction = null
		let checkMaintenanceModeAction = null
		let clearMaintenanceModeAction = null
		let updateTalkVersionHashAction = null

		beforeEach(() => {
			deleteMessagesAction = jest.fn()
			testStoreConfig.modules.messagesStore.actions.deleteMessages = deleteMessagesAction

			checkMaintenanceModeAction = jest.fn()
			clearMaintenanceModeAction = jest.fn()
			updateTalkVersionHashAction = jest.fn()
			testStoreConfig.modules.talkHashStore.actions.checkMaintenanceMode = checkMaintenanceModeAction
			testStoreConfig.modules.talkHashStore.actions.clearMaintenanceMode = clearMaintenanceModeAction
			testStoreConfig.modules.talkHashStore.actions.updateTalkVersionHash = updateTalkVersionHashAction

			store = new Vuex.Store(testStoreConfig)
		})

		test('adds conversation to the store, with current user as participant', () => {
			store.dispatch('setCurrentUser', {
				uid: 'current-user',
				displayName: 'display-name',
			})
			store.dispatch('addConversation', testConversation)

			expect(store.getters.conversation(testToken)).toBe(testConversation)
			expect(store.getters.conversation('ANOTHER')).toBeUndefined()

			expect(addParticipantOnceAction).toHaveBeenCalled()
			expect(addParticipantOnceAction.mock.calls[0][1]).toStrictEqual({
				token: testToken,
				participant: {
					actorId: 'actor-id',
					actorType: 'users',
					attendeeId: 'attendee-id-1',
					displayName: 'display-name',
					inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED,
					lastPing: 600,
					participantType: PARTICIPANT.TYPE.USER,
					sessionIds: [
					  'session-id-1',
					],
					userId: 'current-user',
				},
			})
		})

		test('adds conversation to the store, with empty user id for guests', () => {
			store.dispatch('setCurrentParticipant', {
				actorId: 'guestActorId',
				sessionId: 'XXSESSIONIDXX',
				participantType: PARTICIPANT.TYPE.GUEST,
			})

			store.dispatch('addConversation', testConversation)

			expect(store.getters.conversation(testToken)).toBe(testConversation)

			expect(addParticipantOnceAction).toHaveBeenCalled()
			expect(addParticipantOnceAction.mock.calls[0][1]).toStrictEqual({
				token: testToken,
				participant: {
					// the one from the conversation is taken...
					actorId: 'actor-id',
					actorType: 'users',
					attendeeId: 'attendee-id-1',
					displayName: '',
					inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED,
					lastPing: 600,
					participantType: PARTICIPANT.TYPE.USER,
					sessionIds: [
					  'session-id-1',
					],
					userId: '',
				},
			})
		})

		test('deletes messages with conversation', () => {
			store.dispatch('setCurrentUser', {
				uid: 'current-user',
				displayName: 'display-name',
			})
			store.dispatch('addConversation', testConversation)

			store.dispatch('deleteConversation', testToken)
			expect(deleteMessagesAction).toHaveBeenCalled()

			expect(store.getters.conversation(testToken)).toBeUndefined()

			// not deleted from server...
			expect(deleteConversation).not.toHaveBeenCalled()
		})

		test('purges all conversations', () => {
			const testConversation2 = Object.assign({}, testConversation, {
				token: 'XXANOTHERXX',
			})
			store.dispatch('addConversation', testConversation)
			store.dispatch('addConversation', testConversation2)

			store.dispatch('purgeConversationsStore')

			expect(store.getters.conversation(testToken)).toBeUndefined()
			expect(store.getters.conversation('XXANOTHERXX')).toBeUndefined()
			expect(store.getters.conversationsList).toStrictEqual([])
		})

		test('deletes conversation from server', async () => {
			store.dispatch('addConversation', testConversation)

			await store.dispatch('deleteConversationFromServer', { token: testToken })
			expect(deleteConversation).toHaveBeenCalledWith(testToken)
			expect(deleteMessagesAction).toHaveBeenCalled()

			expect(store.getters.conversation(testToken)).toBeUndefined()
		})

		test('fetches a single conversation', async () => {
			const response = {
				data: {
					ocs: {
						data: testConversation,
					},
				},
			}

			fetchConversation.mockResolvedValue(response)

			await store.dispatch('fetchConversation', { token: testToken })

			expect(fetchConversation).toHaveBeenCalledWith(testToken)

			const fetchedConversation = store.getters.conversation(testToken)
			expect(fetchedConversation).toBe(testConversation)

			expect(clearMaintenanceModeAction).toHaveBeenCalled()
			expect(updateTalkVersionHashAction).toHaveBeenCalledWith(expect.anything(), response)
		})

		test('fetches all conversations and adds them after purging', async () => {
			const testConversations = [
				{
					token: 'one_token',
					attendeeId: 'attendee-id-1',
				},
				{
					token: 'another_token',
					attendeeId: 'attendee-id-2',
				},
			]

			// add conversation that should be purged
			store.dispatch('addConversation', testConversation)

			const response = {
				data: {
					ocs: {
						data: testConversations,
					},
				},
			}

			fetchConversations.mockResolvedValue(response)

			await store.dispatch('fetchConversations')

			expect(fetchConversations).toHaveBeenCalledWith()
			expect(store.getters.conversationsList).toStrictEqual(testConversations)

			expect(clearMaintenanceModeAction).toHaveBeenCalled()
			expect(updateTalkVersionHashAction).toHaveBeenCalledWith(expect.anything(), response)
		})

		test('fetch conversation failure checks for maintenance mode', async () => {
			const response = { status: 503 }
			fetchConversation.mockRejectedValue({ response })

			await expect(store.dispatch('fetchConversation', { token: testToken })).rejects.toMatchObject({ response })

			expect(checkMaintenanceModeAction).toHaveBeenCalledWith(expect.anything(), response)
		})

		test('fetch conversations failure checks for maintenance mode', async () => {
			const response = { status: 503 }
			fetchConversations.mockRejectedValue({ response })

			await expect(store.dispatch('fetchConversations')).rejects.toMatchObject({ response })

			expect(checkMaintenanceModeAction).toHaveBeenCalledWith(expect.anything(), response)
		})

	})

	describe('conversation settings', () => {
		beforeEach(() => {
			store = new Vuex.Store(testStoreConfig)
		})

		test('make public', async () => {
			testConversation.type = CONVERSATION.TYPE.GROUP

			store.dispatch('addConversation', testConversation)

			makePublic.mockResolvedValue()

			await store.dispatch('toggleGuests', {
				token: testToken,
				allowGuests: true,
			})

			expect(makePublic).toHaveBeenCalledWith(testToken)

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.type).toEqual(CONVERSATION.TYPE.PUBLIC)
		})

		test('make non-public', async () => {
			testConversation.type = CONVERSATION.TYPE.PUBLIC

			store.dispatch('addConversation', testConversation)

			makePrivate.mockResolvedValue()

			await store.dispatch('toggleGuests', {
				token: testToken,
				allowGuests: false,
			})

			expect(makePrivate).toHaveBeenCalledWith(testToken)

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.type).toEqual(CONVERSATION.TYPE.GROUP)
		})

		test('set favorite', async () => {
			testConversation.isFavorite = false

			store.dispatch('addConversation', testConversation)

			addToFavorites.mockResolvedValue()

			await store.dispatch('toggleFavorite', {
				token: testToken,
				isFavorite: false,
			})

			expect(addToFavorites).toHaveBeenCalledWith(testToken)

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.isFavorite).toBe(true)
		})

		test('unset favorite', async () => {
			testConversation.isFavorite = true

			store.dispatch('addConversation', testConversation)

			removeFromFavorites.mockResolvedValue()

			await store.dispatch('toggleFavorite', {
				token: testToken,
				isFavorite: true,
			})

			expect(removeFromFavorites).toHaveBeenCalledWith(testToken)

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.isFavorite).toBe(false)
		})

		test('enable lobby', async () => {
			testConversation.lobbyState = WEBINAR.LOBBY.NONE

			store.dispatch('addConversation', testConversation)

			changeLobbyState.mockResolvedValue()

			await store.dispatch('toggleLobby', {
				token: testToken,
				enableLobby: true,
			})

			expect(changeLobbyState).toHaveBeenCalledWith(testToken, WEBINAR.LOBBY.NON_MODERATORS)

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.lobbyState).toBe(WEBINAR.LOBBY.NON_MODERATORS)
		})

		test('disable lobby', async () => {
			testConversation.lobbyState = WEBINAR.LOBBY.NON_MODERATORS

			store.dispatch('addConversation', testConversation)

			changeLobbyState.mockResolvedValue()

			await store.dispatch('toggleLobby', {
				token: testToken,
				enableLobby: false,
			})

			expect(changeLobbyState).toHaveBeenCalledWith(testToken, WEBINAR.LOBBY.NONE)

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.lobbyState).toBe(WEBINAR.LOBBY.NONE)
		})

		test('set conversation name', async () => {
			testConversation.displayName = 'initial name'

			store.dispatch('addConversation', testConversation)

			setConversationName.mockResolvedValue()

			await store.dispatch('setConversationName', {
				token: testToken,
				name: 'new name',
			})

			expect(setConversationName).toHaveBeenCalledWith(testToken, 'new name')

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.displayName).toBe('new name')
		})

		test('set conversation description', async () => {
			testConversation.description = 'initial description'

			store.dispatch('addConversation', testConversation)

			setConversationDescription.mockResolvedValue()

			await store.dispatch('setConversationDescription', {
				token: testToken,
				description: 'new description',
			})

			expect(setConversationDescription).toHaveBeenCalledWith(testToken, 'new description')

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.description).toBe('new description')
		})

		test('enable read-only', async () => {
			testConversation.readOnly = CONVERSATION.STATE.READ_WRITE

			store.dispatch('addConversation', testConversation)

			changeReadOnlyState.mockResolvedValue()

			await store.dispatch('setReadOnlyState', {
				token: testToken,
				readOnly: CONVERSATION.STATE.READ_ONLY,
			})

			expect(changeReadOnlyState).toHaveBeenCalledWith(testToken, CONVERSATION.STATE.READ_ONLY)

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.readOnly).toBe(CONVERSATION.STATE.READ_ONLY)
		})

		test('disable read-only', async () => {
			testConversation.readOnly = CONVERSATION.STATE.READ_ONLY

			store.dispatch('addConversation', testConversation)

			changeReadOnlyState.mockResolvedValue()

			await store.dispatch('setReadOnlyState', {
				token: testToken,
				readOnly: CONVERSATION.STATE.READ_WRITE,
			})

			expect(changeReadOnlyState).toHaveBeenCalledWith(testToken, CONVERSATION.STATE.READ_WRITE)

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.readOnly).toBe(CONVERSATION.STATE.READ_WRITE)
		})

		test('set listable flag', async () => {
			testConversation.readOnly = CONVERSATION.LISTABLE.NONE

			store.dispatch('addConversation', testConversation)

			changeListable.mockResolvedValue()

			await store.dispatch('setListable', {
				token: testToken,
				listable: CONVERSATION.LISTABLE.ALL,
			})

			expect(changeListable).toHaveBeenCalledWith(testToken, CONVERSATION.LISTABLE.ALL)

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.listable).toBe(CONVERSATION.LISTABLE.ALL)
		})

		test('set lobby timer', async () => {
			testConversation.lobbyState = WEBINAR.LOBBY.NON_MODERATORS
			testConversation.lobbyTimer = 1200300

			store.dispatch('addConversation', testConversation)

			changeLobbyState.mockResolvedValue()

			await store.dispatch('setLobbyTimer', {
				token: testToken,
				timestamp: 2300400,
			})

			expect(changeLobbyState).toHaveBeenCalledWith(testToken, WEBINAR.LOBBY.NON_MODERATORS, 2300400)

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.lobbyTimer).toBe(2300400)
		})

		test('set SIP enabled', async () => {
			testConversation.sipEnabled = false

			store.dispatch('addConversation', testConversation)

			setSIPEnabled.mockResolvedValue()

			await store.dispatch('setSIPEnabled', {
				token: testToken,
				state: true,
			})

			expect(setSIPEnabled).toHaveBeenCalledWith(testToken, true)

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.sipEnabled).toBe(true)
		})

		test('set notification level', async () => {
			testConversation.notificationLevel = 1

			store.dispatch('addConversation', testConversation)

			setNotificationLevel.mockResolvedValue()

			await store.dispatch('setNotificationLevel', {
				token: testToken,
				notificationLevel: 2,
			})

			expect(setNotificationLevel).toHaveBeenCalledWith(testToken, 2)

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.notificationLevel).toBe(2)
		})
	})

	describe('read marker', () => {
		beforeEach(() => {
			store = new Vuex.Store(testStoreConfig)
		})

		test('marks conversation as read by clearing unread counters', () => {
			testConversation.unreadMessages = 1024
			testConversation.unreadMention = true

			store.dispatch('addConversation', testConversation)

			store.dispatch('markConversationRead', testToken)

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.unreadMessages).toBe(0)
			expect(changedConversation.unreadMention).toBe(false)
		})

		test('updates last common read message', () => {
			testConversation.lastCommonReadMessage = {
				id: 999,
			}

			store.dispatch('addConversation', testConversation)

			store.dispatch('updateLastCommonReadMessage', {
				token: testToken,
				lastCommonReadMessage: { id: 1024 },
			})

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.lastCommonReadMessage.id).toBe(1024)
		})

		test('updates last activity', () => {
			const mockDate = new Date('2020-01-01')

			jest.spyOn(global, 'Date')
				.mockImplementation(() => mockDate)

			testConversation.lastActivity = 1200300

			store.dispatch('addConversation', testConversation)

			store.dispatch('updateConversationLastActive', testToken)

			jest.useRealTimers()

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.lastActivity).toBe(mockDate.getTime() / 1000)
		})
	})

	describe('update last message', () => {
		beforeEach(() => {
			store = new Vuex.Store(testStoreConfig)
		})

		test('successful update from user', () => {
			const testLastMessage = {
				actorType: 'users',
				actorId: 'admin',
				systemMessage: '',
				id: 42,
				message: 'Message 2',
			}

			testConversation.lastMessage = previousLastMessage

			store.dispatch('addConversation', testConversation)

			store.dispatch('updateConversationLastMessage', {
				token: testToken,
				lastMessage: testLastMessage,
			})

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.lastMessage).toBe(testLastMessage)
		})

		test('ignore update from bot', () => {
			const testLastMessage = {
				actorType: 'bots',
				actorId: 'selfmade',
				systemMessage: '',
				id: 42,
				message: 'Message 2',
			}

			testConversation.lastMessage = previousLastMessage

			store.dispatch('addConversation', testConversation)

			store.dispatch('updateConversationLastMessage', {
				token: testToken,
				lastMessage: testLastMessage,
			})

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.lastMessage).toBe(previousLastMessage)
		})

		test('ignore update from bot but not from changelog', () => {
			const testLastMessage = {
				actorType: 'bots',
				actorId: 'changelog',
				systemMessage: '',
				id: 42,
				message: 'Message 2',
			}

			testConversation.lastMessage = previousLastMessage

			store.dispatch('addConversation', testConversation)

			store.dispatch('updateConversationLastMessage', {
				token: testToken,
				lastMessage: testLastMessage,
			})

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.lastMessage).toBe(testLastMessage)
		})

		test('ignore update reactions', () => {
			const testLastMessage = {
				actorType: 'users',
				actorId: 'admin',
				systemMessage: 'reaction',
				id: 42,
				message: '👍',
			}

			testConversation.lastMessage = previousLastMessage

			store.dispatch('addConversation', testConversation)

			store.dispatch('updateConversationLastMessage', {
				token: testToken,
				lastMessage: testLastMessage,
			})

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.lastMessage).toBe(previousLastMessage)
		})

		test('ignore update from the action of deleting reactions', () => {
			const testLastMessage = {
				actorType: 'users',
				actorId: 'admin',
				systemMessage: 'reaction_revoked',
				id: 42,
				message: 'Admin deleted a reaction',
			}

			testConversation.lastMessage = previousLastMessage

			store.dispatch('addConversation', testConversation)

			store.dispatch('updateConversationLastMessage', {
				token: testToken,
				lastMessage: testLastMessage,
			})

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.lastMessage).toBe(previousLastMessage)
		})

		test('ignore update deleted reactions (only theory as the action of deleting would come after it anyway)', () => {
			const testLastMessage = {
				actorType: 'users',
				actorId: 'admin',
				systemMessage: 'reaction_deleted',
				id: 42,
				message: 'Reaction deleted by author',
			}

			testConversation.lastMessage = previousLastMessage

			store.dispatch('addConversation', testConversation)

			store.dispatch('updateConversationLastMessage', {
				token: testToken,
				lastMessage: testLastMessage,
			})

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.lastMessage).toBe(previousLastMessage)
		})

		test('ignore update from deleting a message', () => {
			const testLastMessage = {
				actorType: 'users',
				actorId: 'admin',
				systemMessage: 'message_deleted',
				id: 42,
				message: 'Admin deleted a message',
			}

			testConversation.lastMessage = previousLastMessage

			store.dispatch('addConversation', testConversation)

			store.dispatch('updateConversationLastMessage', {
				token: testToken,
				lastMessage: testLastMessage,
			})

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.lastMessage).toBe(previousLastMessage)
		})

		test('successfully update temporary messages', () => {
			const testLastMessage = {
				actorType: 'users',
				actorId: 'admin',
				systemMessage: '',
				id: 'temp-42',
				message: 'quit',
			}

			testConversation.lastMessage = previousLastMessage

			store.dispatch('addConversation', testConversation)

			store.dispatch('updateConversationLastMessage', {
				token: testToken,
				lastMessage: testLastMessage,
			})

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.lastMessage).toBe(testLastMessage)
		})

		test('successfully update also posted messages which start with a slash', () => {
			const testLastMessage = {
				actorType: 'users',
				actorId: 'admin',
				systemMessage: '',
				id: 42,
				message: '/quit',
			}

			testConversation.lastMessage = previousLastMessage

			store.dispatch('addConversation', testConversation)

			store.dispatch('updateConversationLastMessage', {
				token: testToken,
				lastMessage: testLastMessage,
			})

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.lastMessage).toBe(testLastMessage)
		})

		test('ignore update from temporary if posting a command', () => {
			const testLastMessage = {
				actorType: 'users',
				actorId: 'admin',
				systemMessage: '',
				id: 'temp-42',
				message: '/quit',
			}

			testConversation.lastMessage = previousLastMessage

			store.dispatch('addConversation', testConversation)

			store.dispatch('updateConversationLastMessage', {
				token: testToken,
				lastMessage: testLastMessage,
			})

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.lastMessage).toBe(previousLastMessage)
		})
	})

	describe('creating conversations', () => {
		test('creates one to one conversation', async () => {
			const newConversation = {
				id: 999,
				token: 'new-token',
				type: CONVERSATION.TYPE.ONE_TO_ONE,
			}

			const response = {
				data: {
					ocs: {
						data: newConversation,
					},
				},
			}

			createOneToOneConversation.mockResolvedValueOnce(response)

			await store.dispatch('createOneToOneConversation', 'target-actor-id')

			expect(createOneToOneConversation).toHaveBeenCalledWith('target-actor-id')

			const addedConversation = store.getters.conversation('new-token')
			expect(addedConversation).toBe(newConversation)
		})
	})

	test('sets default permissions for a conversation', async () => {
		expect(store.getters.selectedParticipants).toStrictEqual([])

		await store.dispatch('setConversationPermissions', { token: testToken, permissions })

		expect(setConversationPermissions).toHaveBeenCalledWith(testToken, permissions)

		expect(store.getters.conversation(testToken).defaultPermissions).toBe(permissions)
	})

	test('sets default permissions for a call', async () => {
		expect(store.getters.selectedParticipants).toStrictEqual([])

		await store.dispatch('setCallPermissions', { token: testToken, permissions })

		expect(setCallPermissions).toHaveBeenCalledWith(testToken, permissions)

		expect(store.getters.conversation(testToken).callPermissions).toBe(permissions)
	})
})
