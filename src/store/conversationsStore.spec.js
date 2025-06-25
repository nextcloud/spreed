import { emit } from '@nextcloud/event-bus'
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { flushPromises } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import { createPinia, setActivePinia } from 'pinia'
import { createStore } from 'vuex'
import {
	ATTENDEE,
	CONVERSATION,
	PARTICIPANT,
	WEBINAR,
} from '../constants.ts'
import BrowserStorage from '../services/BrowserStorage.js'
import {
	addToFavorites,
	changeListable,
	changeLobbyState,
	changeReadOnlyState,
	createConversation,
	deleteConversation,
	fetchConversation,
	fetchConversations,
	makeConversationPrivate,
	makeConversationPublic,
	removeFromFavorites,
	setCallPermissions,
	setConversationDescription,
	setConversationName,
	setConversationPermissions,
	setNotificationLevel,
	setSIPEnabled,
} from '../services/conversationsService.ts'
import { setConversationUnread, updateLastReadMessage } from '../services/messagesService.ts'
import { useActorStore } from '../stores/actor.ts'
import { useTalkHashStore } from '../stores/talkHash.js'
import { generateOCSErrorResponse, generateOCSResponse } from '../test-helpers.js'
import storeConfig from './storeConfig.js'

jest.mock('../services/conversationsService', () => ({
	makeConversationPublic: jest.fn(),
	makeConversationPrivate: jest.fn(),
	addToFavorites: jest.fn(),
	removeFromFavorites: jest.fn(),
	changeLobbyState: jest.fn(),
	changeReadOnlyState: jest.fn(),
	changeListable: jest.fn(),
	createLegacyConversation: jest.fn(),
	createConversation: jest.fn(),
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

jest.mock('../services/messagesService', () => ({
	updateLastReadMessage: jest.fn(),
	setConversationUnread: jest.fn(),
}))

jest.mock('@nextcloud/event-bus')

jest.mock('../services/BrowserStorage.js', () => ({
	getItem: jest.fn().mockReturnValue(null),
	setItem: jest.fn(),
	removeItem: jest.fn(),
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
	let store = null
	let addParticipantOnceAction = null
	let actorStore
	const permissions = PARTICIPANT.PERMISSIONS.MAX_CUSTOM

	beforeEach(() => {
		setActivePinia(createPinia())
		actorStore = useActorStore()

		testConversation = {
			token: testToken,
			participantFlags: PARTICIPANT.CALL_FLAG.DISCONNECTED,
			participantType: PARTICIPANT.TYPE.USER,
			lastPing: 600,
			lastActivity: 1672531200, // 2023-01-01T00:00:00.000Z
			sessionId: 'session-id-1',
			attendeeId: 'attendee-id-1',
			actorType: ATTENDEE.ACTOR_TYPE.USERS,
			actorId: 'actor-id',
			permissions: PARTICIPANT.PERMISSIONS.CUSTOM,
			defaultPermissions: PARTICIPANT.PERMISSIONS.CUSTOM,
			callPermissions: PARTICIPANT.PERMISSIONS.CUSTOM,
			lastMessage: { ...previousLastMessage },
		}

		testStoreConfig = cloneDeep(storeConfig)

		addParticipantOnceAction = jest.fn()
		testStoreConfig.modules.participantsStore.actions.addParticipantOnce = addParticipantOnceAction

		console.debug = jest.fn()
	})

	afterEach(() => {
		jest.clearAllMocks()
	})

	describe('conversation list', () => {
		let talkHashStore
		let purgeMessagesStoreAction
		let checkMaintenanceModeAction
		let clearMaintenanceModeAction
		let updateTalkVersionHashAction

		beforeEach(() => {
			purgeMessagesStoreAction = jest.fn()
			testStoreConfig.modules.messagesStore.actions.purgeMessagesStore = purgeMessagesStoreAction
			talkHashStore = useTalkHashStore()
			checkMaintenanceModeAction = jest.spyOn(talkHashStore, 'checkMaintenanceMode')
			clearMaintenanceModeAction = jest.spyOn(talkHashStore, 'clearMaintenanceMode')
			updateTalkVersionHashAction = jest.spyOn(talkHashStore, 'updateTalkVersionHash')
			store = createStore(testStoreConfig)
		})

		test('adds conversation to the store, with current user as participant', () => {
			actorStore.setCurrentUser({
				uid: 'current-user',
				displayName: 'display-name',
			})
			store.dispatch('addConversation', testConversation)

			expect(store.getters.conversation(testToken)).toStrictEqual(testConversation)
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
					permissions: PARTICIPANT.PERMISSIONS.CUSTOM,
					sessionIds: [
						'session-id-1',
					],
					userId: 'current-user',
					status: '',
				},
			})
		})

		test('adds conversation to the store, with empty user id for guests', () => {
			actorStore.setCurrentParticipant({
				actorId: 'guestActorId',
				sessionId: 'XXSESSIONIDXX',
				participantType: PARTICIPANT.TYPE.GUEST,
			})

			store.dispatch('addConversation', testConversation)

			expect(store.getters.conversation(testToken)).toStrictEqual(testConversation)

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
					permissions: PARTICIPANT.PERMISSIONS.CUSTOM,
					sessionIds: [
						'session-id-1',
					],
					userId: '',
					status: '',
				},
			})
		})

		test('deletes messages with conversation', () => {
			actorStore.setCurrentUser({
				uid: 'current-user',
				displayName: 'display-name',
			})
			store.dispatch('addConversation', testConversation)

			store.dispatch('deleteConversation', testToken)
			expect(purgeMessagesStoreAction).toHaveBeenCalled()

			expect(store.getters.conversation(testToken)).toBeUndefined()

			// not deleted from server...
			expect(deleteConversation).not.toHaveBeenCalled()
		})

		test('restores conversations cached in BrowserStorage', async () => {
			const testConversations = [
				{
					token: 'one_token',
					attendeeId: 'attendee-id-1',
					lastActivity: Date.parse('2023-02-01T00:00:00.000Z') / 1000,
				},
				{
					token: 'another_token',
					attendeeId: 'attendee-id-2',
					lastActivity: Date.parse('2023-01-01T00:00:00.000Z') / 1000,
				},
			]

			BrowserStorage.getItem.mockReturnValueOnce('[{"token":"one_token","attendeeId":"attendee-id-1","lastActivity":1675209600},{"token":"another_token","attendeeId":"attendee-id-2","lastActivity":1672531200}]')

			await store.dispatch('restoreConversations')

			expect(BrowserStorage.getItem).toHaveBeenCalledWith('cachedConversations')
			expect(store.getters.conversationsList).toHaveLength(2)
			expect(store.getters.conversationsList).toEqual(testConversations)
		})

		test('deletes conversation from server', async () => {
			store.dispatch('addConversation', testConversation)

			await store.dispatch('deleteConversationFromServer', { token: testToken })
			expect(deleteConversation).toHaveBeenCalledWith(testToken)
			expect(purgeMessagesStoreAction).toHaveBeenCalled()

			expect(store.getters.conversation(testToken)).toBeUndefined()
		})

		test('fetches a single conversation', async () => {
			const response = generateOCSResponse({ payload: testConversation })
			fetchConversation.mockResolvedValue(response)

			await store.dispatch('fetchConversation', { token: testToken })

			expect(fetchConversation).toHaveBeenCalledWith(testToken)

			const fetchedConversation = store.getters.conversation(testToken)
			expect(fetchedConversation).toStrictEqual(testConversation)

			expect(clearMaintenanceModeAction).toHaveBeenCalled()
			expect(updateTalkVersionHashAction).toHaveBeenCalledWith(response)
		})

		test('fetches all conversations and set initial', async () => {
			const testConversations = [
				{
					token: 'one_token',
					attendeeId: 'attendee-id-1',
					lastActivity: Date.parse('2023-02-01T00:00:00.000Z') / 1000,
				},
				{
					token: 'another_token',
					attendeeId: 'attendee-id-2',
					lastActivity: Date.parse('2023-01-01T00:00:00.000Z') / 1000,
				},
			]

			const response = generateOCSResponse({ payload: testConversations })
			fetchConversations.mockResolvedValue(response)

			await store.dispatch('fetchConversations', {})

			expect(fetchConversations).toHaveBeenCalledWith({ modifiedSince: 0, includeStatus: 1, includeLastMessage: 1 })
			expect(store.getters.conversationsList).toStrictEqual(testConversations)
		})

		test('sets fetched conversations to BrowserStorage', async () => {
			const testConversations = [
				{
					token: 'one_token',
					attendeeId: 'attendee-id-1',
					lastActivity: Date.parse('2023-02-01T00:00:00.000Z') / 1000,
				},
				{
					token: 'another_token',
					attendeeId: 'attendee-id-2',
					lastActivity: Date.parse('2023-01-01T00:00:00.000Z') / 1000,
				},
			]

			const response = generateOCSResponse({ payload: testConversations })
			fetchConversations.mockResolvedValue(response)

			store.dispatch('fetchConversations', { })
			await flushPromises()

			expect(BrowserStorage.setItem).toHaveBeenCalledWith('cachedConversations', JSON.stringify(testConversations))
		})

		test('fetches all conversations and add new received conversations', async () => {
			const oldConversation = {
				token: 'tokenOne',
				attendeeId: 'attendee-id-1',
				lastActivity: Date.parse('2023-01-01T00:00:00.000Z') / 1000,
			}

			// Add initial conversations
			store.dispatch('addConversation', oldConversation)

			// Fetch new conversation
			const newConversation = {
				token: 'tokenTwo',
				attendeeId: 'attendee-id-2',
				lastActivity: Date.parse('2023-02-01T00:00:00.000Z') / 1000,
			}

			const response = generateOCSResponse({ payload: [oldConversation, newConversation] })
			fetchConversations.mockResolvedValue(response)

			await store.dispatch('fetchConversations', { })

			expect(fetchConversations).toHaveBeenCalledWith({ modifiedSince: 0, includeStatus: 1, includeLastMessage: 1 })
			// conversationsList is actual to the response
			expect(store.getters.conversationsList).toEqual([newConversation, oldConversation])
			// Only old conversation with new activity should be actually replaced with new objects
			expect(store.state.conversationsStore.conversations[oldConversation.token]).toStrictEqual(oldConversation)
			expect(store.state.conversationsStore.conversations[newConversation.token]).toStrictEqual(newConversation)
		})

		test('fetches all conversations and emit user status update for new 1-1 conversations', async () => {
			const oldConversations = [
				{
					token: 'one_token',
					attendeeId: 'attendee-id-1',
					lastActivity: Date.parse('2023-02-01T00:00:00.000Z') / 1000,
					type: CONVERSATION.TYPE.ONE_TO_ONE,
					status: 'online',
					statusIcon: '🎉',
					statusMessage: 'I am the test',
					statusClearAt: null,
				},
			]
			store.dispatch('addConversation', oldConversations[0])

			const newConversations = [{
				...oldConversations[0],
			}, {
				token: 'another_token',
				attendeeId: 'attendee-id-2',
				lastActivity: Date.parse('2023-01-01T00:00:00.000Z') / 1000,
				type: CONVERSATION.TYPE.GROUP,
			}, {
				name: 'bob',
				token: 'new_token',
				attendeeId: 'attendee-id-3',
				lastActivity: Date.parse('2023-02-01T00:00:00.000Z') / 1000,
				type: CONVERSATION.TYPE.ONE_TO_ONE,
				status: 'online',
				statusIcon: '😃',
				statusMessage: 'I am the test 2',
				statusClearAt: null,
			}]
			const response = generateOCSResponse({ payload: newConversations })
			fetchConversations.mockResolvedValue(response)
			emit.mockClear()

			store.dispatch('fetchConversations', { })
			await flushPromises()

			// Only new conversation emits event
			expect(emit).toHaveBeenCalledTimes(1)
			expect(emit.mock.calls.at(-1)).toEqual([
				'user_status:status.updated',
				{
					userId: newConversations[2].name,
					status: newConversations[2].status,
					icon: newConversations[2].statusIcon,
					message: newConversations[2].statusMessage,
					clearAt: newConversations[2].statusClearAt,
				},
			])
		})

		test('fetches all conversations and emit user status update for changed statuses of 1-1 conversations', async () => {
			const oldConversations = [
				{
					token: 'first_token',
					name: 'alice',
					attendeeId: 'attendee-id-1',
					lastActivity: Date.parse('2023-02-01T00:00:00.000Z') / 1000,
					type: CONVERSATION.TYPE.ONE_TO_ONE,
					status: 'online',
					statusIcon: '🎉',
					statusMessage: 'I am the test',
					statusClearAt: null,
				},
				{
					token: 'second_token',
					name: 'bob',
					attendeeId: 'attendee-id-2',
					lastActivity: Date.parse('2023-02-01T00:00:00.000Z') / 1000,
					type: CONVERSATION.TYPE.ONE_TO_ONE,
					status: 'away',
					statusIcon: '🙄',
					statusMessage: 'I am the test 2',
					statusClearAt: null,
				},
			]
			store.dispatch('addConversation', oldConversations[0])
			store.dispatch('addConversation', oldConversations[1])

			const newConversations = [{
				// Not changed
				...oldConversations[0],
			}, {
				// Updated status
				...oldConversations[1],
				status: 'online',
				statusIcon: '👀',
				statusMessage: 'I am the test 3',
				statusClearAt: null,
			}, {
				token: 'another_token',
				attendeeId: 'attendee-id-2',
				lastActivity: Date.parse('2023-01-01T00:00:00.000Z') / 1000,
				type: CONVERSATION.TYPE.GROUP,
			}]
			const response = generateOCSResponse({ payload: newConversations })
			fetchConversations.mockResolvedValue(response)
			emit.mockClear()
			await store.dispatch('fetchConversations', { })
			// Only new conversation emits event
			expect(emit).toHaveBeenCalledTimes(1)
			expect(emit.mock.calls.at(-1)).toEqual([
				'user_status:status.updated',
				{
					userId: newConversations[1].name,
					status: newConversations[1].status,
					icon: newConversations[1].statusIcon,
					message: newConversations[1].statusMessage,
					clearAt: newConversations[1].statusClearAt,
				},
			])
		})

		test('fetches all conversations and re-set conversations with new lastActivity', async () => {
			const oldConversations = [
				{
					token: 'one_token',
					attendeeId: 'attendee-id-1',
					lastActivity: Date.parse('2023-02-01T00:00:00.000Z') / 1000,
				},
				{
					token: 'another_token',
					attendeeId: 'attendee-id-2',
					lastActivity: Date.parse('2023-01-01T00:00:00.000Z') / 1000,
				},
			]
			store.dispatch('addConversation', oldConversations[0])
			store.dispatch('addConversation', oldConversations[1])

			const newConversations = [{
				...oldConversations[0],
			}, {
				...oldConversations[1],
				lastActivity: oldConversations[1].lastActivity + 1000,
			}]
			const response = generateOCSResponse({ payload: newConversations })
			fetchConversations.mockResolvedValue(response)
			await store.dispatch('fetchConversations', { })

			// conversationsList is actual to the response
			expect(store.getters.conversationsList).toEqual(newConversations)
			// Only old conversation with new activity should be actually replaced with new objects
			// Not updated
			expect(store.state.conversationsStore.conversations[oldConversations[0].token]).toStrictEqual(newConversations[0])
			// Updated because of new lastActivity
			expect(store.state.conversationsStore.conversations[oldConversations[1].token]).toStrictEqual(newConversations[1])
		})

		test('fetches all conversations and re-set conversations when it has any property changed', async () => {
			const oldConversations = [
				{
					token: 'one_token',
					attendeeId: 'attendee-id-1',
					unreadMention: false,
					lastActivity: Date.parse('2023-02-01T00:00:00.000Z') / 1000,
				},
				{
					token: 'another_token',
					attendeeId: 'attendee-id-2',
					unreadMention: false,
					lastActivity: Date.parse('2023-01-01T00:00:00.000Z') / 1000,
				},
			]
			store.dispatch('addConversation', oldConversations[0])
			store.dispatch('addConversation', oldConversations[1])

			const newConversations = [{
				...oldConversations[0],
			}, {
				...oldConversations[1],
				unreadMention: true,
			}]
			const response = generateOCSResponse({ payload: newConversations })
			fetchConversations.mockResolvedValue(response)
			await store.dispatch('fetchConversations', { })

			// conversationsList is actual to the response
			expect(store.getters.conversationsList).toEqual(newConversations)
			// Only old conversation with new activity should be actually replaced with new objects
			// Not updated
			expect(store.state.conversationsStore.conversations[oldConversations[0].token]).toStrictEqual(newConversations[0])
			// Updated because unreadMention change
			expect(store.state.conversationsStore.conversations[oldConversations[1].token]).toStrictEqual(newConversations[1])
		})

		test('fetches all conversations and remove deleted conversations if without modifiedSince', async () => {
			const testConversations = [
				{
					token: 'one_token',
					attendeeId: 'attendee-id-1',
					lastActivity: 1675209600, // 2023-02-01T00:00:00.000Z
				},
				{
					token: 'another_token',
					attendeeId: 'attendee-id-2',
					lastActivity: 1675209600, // 2023-02-01T00:00:00.000Z
				},
			]

			// add conversation that should be removed
			store.dispatch('addConversation', testConversation)

			const response = generateOCSResponse({ payload: testConversations })

			fetchConversations.mockResolvedValue(response)

			store.dispatch('fetchConversations', { })
			await flushPromises()

			expect(fetchConversations).toHaveBeenCalledWith({ modifiedSince: 0, includeStatus: 1, includeLastMessage: 1 })
			expect(store.getters.conversationsList).toStrictEqual(testConversations)
		})

		test('fetches all conversations without purging not revieved conversations when modifiedSince is present', async () => {
			const oldConversation1 = {
				token: 'tokenOne',
				attendeeId: 'attendee-id-1',
				lastActivity: 1672531200, // 2023-01-01T00:00:00.000Z
			}
			const oldConversation2 = {
				token: 'tokenTwo',
				attendeeId: 'attendee-id-2',
				lastActivity: 1672531200, // 2023-01-01T00:00:00.000Z
			}

			// Add initial conversations
			store.dispatch('addConversation', oldConversation1)
			store.dispatch('addConversation', oldConversation2)

			// Fetch new conversation
			// The same lastActivity, as oldConversation
			const newConversation1 = {
				token: 'tokenOne',
				attendeeId: 'attendee-id-1',
				lastActivity: 1672531200, // 2023-01-01T00:00:00.000Z
			}
			// Has new activity
			const newConversation2 = {
				token: 'tokenTwo',
				attendeeId: 'attendee-id-2',
				lastActivity: 1675209600, // 2023-02-01T00:00:00.000Z
			}
			const modifiedSince = 1675209600 // 2023-02-01T00:00:00.000Z

			const response = generateOCSResponse({ payload: [newConversation1, newConversation2] })

			fetchConversations.mockResolvedValue(response)

			await store.dispatch('fetchConversations', { modifiedSince })

			expect(fetchConversations).toHaveBeenCalledWith({ modifiedSince, includeStatus: 1, includeLastMessage: 1 })
			// conversations are actual to the response
			expect(store.state.conversationsStore.conversations).toEqual({
				[newConversation1.token]: newConversation1,
				[newConversation2.token]: newConversation2,
			})
			// Only old conversation with new activity should be actually replaced with new objects
			expect(store.state.conversationsStore.conversations[oldConversation1.token]).toStrictEqual(oldConversation1)
			expect(store.state.conversationsStore.conversations[oldConversation2.token]).toStrictEqual(newConversation2)
		})

		test('fetch conversation failure checks for maintenance mode', async () => {
			const error = generateOCSErrorResponse({ payload: [], status: 503 })
			fetchConversation.mockRejectedValue(error)

			await expect(store.dispatch('fetchConversation', { token: testToken })).rejects.toMatchObject(error)

			expect(checkMaintenanceModeAction).toHaveBeenCalledWith(error.response)
		})

		test('fetch conversations failure checks for maintenance mode', async () => {
			const error = generateOCSErrorResponse({ payload: [], status: 503 })
			fetchConversations.mockRejectedValue(error)

			await expect(store.dispatch('fetchConversations', {})).rejects.toMatchObject(error)

			expect(checkMaintenanceModeAction).toHaveBeenCalledWith(error.response)
		})

		test('fetch conversations should update talkVersion', async () => {
			const response = generateOCSResponse({ payload: [] })
			fetchConversations.mockResolvedValue(response)
			await store.dispatch('fetchConversations', {})
			expect(updateTalkVersionHashAction).toHaveBeenCalledWith(response)
		})
	})

	describe('conversation settings', () => {
		beforeEach(() => {
			store = createStore(testStoreConfig)
		})

		test('make public', async () => {
			testConversation.type = CONVERSATION.TYPE.GROUP

			store.dispatch('addConversation', testConversation)

			makeConversationPublic.mockResolvedValue()

			await store.dispatch('toggleGuests', {
				token: testToken,
				allowGuests: true,
			})

			expect(makeConversationPublic).toHaveBeenCalledWith(testToken, undefined)

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.type).toEqual(CONVERSATION.TYPE.PUBLIC)
		})

		test('make non-public', async () => {
			testConversation.type = CONVERSATION.TYPE.PUBLIC

			store.dispatch('addConversation', testConversation)

			makeConversationPrivate.mockResolvedValue()

			await store.dispatch('toggleGuests', {
				token: testToken,
				allowGuests: false,
			})

			expect(makeConversationPrivate).toHaveBeenCalledWith(testToken)

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
			testConversation.sipEnabled = WEBINAR.SIP.DISABLED

			store.dispatch('addConversation', testConversation)

			setSIPEnabled.mockResolvedValue()

			await store.dispatch('setSIPEnabled', {
				token: testToken,
				state: WEBINAR.SIP.ENABLED,
			})

			expect(setSIPEnabled).toHaveBeenCalledWith(testToken, WEBINAR.SIP.ENABLED)

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.sipEnabled).toBe(WEBINAR.SIP.ENABLED)
		})

		test('set SIP enabled no individual PIN', async () => {
			testConversation.sipEnabled = WEBINAR.SIP.ENABLED

			store.dispatch('addConversation', testConversation)

			setSIPEnabled.mockResolvedValue()

			await store.dispatch('setSIPEnabled', {
				token: testToken,
				state: WEBINAR.SIP.ENABLED_NO_PIN,
			})

			expect(setSIPEnabled).toHaveBeenCalledWith(testToken, WEBINAR.SIP.ENABLED_NO_PIN)

			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.sipEnabled).toBe(WEBINAR.SIP.ENABLED_NO_PIN)
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
			store = createStore(testStoreConfig)
			actorStore.userId = 'current-user'
		})

		test('marks conversation as read by clearing unread counters', async () => {
			// Arrange
			testConversation.unreadMessages = 1024
			testConversation.unreadMention = true
			store.dispatch('addConversation', testConversation)

			const response = generateOCSResponse({
				payload: {
					...testConversation,
					unreadMessages: 0,
					unreadMention: false,
				},
			})
			updateLastReadMessage.mockResolvedValue(response)

			// Act
			store.dispatch('clearLastReadMessage', { token: testToken })
			await flushPromises()

			// Assert
			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.unreadMessages).toBe(0)
			expect(changedConversation.unreadMention).toBe(false)
		})

		test('marks conversation as unread', async () => {
			// Arrange
			testConversation.unreadMessages = 0
			store.dispatch('addConversation', testConversation)

			const response = generateOCSResponse({ payload: { ...testConversation, unreadMessages: 1 } })
			setConversationUnread.mockResolvedValue(response)

			// Act
			store.dispatch('markConversationUnread', { token: testToken })
			await flushPromises()

			// Assert
			expect(setConversationUnread).toHaveBeenCalledWith(testConversation.token)
			const changedConversation = store.getters.conversation(testToken)
			expect(changedConversation.unreadMessages).toBe(1)
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
			jest.useFakeTimers().setSystemTime(mockDate)

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
			store = createStore(testStoreConfig)
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
			expect(changedConversation.lastMessage).toStrictEqual(testLastMessage)
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
			expect(changedConversation.lastMessage).toStrictEqual(previousLastMessage)
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
			expect(changedConversation.lastMessage).toStrictEqual(testLastMessage)
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
			expect(changedConversation.lastMessage).toStrictEqual(previousLastMessage)
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
			expect(changedConversation.lastMessage).toStrictEqual(previousLastMessage)
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
			expect(changedConversation.lastMessage).toStrictEqual(previousLastMessage)
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
			expect(changedConversation.lastMessage).toStrictEqual(previousLastMessage)
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
			expect(changedConversation.lastMessage).toStrictEqual(testLastMessage)
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
			expect(changedConversation.lastMessage).toStrictEqual(testLastMessage)
		})
	})

	describe('creating conversations', () => {
		test('creates one to one conversation', async () => {
			const newConversation = {
				id: 999,
				token: 'new-token',
				type: CONVERSATION.TYPE.ONE_TO_ONE,
			}

			const response = generateOCSResponse({ payload: newConversation })
			createConversation.mockResolvedValueOnce(response)

			await store.dispatch('createOneToOneConversation', 'target-actor-id')

			expect(createConversation).toHaveBeenCalledWith({
				roomType: CONVERSATION.TYPE.ONE_TO_ONE,
				participants: { users: ['target-actor-id'] },
			})

			const addedConversation = store.getters.conversation('new-token')
			expect(addedConversation).toStrictEqual(newConversation)
		})
	})

	test('sets default permissions for a conversation', async () => {
		await store.dispatch('setConversationPermissions', { token: testToken, permissions })

		expect(setConversationPermissions).toHaveBeenCalledWith(testToken, permissions)

		expect(store.getters.conversation(testToken).defaultPermissions).toBe(permissions)
	})

	test('sets default permissions for a call', async () => {
		await store.dispatch('setCallPermissions', { token: testToken, permissions })

		expect(setCallPermissions).toHaveBeenCalledWith(testToken, permissions)

		expect(store.getters.conversation(testToken).callPermissions).toBe(permissions)
	})
})
