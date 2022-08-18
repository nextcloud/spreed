/* eslint-disable import/no-named-as-default-member */
import Vuex from 'vuex'
import { createLocalVue, mount } from '@vue/test-utils'
import VueRouter from 'vue-router'
import router from '../../router/router.js'
import { cloneDeep } from 'lodash'
import storeConfig from '../../store/storeConfig.js'
import { loadState } from '@nextcloud/initial-state'
import { EventBus } from '../../services/EventBus.js'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import {
	searchPossibleConversations,
	searchListedConversations,
} from '../../services/conversationsService.js'

import LeftSidebar from './LeftSidebar.vue'

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

	/**
	 *
	 */
	function mountComponent() {
		return mount(LeftSidebar, {
			localVue,
			router,
			store,
			stubs: {
				// to prevent user status fetching
				NcAvatar: true,
				// to prevent complex dialog logic
				NewGroupConversation: true,
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
		fetchConversationsAction = jest.fn()
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

			expect(fetchConversationsAction).toHaveBeenCalledWith(expect.anything(), undefined)
			expect(conversationsListMock).toHaveBeenCalled()

			const appNavEl = wrapper.findComponent({ name: 'NcAppNavigation' })
			const listEl = appNavEl.findComponent({ name: 'ConversationsList' })

			expect(listEl.exists()).toBe(true)
			expect(listEl.props('searchText')).toBe('')
			expect(listEl.props('initialisedConversations')).toBe(false)

			expect(conversationsReceivedEvent).not.toHaveBeenCalled()

			// move on past the fetchConversation call
			await wrapper.vm.$nextTick()
			await wrapper.vm.$nextTick()

			expect(listEl.props('initialisedConversations')).toBe(true)
			expect(listEl.props('conversationsList')).toStrictEqual([
				conversationsList[2],
				conversationsList[0],
				conversationsList[1],
			])

			expect(conversationsReceivedEvent).toHaveBeenCalledWith({
				singleConversation: false,
			})
		})

		test('re-fetches conversations every 30 seconds', async () => {
			const wrapper = mountComponent()

			expect(fetchConversationsAction).toHaveBeenCalled()

			fetchConversationsAction.mockClear()

			// move past async call
			await wrapper.vm.$nextTick()
			await wrapper.vm.$nextTick()

			expect(fetchConversationsAction).not.toHaveBeenCalled()

			jest.advanceTimersByTime(15000)

			expect(fetchConversationsAction).not.toHaveBeenCalled()

			jest.advanceTimersByTime(20000)

			expect(fetchConversationsAction).toHaveBeenCalled()
		})

		test('re-fetches conversations when receiving bus event', async () => {
			const wrapper = mountComponent()

			expect(fetchConversationsAction).toHaveBeenCalled()

			fetchConversationsAction.mockClear()

			// move past async call
			await wrapper.vm.$nextTick()
			await wrapper.vm.$nextTick()

			expect(fetchConversationsAction).not.toHaveBeenCalled()

			EventBus.$emit('should-refresh-conversations')

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
			searchPossibleConversations.mockResolvedValueOnce({
				data: {
					ocs: {
						data: possibleResults,
					},
				},
			})
			searchListedConversations.mockResolvedValueOnce({
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

			expect(fetchConversationsAction).toHaveBeenCalledWith(expect.anything(), undefined)
			expect(conversationsListMock).toHaveBeenCalled()

			const appNavEl = wrapper.findComponent({ name: 'NcAppNavigation' })
			const searchBoxEl = appNavEl.findComponent({ name: 'SearchBox' })
			expect(searchBoxEl.exists()).toBe(true)

			// move past async call
			await wrapper.vm.$nextTick()
			await wrapper.vm.$nextTick()

			await searchBoxEl.find('input[type="text"]').setValue(searchTerm)
			expect(searchBoxEl.props('isSearching')).toBe(true)

			await wrapper.vm.$nextTick()
			await wrapper.vm.$nextTick()

			return wrapper
		}

		describe('displaying search results', () => {
			test('displays search results when search is active', async () => {
				const wrapper = await testSearch(
					'search',
					[...usersResults, ...groupsResults, ...circlesResults],
					listedResults,
					{
						circles_enabled: true,
						start_conversations: true,
					}
				)

				const appNavEl = wrapper.findComponent({ name: 'NcAppNavigation' })

				const captionListEl = appNavEl.findAllComponents({ name: 'NcAppNavigationCaption' })

				expect(captionListEl.exists()).toBe(true)
				expect(captionListEl.length).toBe(5)
				expect(captionListEl.at(0).props('title')).toStrictEqual('Conversations')
				expect(captionListEl.at(1).props('title')).toStrictEqual('Open conversations')
				expect(captionListEl.at(2).props('title')).toStrictEqual('Users')
				expect(captionListEl.at(3).props('title')).toStrictEqual('Groups')
				expect(captionListEl.at(4).props('title')).toStrictEqual('Circles')

				const listEl = appNavEl.findComponent({ name: 'ConversationsList' })

				expect(listEl.exists()).toBe(true)
				expect(listEl.props('conversationsList')).toStrictEqual([
					conversationsList[0],
					conversationsList[1],
				])

				const listedEls = appNavEl.findAllComponents({ name: 'Conversation' })
				expect(listedEls.exists()).toBe(true)
				expect(listedEls.length).toBe(4)
				expect(listedEls.at(0).props('item')).toStrictEqual(conversationsList[0])
				expect(listedEls.at(1).props('item')).toStrictEqual(conversationsList[1])
				expect(listedEls.at(2).props('item')).toStrictEqual(listedResults[0])
				expect(listedEls.at(3).props('item')).toStrictEqual(listedResults[1])

				const optionsEls = appNavEl.findAllComponents({ name: 'ConversationsOptionsList' })
				expect(optionsEls.exists()).toBe(true)
				expect(optionsEls.length).toBe(3)
				expect(optionsEls.at(0).props('items')).toStrictEqual([usersResults[1], usersResults[2]])
				expect(optionsEls.at(1).props('items')).toStrictEqual([groupsResults[0], groupsResults[1]])
				expect(optionsEls.at(2).props('items')).toStrictEqual([circlesResults[0], circlesResults[1]])
			})
			test('only shows user search results when cannot create conversations', async () => {
				const wrapper = await testSearch(
					'search',
					[...usersResults, ...groupsResults, ...circlesResults],
					listedResults,
					{
						circles_enabled: true,
						start_conversations: false,
					}
				)

				const appNavEl = wrapper.findComponent({ name: 'NcAppNavigation' })

				const captionListEl = appNavEl.findAllComponents({ name: 'NcAppNavigationCaption' })

				expect(captionListEl.exists()).toBe(true)
				expect(captionListEl.length).toBe(3)
				expect(captionListEl.at(0).props('title')).toStrictEqual('Conversations')
				expect(captionListEl.at(1).props('title')).toStrictEqual('Open conversations')
				expect(captionListEl.at(2).props('title')).toStrictEqual('Users')

				const listEl = appNavEl.findComponent({ name: 'ConversationsList' })

				expect(listEl.exists()).toBe(true)
				expect(listEl.props('conversationsList')).toStrictEqual([
					conversationsList[0],
					conversationsList[1],
				])

				const listedEls = appNavEl.findAllComponents({ name: 'Conversation' })
				expect(listedEls.exists()).toBe(true)
				expect(listedEls.length).toBe(4)
				expect(listedEls.at(0).props('item')).toStrictEqual(conversationsList[0])
				expect(listedEls.at(1).props('item')).toStrictEqual(conversationsList[1])
				expect(listedEls.at(2).props('item')).toStrictEqual(listedResults[0])
				expect(listedEls.at(3).props('item')).toStrictEqual(listedResults[1])

				const optionsEls = appNavEl.findAllComponents({ name: 'ConversationsOptionsList' })
				expect(optionsEls.exists()).toBe(true)
				expect(optionsEls.at(0).props('items')).toStrictEqual([usersResults[1], usersResults[2]])
				expect(optionsEls.length).toBe(1)
			})
			test('does not show circles results when circles are disabled', async () => {
				const wrapper = await testSearch(
					'search',
					[...usersResults, ...groupsResults],
					listedResults,
					{
						circles_enabled: false,
						start_conversations: true,
					}
				)

				const appNavEl = wrapper.findComponent({ name: 'NcAppNavigation' })

				const captionListEl = appNavEl.findAllComponents({ name: 'NcAppNavigationCaption' })

				expect(captionListEl.exists()).toBe(true)
				expect(captionListEl.length).toBe(4)
				expect(captionListEl.at(0).props('title')).toStrictEqual('Conversations')
				expect(captionListEl.at(1).props('title')).toStrictEqual('Open conversations')
				expect(captionListEl.at(2).props('title')).toStrictEqual('Users')
				expect(captionListEl.at(3).props('title')).toStrictEqual('Groups')

				const listEl = appNavEl.findComponent({ name: 'ConversationsList' })

				expect(listEl.exists()).toBe(true)
				expect(listEl.props('conversationsList')).toStrictEqual([
					conversationsList[0],
					conversationsList[1],
				])

				const listedEls = appNavEl.findAllComponents({ name: 'Conversation' })
				expect(listedEls.exists()).toBe(true)
				expect(listedEls.length).toBe(4)
				expect(listedEls.at(0).props('item')).toStrictEqual(conversationsList[0])
				expect(listedEls.at(1).props('item')).toStrictEqual(conversationsList[1])
				expect(listedEls.at(2).props('item')).toStrictEqual(listedResults[0])
				expect(listedEls.at(3).props('item')).toStrictEqual(listedResults[1])

				const optionsEls = appNavEl.findAllComponents({ name: 'ConversationsOptionsList' })
				expect(optionsEls.exists()).toBe(true)
				expect(optionsEls.length).toBe(2)
				expect(optionsEls.at(0).props('items')).toStrictEqual([usersResults[1], usersResults[2]])
				expect(optionsEls.at(1).props('items')).toStrictEqual([groupsResults[0], groupsResults[1]])
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

				const appNavEl = wrapper.findComponent({ name: 'NcAppNavigation' })
				const listEl = appNavEl.findComponent({ name: 'ConversationsList' })
				expect(listEl.exists()).toBe(true)
				const listedEls = appNavEl.findAllComponents({ name: 'Conversation' })
				expect(listedEls.exists()).toBe(true)
				expect(listedEls.length).toBe(2 + listedResults.length)
				// only filters the existing conversations in the list
				expect(listedEls.at(0).props('item')).toStrictEqual(conversationsList[0])
				expect(listedEls.at(1).props('item')).toStrictEqual(conversationsList[1])

				const captionsEls = appNavEl.findAllComponents({ name: 'NcAppNavigationCaption' })
				expect(captionsEls.exists()).toBe(true)
				if (listedResults.length > 0) {
					expect(captionsEls.length).toBeGreaterThan(2)
					expect(captionsEls.at(0).props('title')).toBe('Conversations')
					expect(captionsEls.at(1).props('title')).toBe('Open conversations')
				} else {
					expect(captionsEls.length).toBeGreaterThan(1)
					expect(captionsEls.at(0).props('title')).toBe('Conversations')
				}
				// last dynamic caption for "No search results"
				expect(captionsEls.at(captionsEls.length - 1).props('title')).toBe(expectedCaption)

				return wrapper
			}

			test('displays all types in caption when nothing was found', async () => {
				await testSearchNotFound(
					'search',
					[],
					[],
					{
						circles_enabled: true,
						start_conversations: true,
					},
					'Users, groups and circles'
				)
			})

			test('displays all types in caption when only listed conversations were found', async () => {
				await testSearchNotFound(
					'search',
					[],
					listedResults,
					{
						circles_enabled: true,
						start_conversations: true,
					},
					'Users, groups and circles'
				)
			})

			test('displays all types minus circles when nothing was found but circles is disabled', async () => {
				await testSearchNotFound(
					'search',
					[],
					[],
					{
						circles_enabled: false,
						start_conversations: true,
					},
					'Users and groups'
				)
			})

			test('displays caption for users and groups not found', async () => {
				await testSearchNotFound(
					'search',
					[...circlesResults],
					[],
					{
						circles_enabled: true,
						start_conversations: true,
					},
					'Users and groups'
				)
			})
			test('displays caption for users not found', async () => {
				await testSearchNotFound(
					'search',
					[...circlesResults, ...groupsResults],
					[],
					{
						circles_enabled: true,
						start_conversations: true,
					},
					'Users'
				)
			})
			test('displays caption for groups not found', async () => {
				await testSearchNotFound(
					'search',
					[...usersResults, ...circlesResults],
					[],
					{
						circles_enabled: true,
						start_conversations: true,
					},
					'Groups'
				)
			})
			test('displays caption for groups and circles not found', async () => {
				await testSearchNotFound(
					'search',
					[...usersResults],
					[],
					{
						circles_enabled: true,
						start_conversations: true,
					},
					'Groups and circles'
				)
			})
			test('displays caption for users and circles not found', async () => {
				await testSearchNotFound(
					'search',
					[...groupsResults],
					[],
					{
						circles_enabled: true,
						start_conversations: true,
					},
					'Users and circles'
				)
			})
		})

		describe('clicking search results', () => {
			test('joins listed conversation from search result', async () => {
				const wrapper = await testSearch('search', [], listedResults)

				const appNavEl = wrapper.findComponent({ name: 'NcAppNavigation' })
				const listedEls = appNavEl.findAllComponents({ name: 'Conversation' })
				expect(listedEls.exists()).toBe(true)
				expect(listedEls.length).toBe(4)
				await listedEls.at(3).find('a').trigger('click')

				expect(addConversationAction).toHaveBeenCalledWith(expect.anything(), listedResults[1])
				expect(wrapper.vm.$route.name).toBe('conversation')
				expect(wrapper.vm.$route.params).toStrictEqual({ token: 'listed-token-2' })
			})
			test('creates one to one conversation from user search result', async () => {
				createOneToOneConversationAction.mockResolvedValue({
					id: 9999,
					token: 'new-conversation',
				})

				const wrapper = await testSearch('search', [...usersResults], [])

				const appNavEl = wrapper.findComponent({ name: 'NcAppNavigation' })
				const optionsEls = appNavEl.findAllComponents({ name: 'ConversationsOptionsList' })
				expect(optionsEls.exists()).toBe(true)
				await optionsEls.at(0).findAll('a').at(1).trigger('click')

				expect(createOneToOneConversationAction).toHaveBeenCalledWith(expect.anything(), 'two-user')
				expect(wrapper.vm.$route.name).toBe('conversation')
				expect(wrapper.vm.$route.params).toStrictEqual({ token: 'new-conversation' })
			})
			test('shows group conversation dialog when clicking search result', async () => {
				const eventHandler = jest.fn()
				EventBus.$once('new-group-conversation-dialog', eventHandler)

				const wrapper = await testSearch('search', [...groupsResults], [])

				const appNavEl = wrapper.findComponent({ name: 'NcAppNavigation' })
				const optionsEls = appNavEl.findAllComponents({ name: 'ConversationsOptionsList' })
				expect(optionsEls.exists()).toBe(true)
				await optionsEls.at(0).findAll('a').at(1).trigger('click')

				expect(eventHandler).toHaveBeenCalledWith(groupsResults[1])

				// nothing created yet
				expect(createOneToOneConversationAction).not.toHaveBeenCalled()
				expect(addConversationAction).not.toHaveBeenCalled()
			})
			test('shows circles conversation dialog when clicking search result', async () => {
				const eventHandler = jest.fn()
				EventBus.$once('new-group-conversation-dialog', eventHandler)

				const wrapper = await testSearch('search', [...circlesResults], [])

				const appNavEl = wrapper.findComponent({ name: 'NcAppNavigation' })
				const optionsEls = appNavEl.findAllComponents({ name: 'ConversationsOptionsList' })
				expect(optionsEls.exists()).toBe(true)
				await optionsEls.at(0).findAll('a').at(1).trigger('click')

				expect(eventHandler).toHaveBeenCalledWith(circlesResults[1])

				// nothing created yet
				expect(createOneToOneConversationAction).not.toHaveBeenCalled()
			})
			test('clears search results when joining user chat', async () => {
				createOneToOneConversationAction.mockResolvedValue({
					id: 9999,
					token: 'new-conversation',
				})

				const wrapper = await testSearch('search', [...usersResults], [])

				const appNavEl = wrapper.findComponent({ name: 'NcAppNavigation' })
				const searchBoxEl = appNavEl.findComponent({ name: 'SearchBox' })
				const input = searchBoxEl.find('input[type="text"]')
				expect(input.element.value).toBe('search')

				const optionsEls = appNavEl.findAllComponents({ name: 'ConversationsOptionsList' })
				expect(optionsEls.exists()).toBe(true)
				await optionsEls.at(0).findAll('a').at(1).trigger('click')

				await wrapper.vm.$nextTick()

				expect(searchBoxEl.exists()).toBe(true)
				expect(input.element.value).toBe('')
			})
			test('does not clear search results when clicking group chat', async () => {
				const wrapper = await testSearch('search', [...groupsResults], [])

				const appNavEl = wrapper.findComponent({ name: 'NcAppNavigation' })
				const searchBoxEl = appNavEl.findComponent({ name: 'SearchBox' })
				const input = searchBoxEl.find('input[type="text"]')
				expect(input.element.value).toBe('search')

				const optionsEls = appNavEl.findAllComponents({ name: 'ConversationsOptionsList' })
				expect(optionsEls.exists()).toBe(true)
				await optionsEls.at(0).findAll('a').at(1).trigger('click')

				await wrapper.vm.$nextTick()

				expect(searchBoxEl.exists()).toBe(true)
				expect(input.element.value).toBe('search')
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
			const buttonEl = wrapper.findComponent({ name: 'NewGroupConversation' })
			expect(buttonEl.exists()).toBe(true)
		})
		test('does not show new conversation button if user cannot start conversations', () => {
			loadStateSettings.start_conversations = false

			const wrapper = mountComponent()
			const buttonEl = wrapper.findComponent({ name: 'NewGroupConversation' })
			expect(buttonEl.exists()).toBe(false)
		})
	})

	test('shows settings when clicking the settings button', async () => {
		conversationsListMock.mockImplementation(() => [])
		const eventHandler = jest.fn()
		subscribe('show-settings', eventHandler)
		const wrapper = mountComponent()

		const appNavEl = wrapper.findComponent({ name: 'NcAppNavigation' })
		const button = appNavEl.find('.settings-button')
		expect(button.exists()).toBe(true)

		await button.trigger('click')

		unsubscribe('show-settings', eventHandler)

		expect(eventHandler).toHaveBeenCalled()
	})
})
