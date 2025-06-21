/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { createLocalVue, flushPromises, mount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import { createPinia, setActivePinia } from 'pinia'
import VueRouter from 'vue-router'
import Vuex from 'vuex'
import LeftSidebar from './LeftSidebar.vue'
import router from '../../__mocks__/router.js'
import { searchListedConversations } from '../../services/conversationsService.ts'
import { autocompleteQuery } from '../../services/coreService.ts'
import { EventBus } from '../../services/EventBus.ts'
import storeConfig from '../../store/storeConfig.js'
import { useActorStore } from '../../stores/actor.ts'
import { findNcActionButton, findNcButton } from '../../test-helpers.js'
import { requestTabLeadership } from '../../utils/requestTabLeadership.js'

jest.mock('../../services/conversationsService', () => ({
	searchListedConversations: jest.fn(),
}))
jest.mock('../../services/coreService', () => ({
	autocompleteQuery: jest.fn(),
}))

// Test actions with 'can-create' config
let mockCanCreateConversations = true
jest.mock('../../services/CapabilitiesManager', () => ({
	...jest.requireActual('../../services/CapabilitiesManager'),
	getTalkConfig: jest.fn((...args) => {
		if (args[0] === 'local' && args[1] === 'conversations' && args[2] === 'can-create') {
			return mockCanCreateConversations
		} else {
			return jest.requireActual('../../services/CapabilitiesManager').getTalkConfig(...args)
		}
	}),
}))

// short-circuit debounce
jest.mock('debounce', () => jest.fn().mockImplementation((fn) => fn))

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

	const RecycleScrollerStub = {
		props: {
			items: Array,
			itemSize: Number,
		},
		template: `<ul class="vue-recycle-scroller-STUB">
			<li v-for="item in items" class="vue-recycle-scroller-STUB-item" :class="item.type" >{{ item?.name ?? item.object?.name ?? item.object?.label ?? item.hint }}</li>
			</ul>`,
	}

	const mountComponent = () => {
		return mount(LeftSidebar, {
			localVue,
			router,
			store,
			provide: {
				'NcContent:setHasAppNavigation': () => {},
			},
			stubs: {
				// to prevent user status fetching
				NcAvatar: true,
				// to prevent complex dialog logic
				NcActions: true,
				NcModal: true,
				RecycleScroller: RecycleScrollerStub,
			},
		})
	}

	beforeEach(() => {
		jest.useFakeTimers()

		localVue = createLocalVue()
		localVue.use(Vuex)
		localVue.use(VueRouter)
		setActivePinia(createPinia())
		const actorStore = useActorStore()

		loadStateSettings = {
			circles_enabled: true,
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
		actorStore.userId = 'current-user'
		testStoreConfig.modules.conversationsStore.getters.conversationsList = conversationsListMock
		testStoreConfig.modules.conversationsStore.actions.fetchConversations = fetchConversationsAction
		testStoreConfig.modules.conversationsStore.actions.addConversation = addConversationAction
		testStoreConfig.modules.conversationsStore.actions.createOneToOneConversation = createOneToOneConversationAction

		store = new Vuex.Store(testStoreConfig)
	})

	afterEach(() => {
		mockCanCreateConversations = true
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
				isArchived: false,
				name: 'one',
				displayName: 'one',
			}, {
				id: 200,
				token: 't200',
				lastActivity: 80,
				isFavorite: false,
				isArchived: true,
				name: 'two',
				displayName: 'two',
			}, {
				id: 300,
				token: 't300',
				lastActivity: 120,
				isFavorite: true,
				isArchived: false,
				name: 'three',
				displayName: 'three',
			}]

			// note: need a copy because the Vue modifies it when sorting
			conversationsListMock.mockImplementation(() => cloneDeep(conversationsList))
		})

		test('fetches and renders conversation list initially', async () => {
			const conversationsReceivedEvent = jest.fn()
			EventBus.once('conversations-received', conversationsReceivedEvent)
			fetchConversationsAction.mockResolvedValueOnce()

			const wrapper = mountComponent()

			await requestTabLeadership()

			expect(fetchConversationsAction).toHaveBeenCalledWith(expect.anything(), expect.anything())

			expect(wrapper.vm.searchText).toBe('')

			expect(conversationsReceivedEvent).not.toHaveBeenCalled()

			// move on past the fetchConversation call
			await flushPromises()

			const normalConversationsList = conversationsList.filter((conversation) => !conversation.isArchived)
			const conversationListItems = wrapper.findAll('.vue-recycle-scroller-STUB-item')
			expect(conversationListItems).toHaveLength(normalConversationsList.length)
			expect(conversationListItems.at(0).text()).toStrictEqual(normalConversationsList[0].displayName)
			expect(conversationListItems.at(1).text()).toStrictEqual(normalConversationsList[1].displayName)

			expect(conversationsReceivedEvent).toHaveBeenCalledWith({
				singleConversation: false,
			})
		})

		test('re-fetches conversations every 30 seconds', async () => {
			const wrapper = mountComponent()

			await requestTabLeadership()

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

			await requestTabLeadership()

			expect(wrapper.exists()).toBeTruthy()
			expect(fetchConversationsAction).toHaveBeenCalled()

			fetchConversationsAction.mockClear()

			// move past async call
			await flushPromises()
			expect(fetchConversationsAction).not.toHaveBeenCalled()

			EventBus.emit('should-refresh-conversations', {})

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
			}, {
				id: 200,
				token: 't200',
				lastActivity: 80,
				isFavorite: false,
				name: 'searched by name',
				displayName: 'another one',
			}, {
				id: 300,
				token: 't300',
				lastActivity: 120,
				isFavorite: true,
				name: 'excluded',
				displayName: 'excluded from results',
			}]

			listedResults = [{
				id: 1000,
				name: 'listed one searched',
				displayName: 'listed one searched',
				token: 'listed-token-1',
			}, {
				id: 1001,
				name: 'listed two searched',
				displayName: 'listed two searched',
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
			autocompleteQuery.mockResolvedValue({
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

			await requestTabLeadership()

			expect(fetchConversationsAction).toHaveBeenCalledWith(expect.anything(), expect.anything())

			const searchBox = wrapper.findComponent({ name: 'SearchBox' })
			expect(searchBox.exists()).toBeTruthy()

			// move past async call
			await flushPromises()

			await searchBox.find('input[type="text"]').setValue(searchTerm)

			await flushPromises()

			return wrapper
		}

		/**
		 * @param {Array} usersResults Result options returned by the APIs
		 * @param {Array} groupsResults Result options returned by the APIs
		 * @param {Array} circlesResults Result options returned by the APIs
		 * @param {Array} listedResults The displayed results
		 * @param {string} remainedCaption The caption of the "No search results" section
		 * @param {boolean} circlesEnabled Whether circles are enabled
		 * @param {boolean} startConversations Whether the user can start conversations
		 */
		function prepareExpectedResults(usersResults, groupsResults, circlesResults, listedResults, remainedCaption, circlesEnabled = true, startConversations = true) {
			// Check all conversations, users, groups and circles
			const conversationList = conversationsList
				.filter((item) => item.name.includes(SEARCH_TERM) || item.displayName.includes(SEARCH_TERM))
				.map((item) => { return item.name })
			const searchedUsersResults = usersResults
				.filter((item) => item.label.includes(SEARCH_TERM) && item.id !== 'current-user' && item.source === 'users')
				.map((item) => { return item.label })
			const searchedGroupsResults = groupsResults
				.filter((item) => item.label.includes(SEARCH_TERM))
				.map((item) => { return item.label })
			const searchedCirclesResults = circlesResults
				.filter((item) => item.label.includes(SEARCH_TERM))
				.map((item) => { return item.label })
			const searchedFederatedUsersResults = usersResults
				.filter((item) => item.label.includes(SEARCH_TERM) && item.id === 'current-user' && item.source === 'remotes')

			const itemsListNames = []
			if (conversationList.length > 0) {
				itemsListNames.push('Conversations', ...conversationList)
			} else {
				itemsListNames.push('Conversations', 'no matches found')
			}
			if (startConversations) {
				itemsListNames.push(SEARCH_TERM)
			}
			if (listedResults.length > 0) {
				itemsListNames.push('Open conversations', ...listedResults.map((item) => item.name))
			}
			if (searchedUsersResults.length > 0) {
				itemsListNames.push('Users', ...searchedUsersResults)
			}
			if (startConversations && searchedGroupsResults.length > 0) {
				itemsListNames.push('Groups', ...searchedGroupsResults)
			}
			if (startConversations && circlesEnabled && searchedCirclesResults.length > 0) {
				itemsListNames.push('Teams', ...searchedCirclesResults)
			}
			if (startConversations && searchedFederatedUsersResults.length > 0) {
				itemsListNames.push('Teams', ...searchedFederatedUsersResults)
			}
			itemsListNames.push(remainedCaption, 'No search results')

			return itemsListNames
		}

		describe('displaying search results', () => {
			test('displays search results when search is active', async () => {
				const wrapper = await testSearch(
					SEARCH_TERM,
					[...usersResults, ...groupsResults, ...circlesResults],
					listedResults,
					{
						circles_enabled: true,
					},
				)
				const itemsListNames = prepareExpectedResults(usersResults, groupsResults, circlesResults, listedResults, 'Other sources')
				const itemsList = wrapper.findAll('.vue-recycle-scroller-STUB-item')
				expect(itemsList.exists()).toBeTruthy()
				expect(itemsList).toHaveLength(itemsListNames.length)
				itemsListNames.forEach((name, index) => {
					expect(itemsList.at(index).text()).toStrictEqual(name)
				})
			})

			test('only shows user search results when cannot create conversations', async () => {
				mockCanCreateConversations = false

				const wrapper = await testSearch(
					SEARCH_TERM,
					[...usersResults, ...groupsResults, ...circlesResults],
					listedResults,
					{
						circles_enabled: true,
					},
				)

				const itemsListNames = prepareExpectedResults(usersResults, groupsResults, circlesResults, listedResults, 'Groups and teams', true, false)
				const itemsList = wrapper.findAll('.vue-recycle-scroller-STUB-item')
				expect(itemsList.exists()).toBeTruthy()
				expect(itemsList).toHaveLength(itemsListNames.length)
				expect(itemsListNames.filter((item) => ['Groups', 'Teams', 'Federated users', SEARCH_TERM].includes(item)).length).toBe(0)
				itemsListNames.forEach((name, index) => {
					expect(itemsList.at(index).text()).toStrictEqual(name)
				})
			})

			test('does not show circles results when circles are disabled', async () => {
				const wrapper = await testSearch(
					SEARCH_TERM,
					[...usersResults, ...groupsResults],
					listedResults,
					{
						circles_enabled: false,
					},
				)

				const itemsListNames = prepareExpectedResults(usersResults, groupsResults, circlesResults, listedResults, 'Other sources', false, true)
				const itemsList = wrapper.findAll('.vue-recycle-scroller-STUB-item')
				expect(itemsList.exists()).toBeTruthy()
				expect(itemsList).toHaveLength(itemsListNames.length)
				expect(itemsListNames.filter((item) => ['Teams'].includes(item)).length).toBe(0)
				itemsListNames.forEach((name, index) => {
					expect(itemsList.at(index).text()).toStrictEqual(name)
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

				const captionsEls = wrapper.findAll('.caption')
				expect(captionsEls.exists()).toBeTruthy()
				if (listedResults.length > 0) {
					expect(captionsEls.length).toBeGreaterThan(2)
					expect(captionsEls.at(0).text()).toBe('Conversations')
					expect(captionsEls.at(1).text()).toBe('Open conversations')
				} else {
					expect(captionsEls.length).toBeGreaterThan(1)
					expect(captionsEls.at(0).text()).toBe('Conversations')
				}
				// last dynamic caption for "No search results"
				expect(captionsEls.at(-1).text()).toBe(expectedCaption)

				return wrapper
			}

			test('displays all types in caption when nothing was found', async () => {
				await testSearchNotFound(
					SEARCH_TERM,
					[],
					[],
					{
						circles_enabled: true,
					},
					'Users, groups and teams',
				)
			})
			test('displays all types in caption when only listed conversations were found', async () => {
				await testSearchNotFound(
					SEARCH_TERM,
					[],
					listedResults,
					{
						circles_enabled: true,
					},
					'Users, groups and teams',
				)
			})
			test('displays all types minus circles when nothing was found but circles is disabled', async () => {
				await testSearchNotFound(
					SEARCH_TERM,
					[],
					[],
					{
						circles_enabled: false,
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
					},
					'Groups and teams',
				)
			})
			test('displays caption for users and circles not found', async () => {
				await testSearchNotFound(
					SEARCH_TERM,
					[...groupsResults],
					[],
					{
						circles_enabled: true,
					},
					'Users and teams',
				)
			})
		})
	})

	describe('new conversation button', () => {
		beforeEach(() => {
			conversationsListMock.mockReturnValue([])
			fetchConversationsAction.mockResolvedValueOnce()
		})
		test('shows new conversation button if user can start conversations', () => {
			const wrapper = mountComponent()
			const newConversationbutton = findNcActionButton(wrapper, 'Create a new conversation')
			expect(newConversationbutton.exists()).toBeTruthy()
		})
		test('does not show new conversation button if user cannot start conversations', () => {
			mockCanCreateConversations = false

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

		const button = findNcButton(wrapper, 'Talk settings')
		expect(button.exists()).toBeTruthy()

		await button.trigger('click')

		unsubscribe('show-settings', eventHandler)

		expect(eventHandler).toHaveBeenCalled()
	})
})
