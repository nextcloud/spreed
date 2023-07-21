import { createLocalVue, mount } from '@vue/test-utils'
import flushPromises from 'flush-promises' // TODO fix after migration to @vue/test-utils v2.0.0
import { cloneDeep } from 'lodash'
import VueRouter from 'vue-router'
import Vuex from 'vuex'

import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'

import LeftSidebar from './LeftSidebar.vue'

import router from '../../__mocks__/router.js'
import { searchPossibleConversations, searchListedConversations } from '../../services/conversationsService.js'
import { EventBus } from '../../services/EventBus.js'
import storeConfig from '../../store/storeConfig.js'
import { findNcListItems, findNcActionButton } from '../../test-helpers.js'

jest.mock('@nextcloud/initial-state', () => ({
	loadState: jest.fn(),
}))
jest.mock('../../services/conversationsService', () => ({
	searchPossibleConversations: jest.fn(),
	searchListedConversations: jest.fn(),
}))

// short-circuit debounce
jest.mock('debounce', () => jest.fn().mockImplementation(fn => fn))

describe('LeftSidebar.vue', () => {
	let store
	let localVue
	let testStoreConfig
	let loadStateSettings
	let conversationsListMock
	let fetchConversationsAction
	let addConversationAction
	let createOneToOneConversationAction

	const SEARCH_TERM = 'search'

	const mountComponent = () => {
		return mount(LeftSidebar, {
			localVue,
			router,
			store,
			stubs: {
				// to prevent user status fetching
				NcAvatar: true,
				// to prevent complex dialog logic
				NcActions: true,
				NcModal: true,
			},
		})
	}

	beforeEach(() => {
		jest.useFakeTimers()

		localVue = createLocalVue()
		localVue.use(Vuex)
		localVue.use(VueRouter)

		loadStateSettings = {
			circles_enabled: true,
			start_conversations: true,
		}

		loadState.mockImplementation((app, key) => {
			if (app === 'spreed') {
				return loadStateSettings[key]
			}
			return null
		})

		testStoreConfig = cloneDeep(storeConfig)

		// note: need a copy because the Vue modifies it when sorting
		conversationsListMock = jest.fn()
		fetchConversationsAction = jest.fn().mockReturnValue({ headers: {} })
		addConversationAction = jest.fn()
		createOneToOneConversationAction = jest.fn()
		const getUserIdMock = jest.fn().mockReturnValue('current-user')
		testStoreConfig.modules.actorStore.getters.getUserId = () => getUserIdMock
		testStoreConfig.modules.conversationsStore.getters.conversationsList = conversationsListMock
		testStoreConfig.modules.conversationsStore.actions.fetchConversations = fetchConversationsAction
		testStoreConfig.modules.conversationsStore.actions.addConversation = addConversationAction
		testStoreConfig.modules.conversationsStore.actions.createOneToOneConversation = createOneToOneConversationAction

		store = new Vuex.Store(testStoreConfig)
	})

	afterEach(() => {
		jest.clearAllMocks()
	})

	describe('conversation list', () => {
		let conversationsList

		beforeEach(() => {
			conversationsList = [{
				id: 100,
				token: 't100',
				lastActivity: 100,
				isFavorite: false,
				name: 'one',
				displayName: 'one',
			}, {
				id: 200,
				token: 't200',
				lastActivity: 80,
				isFavorite: false,
				name: 'two',
				displayName: 'two',
			}, {
				id: 300,
				token: 't300',
				lastActivity: 120,
				isFavorite: true,
				name: 'three',
				displayName: 'three',
			}]

			// note: need a copy because the Vue modifies it when sorting
			conversationsListMock.mockImplementation(() => cloneDeep(conversationsList))
		})

		test('fetches and renders conversation list initially', async () => {
			const conversationsReceivedEvent = jest.fn()
			EventBus.$once('conversations-received', conversationsReceivedEvent)
			fetchConversationsAction.mockResolvedValueOnce()

			const wrapper = mountComponent()

			expect(fetchConversationsAction).toHaveBeenCalledWith(expect.anything(), expect.anything())
			expect(conversationsListMock).toHaveBeenCalled()

			const conversationListItems = wrapper.findAllComponents({ name: 'Conversation' })
			expect(conversationListItems).toHaveLength(conversationsList.length)

			expect(wrapper.vm.searchText).toBe('')
			expect(wrapper.vm.initialisedConversations).toBeFalsy()

			expect(conversationsReceivedEvent).not.toHaveBeenCalled()

			// move on past the fetchConversation call
			await flushPromises()

			expect(wrapper.vm.initialisedConversations).toBeTruthy()
			expect(conversationListItems.at(0).props('item')).toStrictEqual(conversationsList[2])
			expect(conversationListItems.at(1).props('item')).toStrictEqual(conversationsList[0])
			expect(conversationListItems.at(2).props('item')).toStrictEqual(conversationsList[1])

			expect(conversationsReceivedEvent).toHaveBeenCalledWith({
				singleConversation: false,
			})
		})

		test('re-fetches conversations every 30 seconds', async () => {
			const wrapper = mountComponent()
			expect(wrapper.exists()).toBeTruthy()
			expect(fetchConversationsAction).toHaveBeenCalled()

			fetchConversationsAction.mockClear()

			// move past async call
			await flushPromises()
			expect(fetchConversationsAction).not.toHaveBeenCalled()

			jest.advanceTimersByTime(15000)
			expect(fetchConversationsAction).not.toHaveBeenCalled()

			jest.advanceTimersByTime(20000)
			expect(fetchConversationsAction).toHaveBeenCalled()
		})

		test('re-fetches conversations when receiving bus event', async () => {
			const wrapper = mountComponent()
			expect(wrapper.exists()).toBeTruthy()
			expect(fetchConversationsAction).toHaveBeenCalled()

			fetchConversationsAction.mockClear()

			// move past async call
			await flushPromises()
			expect(fetchConversationsAction).not.toHaveBeenCalled()

			EventBus.$emit('should-refresh-conversations', {})

			// note: debounce was short-circuited so no delay needed
			expect(fetchConversationsAction).toHaveBeenCalled()
		})
	})

	describe('search results', () => {
		let listedResults
		let usersResults
		let groupsResults
		let circlesResults
		let conversationsList

		beforeEach(() => {
			conversationsList = [{
				id: 100,
				token: 't100',
				lastActivity: 100,
				isFavorite: false,
				name: 'one',
				displayName: 'the searched one by display name',
				lastMessage: {},
			}, {
				id: 200,
				token: 't200',
				lastActivity: 80,
				isFavorite: false,
				name: 'searched by name',
				displayName: 'another one',
				lastMessage: {},
			}, {
				id: 300,
				token: 't300',
				lastActivity: 120,
				isFavorite: true,
				name: 'excluded',
				displayName: 'excluded from results',
				lastMessage: {},
			}]

			listedResults = [{
				id: 1000,
				name: 'listed one searched',
				displayName: 'listed one searched',
				lastMessage: {},
				token: 'listed-token-1',
			}, {
				id: 1001,
				name: 'listed two searched',
				displayName: 'listed two searched',
				lastMessage: {},
				token: 'listed-token-2',
			}]
			usersResults = [{
				id: 'current-user',
				label: 'Current User searched',
				source: 'users',
			}, {
				id: 'one-user',
				label: 'One user searched',
				source: 'users',
			}, {
				id: 'two-user',
				label: 'Two user searched',
				source: 'users',
			}]
			groupsResults = [{
				id: 'one-group',
				label: 'One group searched',
				source: 'groups',
			}, {
				id: 'two-group',
				label: 'Two group searched',
				source: 'groups',
			}]
			circlesResults = [{
				id: 'one-circle',
				label: 'One circle searched',
				source: 'circles',
			}, {
				id: 'two-circle',
				label: 'Two circle searched',
				source: 'circles',
			}]

			// note: need a copy because the Vue modifies it when sorting
			conversationsListMock.mockImplementation(() => cloneDeep(conversationsList))
			fetchConversationsAction.mockResolvedValue()
		})

		/**
		 * @param {string} searchTerm The search term to filter by
		 * @param {Array} possibleResults Result options returned by the APIs
		 * @param {Array} listedResults The displayed results
		 * @param {object} loadStateSettingsOverride Allows to override some properties
		 */
		async function testSearch(searchTerm, possibleResults, listedResults, loadStateSettingsOverride) {
			searchPossibleConversations.mockResolvedValue({
				data: {
					ocs: {
						data: possibleResults,
					},
				},
			})
			searchListedConversations.mockResolvedValue({
				data: {
					ocs: {
						data: listedResults,
					},
				},
			})

			if (loadStateSettingsOverride) {
				loadStateSettings = loadStateSettingsOverride
			}

			const wrapper = mountComponent()

			expect(fetchConversationsAction).toHaveBeenCalledWith(expect.anything(), expect.anything())
			expect(conversationsListMock).toHaveBeenCalled()

			const searchBox = wrapper.findComponent({ name: 'SearchBox' })
			expect(searchBox.exists()).toBeTruthy()

			// move past async call
			await flushPromises()

			await searchBox.find('input[type="text"]').setValue(searchTerm)

			await flushPromises()

			return wrapper
		}

		describe('displaying search results', () => {
			test('displays search results when search is active', async () => {
				const wrapper = await testSearch(
					SEARCH_TERM,
					[...usersResults, ...groupsResults, ...circlesResults],
					listedResults,
					{
						circles_enabled: true,
						start_conversations: true,
					},
				)

				// Check all captions
				const captionList = ['Conversations', 'Open conversations', 'Users', 'Groups', 'Circles']
				const captionListItems = wrapper.findAllComponents({ name: 'NcAppNavigationCaption' })
				expect(captionListItems.exists()).toBeTruthy()
				expect(captionListItems).toHaveLength(captionList.length)
				captionList.forEach((caption, index) => {
					expect(captionListItems.at(index).props('title')).toStrictEqual(caption)
				})

				// Check all conversations
				const conversationList = [...conversationsList, ...listedResults]
					.filter(item => item.name.includes(SEARCH_TERM) || item.displayName.includes(SEARCH_TERM))
				const conversationListItems = wrapper.findAllComponents({ name: 'Conversation' })
				expect(conversationListItems.exists()).toBeTruthy()
				expect(conversationListItems).toHaveLength(conversationList.length)
				conversationList.forEach((conversation, index) => {
					expect(conversationListItems.at(index).props('item')).toStrictEqual(conversation)
				})

				// Check all other results
				const resultsList = [...usersResults, ...groupsResults, ...circlesResults]
					.filter(item => item.id !== 'current-user').map(item => item.label)
				const resultsListItems = findNcListItems(wrapper, resultsList)
				expect(resultsListItems.exists()).toBeTruthy()
				expect(resultsListItems).toHaveLength(resultsList.length)
				resultsList.forEach((result, index) => {
					expect(resultsListItems.at(index).props('title')).toStrictEqual(result)
				})
			})

			test('only shows user search results when cannot create conversations', async () => {
				const wrapper = await testSearch(
					SEARCH_TERM,
					[...usersResults, ...groupsResults, ...circlesResults],
					listedResults,
					{
						circles_enabled: true,
						start_conversations: false,
					},
				)

				// Check all captions
				const captionList = ['Conversations', 'Open conversations', 'Users']
				const captionListItems = wrapper.findAllComponents({ name: 'NcAppNavigationCaption' })
				expect(captionListItems.exists()).toBeTruthy()
				expect(captionListItems).toHaveLength(captionList.length)
				captionList.forEach((caption, index) => {
					expect(captionListItems.at(index).props('title')).toStrictEqual(caption)
				})

				// Check all conversations
				const conversationList = [...conversationsList, ...listedResults]
					.filter(item => item.name.includes(SEARCH_TERM) || item.displayName.includes(SEARCH_TERM))
				const conversationListItems = wrapper.findAllComponents({ name: 'Conversation' })
				expect(conversationListItems.exists()).toBeTruthy()
				expect(conversationListItems).toHaveLength(conversationList.length)
				conversationList.forEach((conversation, index) => {
					expect(conversationListItems.at(index).props('item')).toStrictEqual(conversation)
				})

				// Check all other results
				const resultsList = [...usersResults]
					.filter(item => item.id !== 'current-user').map(item => item.label)
				const resultsListItems = findNcListItems(wrapper, resultsList)
				expect(resultsListItems.exists()).toBeTruthy()
				expect(resultsListItems).toHaveLength(resultsList.length)
				resultsList.forEach((result, index) => {
					expect(resultsListItems.at(index).props('title')).toStrictEqual(result)
				})
			})

			test('does not show circles results when circles are disabled', async () => {
				const wrapper = await testSearch(
					SEARCH_TERM,
					[...usersResults, ...groupsResults],
					listedResults,
					{
						circles_enabled: false,
						start_conversations: true,
					},
				)

				// Check all captions
				const captionList = ['Conversations', 'Open conversations', 'Users', 'Groups']
				const captionListItems = wrapper.findAllComponents({ name: 'NcAppNavigationCaption' })
				expect(captionListItems.exists()).toBeTruthy()
				expect(captionListItems).toHaveLength(captionList.length)
				captionList.forEach((caption, index) => {
					expect(captionListItems.at(index).props('title')).toStrictEqual(caption)
				})

				// Check all conversations
				const conversationList = [...conversationsList, ...listedResults]
					.filter(item => item.name.includes(SEARCH_TERM) || item.displayName.includes(SEARCH_TERM))
				const conversationListItems = wrapper.findAllComponents({ name: 'Conversation' })
				expect(conversationListItems.exists()).toBeTruthy()
				expect(conversationListItems).toHaveLength(conversationList.length)
				conversationList.forEach((conversation, index) => {
					expect(conversationListItems.at(index).props('item')).toStrictEqual(conversation)
				})

				// Check all other results
				const resultsList = [...usersResults, ...groupsResults]
					.filter(item => item.id !== 'current-user').map(item => item.label)
				const resultsListItems = findNcListItems(wrapper, resultsList)
				expect(resultsListItems.exists()).toBeTruthy()
				expect(resultsListItems).toHaveLength(resultsList.length)
				resultsList.forEach((result, index) => {
					expect(resultsListItems.at(index).props('title')).toStrictEqual(result)
				})
			})
		})

		describe('not found caption', () => {
			/**
			 * @param {string} searchTerm The search term to filter by
			 * @param {Array} possibleResults Result options returned by the APIs
			 * @param {Array} listedResults The displayed results
			 * @param {object} loadStateSettingsOverride Allows to override some properties
			 * @param {string} expectedCaption The caption of the "No results found" section
			 */
			async function testSearchNotFound(searchTerm, possibleResults, listedResults, loadStateSettingsOverride, expectedCaption) {
				const wrapper = await testSearch(searchTerm, possibleResults, listedResults, loadStateSettingsOverride)

				const conversationListItems = wrapper.findAllComponents({ name: 'Conversation' })
				expect(conversationListItems.exists()).toBeTruthy()
				expect(conversationListItems).toHaveLength(2 + listedResults.length)
				// only filters the existing conversations in the list
				expect(conversationListItems.at(0).props('item')).toStrictEqual(conversationsList[0])
				expect(conversationListItems.at(1).props('item')).toStrictEqual(conversationsList[1])

				const captionsEls = wrapper.findAllComponents({ name: 'NcAppNavigationCaption' })
				expect(captionsEls.exists()).toBeTruthy()
				if (listedResults.length > 0) {
					expect(captionsEls.length).toBeGreaterThan(2)
					expect(captionsEls.at(0).props('title')).toBe('Conversations')
					expect(captionsEls.at(1).props('title')).toBe('Open conversations')
				} else {
					expect(captionsEls.length).toBeGreaterThan(1)
					expect(captionsEls.at(0).props('title')).toBe('Conversations')
				}
				// last dynamic caption for "No search results"
				expect(captionsEls.at(-1).props('title')).toBe(expectedCaption)

				return wrapper
			}

			test('displays all types in caption when nothing was found', async () => {
				await testSearchNotFound(
					SEARCH_TERM,
					[],
					[],
					{
						circles_enabled: true,
						start_conversations: true,
					},
					'Users, groups and circles',
				)
			})
			test('displays all types in caption when only listed conversations were found', async () => {
				await testSearchNotFound(
					SEARCH_TERM,
					[],
					listedResults,
					{
						circles_enabled: true,
						start_conversations: true,
					},
					'Users, groups and circles',
				)
			})
			test('displays all types minus circles when nothing was found but circles is disabled', async () => {
				await testSearchNotFound(
					SEARCH_TERM,
					[],
					[],
					{
						circles_enabled: false,
						start_conversations: true,
					},
					'Users and groups',
				)
			})
			test('displays caption for users and groups not found', async () => {
				await testSearchNotFound(
					SEARCH_TERM,
					[...circlesResults],
					[],
					{
						circles_enabled: true,
						start_conversations: true,
					},
					'Users and groups',
				)
			})
			test('displays caption for users not found', async () => {
				await testSearchNotFound(
					SEARCH_TERM,
					[...circlesResults, ...groupsResults],
					[],
					{
						circles_enabled: true,
						start_conversations: true,
					},
					'Users',
				)
			})
			test('displays caption for groups not found', async () => {
				await testSearchNotFound(
					SEARCH_TERM,
					[...usersResults, ...circlesResults],
					[],
					{
						circles_enabled: true,
						start_conversations: true,
					},
					'Groups',
				)
			})
			test('displays caption for groups and circles not found', async () => {
				await testSearchNotFound(
					SEARCH_TERM,
					[...usersResults],
					[],
					{
						circles_enabled: true,
						start_conversations: true,
					},
					'Groups and circles',
				)
			})
			test('displays caption for users and circles not found', async () => {
				await testSearchNotFound(
					SEARCH_TERM,
					[...groupsResults],
					[],
					{
						circles_enabled: true,
						start_conversations: true,
					},
					'Users and circles',
				)
			})
		})

		describe('clicking search results', () => {
			test('joins listed conversation from search result', async () => {
				const wrapper = await testSearch(SEARCH_TERM, [], listedResults)
				// Check all conversations
				const conversationList = [...conversationsList, ...listedResults]
					.filter(item => item.name.includes(SEARCH_TERM) || item.displayName.includes(SEARCH_TERM))
				const conversationListItems = wrapper.findAllComponents({ name: 'Conversation' })
				expect(conversationListItems.exists()).toBeTruthy()
				expect(conversationListItems).toHaveLength(conversationList.length)

				await conversationListItems.at(3).find('a').trigger('click')
				expect(addConversationAction).toHaveBeenCalledWith(expect.anything(), conversationList[3])
				expect(wrapper.vm.$route.name).toBe('conversation')
				expect(wrapper.vm.$route.params).toStrictEqual({ token: conversationList[3].token })
			})

			test('creates one to one conversation from user search result', async () => {
				createOneToOneConversationAction.mockResolvedValue({
					id: 9999,
					token: 'new-conversation',
				})

				const wrapper = await testSearch(SEARCH_TERM, [...usersResults], [])
				const resultsList = usersResults.filter(item => item.id !== 'current-user')
				const resultsListItems = findNcListItems(wrapper, resultsList.map(item => item.label))
				expect(resultsListItems.exists()).toBeTruthy()
				expect(resultsListItems).toHaveLength(resultsList.length)

				await resultsListItems.at(1).findAll('a').trigger('click')
				expect(createOneToOneConversationAction).toHaveBeenCalledWith(expect.anything(), resultsList[1].id)
				expect(wrapper.vm.$route.name).toBe('conversation')
				expect(wrapper.vm.$route.params).toStrictEqual({ token: 'new-conversation' })
			})

			test('shows group conversation dialog when clicking search result', async () => {
				const wrapper = await testSearch(SEARCH_TERM, [...groupsResults], [])

				const resultsListItems = findNcListItems(wrapper, groupsResults.map(item => item.label))
				expect(resultsListItems.exists()).toBeTruthy()
				expect(resultsListItems).toHaveLength(groupsResults.length)

				await resultsListItems.at(1).findAll('a').trigger('click')
				// Wait for the component to render
				await wrapper.vm.$nextTick()
				const ncModalComponent = wrapper.findComponent({ name: 'NcModal' })
				expect(ncModalComponent.exists()).toBeTruthy()

				const input = ncModalComponent.findComponent({ name: 'NcTextField', ref: 'conversationName' })
				expect(input.props('value')).toBe(groupsResults[1].label)

				// nothing created yet
				expect(createOneToOneConversationAction).not.toHaveBeenCalled()
				expect(addConversationAction).not.toHaveBeenCalled()
			})

			test('shows circles conversation dialog when clicking search result', async () => {

				const wrapper = await testSearch(SEARCH_TERM, [...circlesResults], [])

				const resultsListItems = findNcListItems(wrapper, circlesResults.map(item => item.label))
				expect(resultsListItems.exists()).toBeTruthy()
				expect(resultsListItems).toHaveLength(circlesResults.length)

				await resultsListItems.at(1).findAll('a').trigger('click')

				// Wait for the component to render
				await wrapper.vm.$nextTick()
				const ncModalComponent = wrapper.findComponent({ name: 'NcModal' })
				expect(ncModalComponent.exists()).toBeTruthy()
				const input = ncModalComponent.findComponent({ name: 'NcTextField', ref: 'conversationName' })
				expect(input.props('value')).toBe(circlesResults[1].label)

				// nothing created yet
				expect(createOneToOneConversationAction).not.toHaveBeenCalled()
			})

			test('clears search results when joining user chat', async () => {
				createOneToOneConversationAction.mockResolvedValue({
					id: 9999,
					token: 'new-conversation',
				})

				const wrapper = await testSearch(SEARCH_TERM, [...usersResults], [])

				const searchBoxEl = wrapper.findComponent({ name: 'SearchBox' })
				const input = searchBoxEl.find('input[type="text"]')
				expect(input.element.value).toBe(SEARCH_TERM)

				const resultsList = usersResults.filter(item => item.id !== 'current-user')
				const resultsListItems = findNcListItems(wrapper, resultsList.map(item => item.label))
				expect(resultsListItems.exists()).toBeTruthy()
				expect(resultsListItems).toHaveLength(resultsList.length)

				await resultsListItems.at(0).findAll('a').trigger('click')
				// FIXME Real router and store should work at this place to execute following:
				//  click => route-change => participantsStore.joinConversation() => joined-conversation
				EventBus.$emit('joined-conversation', { token: 'new-conversation' })
				await flushPromises()

				expect(searchBoxEl.exists()).toBeTruthy()
				expect(input.element.value).toBe('')
			})

			test('does not clear search results when clicking group chat', async () => {
				const wrapper = await testSearch(SEARCH_TERM, [...groupsResults], [])

				const searchBoxEl = wrapper.findComponent({ name: 'SearchBox' })
				const input = searchBoxEl.find('input[type="text"]')
				expect(input.element.value).toBe(SEARCH_TERM)

				const resultsListItems = findNcListItems(wrapper, groupsResults.map(item => item.label))
				expect(resultsListItems.exists()).toBeTruthy()
				expect(resultsListItems).toHaveLength(groupsResults.length)

				await resultsListItems.at(1).find('a').trigger('click')
				await flushPromises()

				expect(searchBoxEl.exists()).toBeTruthy()
				expect(input.element.value).toBe(SEARCH_TERM)
			})
		})
	})

	describe('new conversation button', () => {
		beforeEach(() => {
			conversationsListMock.mockReturnValue([])
			fetchConversationsAction.mockResolvedValueOnce()
		})
		test('shows new conversation button if user can start conversations', () => {
			loadStateSettings.start_conversations = true

			const wrapper = mountComponent()
			const newConversationbutton = findNcActionButton(wrapper, 'Create a new conversation')
			expect(newConversationbutton.exists()).toBeTruthy()

		})
		test('does not show new conversation button if user cannot start conversations', () => {
			loadStateSettings.start_conversations = false

			const wrapper = mountComponent()
			const newConversationbutton = findNcActionButton(wrapper, 'Create a new conversation')
			expect(newConversationbutton.exists()).toBeFalsy()
		})
	})

	test('shows settings when clicking the settings button', async () => {
		conversationsListMock.mockImplementation(() => [])
		const eventHandler = jest.fn()
		subscribe('show-settings', eventHandler)
		const wrapper = mountComponent()

		const button = wrapper.find('.settings-button')
		expect(button.exists()).toBeTruthy()

		await button.trigger('click')

		unsubscribe('show-settings', eventHandler)

		expect(eventHandler).toHaveBeenCalled()
	})
})
