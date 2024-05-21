/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { shallowMount, mount } from '@vue/test-utils'
import flushPromises from 'flush-promises' // TODO fix after migration to @vue/test-utils v2.0.0
import { cloneDeep } from 'lodash'
import Vuex from 'vuex'

// import { showSuccess, showError } from '@nextcloud/dialogs'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'

import Conversation from './Conversation.vue'

import router from '../../../__mocks__/router.js'
import { CONVERSATION, PARTICIPANT, ATTENDEE } from '../../../constants.js'
import { leaveConversation } from '../../../services/participantsService.js'
import storeConfig from '../../../store/storeConfig.js'
import { findNcActionButton } from '../../../test-helpers.js'

jest.mock('@nextcloud/dialogs', () => ({
	showSuccess: jest.fn(),
	showError: jest.fn(),
}))

jest.mock('../../../services/participantsService', () => ({
	leaveConversation: jest.fn(),
}))

// TODO fix after RouterLinkStub can support slots https://github.com/vuejs/vue-test-utils/issues/1803
const RouterLinkStub = true

const NcListItemStub = {
	name: 'NcListItem',
	template: '<li><slot name="actions"></slot><slot name="extra"></slot></li>',
}

describe('Conversation.vue', () => {
	const TOKEN = 'XXTOKENXX'
	let store
	let testStoreConfig
	let item
	let messagesMock

	beforeEach(() => {
		testStoreConfig = cloneDeep(storeConfig)
		messagesMock = jest.fn().mockReturnValue({})
		testStoreConfig.modules.messagesStore.getters.messages = () => messagesMock
		store = new Vuex.Store(testStoreConfig)

		// common defaults
		item = {
			token: TOKEN,
			actorId: 'actor-id-1',
			actorType: ATTENDEE.ACTOR_TYPE.USERS,
			participants: [
			],
			participantType: PARTICIPANT.TYPE.USER,
			unreadMessages: 0,
			unreadMention: false,
			objectType: '',
			type: CONVERSATION.TYPE.GROUP,
			displayName: 'conversation one',
			isFavorite: false,
			lastMessage: {
				actorId: 'user-id-alice',
				actorDisplayName: 'Alice Wonderland',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'hello',
				messageParameters: {},
				systemMessage: '',
				timestamp: 100,
			},
			canLeaveConversation: true,
			canDeleteConversation: true,
		}

		// hack to catch last message rendering
		const oldTee = global.t
		global.t = jest.fn().mockImplementation(function(pkg, text, data) {
			if (data && data.lastMessage) {
				return (data.actor || 'You') + ': ' + data.lastMessage
			}
			return oldTee.apply(this, arguments)
		})
	})

	afterEach(() => {
		jest.clearAllMocks()
	})

	test('renders conversation entry', () => {
		const wrapper = mount(Conversation, {
			global: {
				plugins: [store],
				stubs: {
					RouterLink: RouterLinkStub,
				},
			},
			props: {
				isSearchResult: false,
				item,
			},
		})

		const el = wrapper.findComponent({ name: 'NcListItem' })
		expect(el.exists()).toBe(true)
		expect(el.props('name')).toBe('conversation one')

		const icon = el.findComponent({ name: 'ConversationIcon' })
		expect(icon.props('item')).toStrictEqual(item)
		expect(icon.props('hideFavorite')).toStrictEqual(false)
		expect(icon.props('hideCall')).toStrictEqual(false)
	})

	describe('displayed subname', () => {
		/**
		 * @param {object} item Conversation data
		 * @param {string} expectedText Expected subname of the conversation item
		 * @param {boolean} isSearchResult Whether or not the item is a search result (has no … menu)
		 */
		function testConversationLabel(item, expectedText, isSearchResult = false) {
			const wrapper = mount(Conversation, {
				global: {
					plugins: [store],
					stubs: {
						RouterLink: RouterLinkStub,
					},
				},
				props: {
					isSearchResult,
					item,
				},
			})

			const el = wrapper.findComponent({ name: 'NcListItem' })
			expect(el.vm.$slots.subname()[0].children.trim()).toBe(expectedText)
		}

		test('display joining conversation message when not joined yet', () => {
			item.actorId = null
			testConversationLabel(item, 'Joining conversation …')
		})

		test('displays nothing when there is no last chat message', () => {
			item.lastMessage = {}
			testConversationLabel(item, 'No messages')
		})

		describe('author name', () => {
			test('displays last chat message with shortened author name', () => {
				testConversationLabel(item, 'Alice: hello')
			})

			test('displays last chat message with author name if no space in name', () => {
				item.lastMessage.actorDisplayName = 'Bob'
				testConversationLabel(item, 'Bob: hello')
			})

			test('displays own last chat message with "You" as author', () => {
				item.lastMessage.actorId = 'actor-id-1'

				testConversationLabel(item, 'You: hello')
			})

			test('displays last system message without author', () => {
				item.lastMessage.message = 'Alice has joined the call'
				item.lastMessage.systemMessage = 'call_joined'

				testConversationLabel(item, 'Alice has joined the call')
			})

			test('displays last message without author in one to one conversations', () => {
				item.type = CONVERSATION.TYPE.ONE_TO_ONE
				testConversationLabel(item, 'hello')
			})

			test('displays own last message with "You" author in one to one conversations', () => {
				item.type = CONVERSATION.TYPE.ONE_TO_ONE
				item.lastMessage.actorId = 'actor-id-1'

				testConversationLabel(item, 'You: hello')
			})

			test('displays last guest message with default author when none set', () => {
				item.type = CONVERSATION.TYPE.PUBLIC
				item.lastMessage.actorDisplayName = ''
				item.lastMessage.actorType = ATTENDEE.ACTOR_TYPE.GUESTS

				testConversationLabel(item, 'Guest: hello')
			})

			test('displays description for search results', () => {
				// search results have no actor id
				item.actorId = null
				item.description = 'This is a description'
				testConversationLabel(item, 'This is a description', true)
			})
		})

		test('replaces placeholders in rich object of last message', () => {
			item.lastMessage.message = '{file}'
			item.lastMessage.messageParameters = {
				file: {
					name: 'filename.jpg',
				},
			}

			testConversationLabel(item, 'Alice: filename.jpg')
		})
	})

	describe('unread messages counter', () => {
		/**
		 * @param {object} item Conversation data
		 * @param {string} expectedCounterText The expected unread counter
		 * @param {boolean} expectedOutlined The expected outlined counter
		 * @param {boolean} expectedHighlighted Whether or not the unread counter is highlighted with primary color
		 */
		function testCounter(item, expectedCounterText, expectedOutlined, expectedHighlighted) {
			const wrapper = mount(Conversation, {
				global: {
					plugins: [store],
					stubs: {
						RouterLink: RouterLinkStub,
					},
				},
				props: {
					isSearchResult: false,
					item,
				},
			})

			const el = wrapper.findComponent({ name: 'NcListItem' })
			expect(el.exists()).toBe(true)

			expect(el.props('counterNumber')).toBe(expectedCounterText)
			if (expectedOutlined) {
				expect(el.props('counterType')).toBe('outlined')
			}
			if (expectedHighlighted) {
				expect(el.props('counterType')).toBe('highlighted')
			}
		}

		test('renders unread messages counter', () => {
			item.unreadMessages = 5
			item.unreadMention = false
			item.unreadMentionDirect = false
			testCounter(item, 5, false, false)
		})
		test('renders unread mentions highlighted for non one-to-one conversations', () => {
			item.unreadMessages = 5
			item.unreadMention = true
			item.unreadMentionDirect = true
			testCounter(item, 5, false, true)
		})
		test('renders group mentions outlined for non one-to-one conversations', () => {
			item.unreadMessages = 5
			item.unreadMention = true
			item.unreadMentionDirect = false
			testCounter(item, 5, true, false)
		})
		test('renders unread mentions always highlighted for one-to-one conversations', () => {
			item.unreadMessages = 5
			item.unreadMention = false
			item.unreadMentionDirect = false
			item.type = CONVERSATION.TYPE.ONE_TO_ONE
			testCounter(item, 5, false, true)
		})

		test('does not render counter when no unread messages', () => {
			const wrapper = mount(Conversation, {
				global: {
					plugins: [store],
					stubs: {
						RouterLink: RouterLinkStub,
					},
				},
				props: {
					isSearchResult: false,
					item,
				},
			})

			const el = wrapper.findComponent({ name: 'NcListItem' })
			expect(el.exists()).toBe(true)

			expect(el.vm.$slots.counter).not.toBeDefined()
		})
	})

	describe('actions (real router)', () => {
		test('change route on click event', async () => {
			const wrapper = mount(Conversation, {
				global: {
					plugins: [router, store],
					stubs: {
						NcListItem,
					},
				},
				props: {
					isSearchResult: false,
					item,
				},
			})

			const el = wrapper.findComponent({ name: 'NcListItem' })
			expect(el.exists()).toBe(true)

			await el.find('a').trigger('click')
			await router.isReady()

			expect(wrapper.vm.$route.name).toBe('conversation')
			expect(wrapper.vm.$route.params).toStrictEqual({ token: TOKEN })
		})
	})

	describe('actions (mock router)', () => {
		let $router

		beforeEach(() => {
			$router = { push: jest.fn() }
		})

		/**
		 * @param {string} actionName The name of the action to shallow
		 */
		function shallowMountAndGetAction(actionName) {
			const store = new Vuex.Store(testStoreConfig)
			const wrapper = shallowMount(Conversation, {
				global: {
					plugins: [store],
					mocks: {
						$router,
					},
					stubs: {
						NcActionButton,
						NcListItem: NcListItemStub,
					},
				},
				props: {
					isSearchResult: false,
					item,
				},
			})

			const el = wrapper.findComponent({ name: 'NcListItem' })
			expect(el.exists()).toBe(true)

			return findNcActionButton(el, actionName)
		}

		describe('leaving conversation', () => {
			test('leaves conversation', async () => {
				const actionHandler = jest.fn()
				testStoreConfig.modules.participantsStore.actions.removeCurrentUserFromConversation = actionHandler
				leaveConversation.mockResolvedValue()
				const action = shallowMountAndGetAction('Leave conversation')
				expect(action.exists()).toBe(true)

				await action.find('button').trigger('click')
				await flushPromises()

				expect(actionHandler).toHaveBeenCalledWith(expect.anything(), { token: TOKEN })
			})

			test('hides "leave conversation" action when not allowed', async () => {
				item.canLeaveConversation = false

				const action = shallowMountAndGetAction('Leave conversation')
				expect(action.exists()).toBe(false)
			})

			test('errors with notification when a new moderator is required before leaving', async () => {
				const actionHandler = jest.fn().mockRejectedValueOnce({
					response: {
						status: 400,
					},
				})
				testStoreConfig.modules.participantsStore.actions.removeCurrentUserFromConversation = actionHandler

				const action = shallowMountAndGetAction('Leave conversation')
				expect(action.exists()).toBe(true)

				action.find('button').trigger('click')
				await flushPromises()

				expect(actionHandler).toHaveBeenCalledWith(expect.anything(), { token: TOKEN })
				// expect(showError).toHaveBeenCalledWith(expect.stringContaining('promote'))
			})
		})

		describe('deleting conversation', () => {
			test('deletes conversation when confirmed', async () => {
				// Arrange
				const actionHandler = jest.fn().mockResolvedValueOnce()
				const updateTokenAction = jest.fn()
				testStoreConfig.modules.conversationsStore.actions.deleteConversationFromServer = actionHandler
				testStoreConfig.modules.tokenStore.getters.getToken = jest.fn().mockReturnValue(() => 'another-token')
				testStoreConfig.modules.tokenStore.actions.updateToken = updateTokenAction

				const store = new Vuex.Store(testStoreConfig)
				const wrapper = shallowMount(Conversation, {
					global: {
						plugins: [store],
						mocks: {
							$router,
						},
						stubs: {
							NcActionButton,
							NcDialog,
							NcButton,
							NcListItem: NcListItemStub,
						},
					},
					props: {
						isSearchResult: false,
						item,
					},
				})
				const el = wrapper.findComponent({ name: 'NcListItem' })

				const action = findNcActionButton(el, 'Delete conversation')
				expect(action.exists()).toBe(true)

				// Act 1 : click on the button from the menu
				await action.find('button').trigger('click')

				// Assert 1
				const dialog = wrapper.findComponent({ name: 'NcDialog' })
				expect(dialog.exists).toBeTruthy()
				const buttons = dialog.findAllComponents({ name: 'NcButton' })
				expect(buttons).toHaveLength(2)

				// Act 2 : click on the confirm button
				await buttons.at(1).find('button').trigger('click')

				// Assert 2
				expect(actionHandler).toHaveBeenCalledWith(expect.anything(), { token: TOKEN })
				expect($router.push).not.toHaveBeenCalled()
				expect(updateTokenAction).not.toHaveBeenCalled()
			})

			test('does not delete conversation when not confirmed', async () => {
				// Arrange
				const actionHandler = jest.fn().mockResolvedValueOnce()
				const updateTokenAction = jest.fn()
				testStoreConfig.modules.conversationsStore.actions.deleteConversationFromServer = actionHandler
				testStoreConfig.modules.tokenStore.getters.getToken = jest.fn().mockReturnValue(() => 'another-token')
				testStoreConfig.modules.tokenStore.actions.updateToken = updateTokenAction

				const store = new Vuex.Store(testStoreConfig)
				const wrapper = shallowMount(Conversation, {
					global: {
						plugins: [store],
						mocks: {
							$router,
						},
						stubs: {
							NcActionButton,
							NcDialog,
							NcButton,
							NcListItem: NcListItemStub,
						},
					},
					props: {
						isSearchResult: false,
						item,
					},
				})
				const el = wrapper.findComponent({ name: 'NcListItem' })

				const action = findNcActionButton(el, 'Delete conversation')
				expect(action.exists()).toBe(true)

				// Act 1 : click on the button from the menu
				await action.find('button').trigger('click')

				// Assert 1
				const dialog = wrapper.findComponent({ name: 'NcDialog' })
				expect(dialog.exists).toBeTruthy()
				const buttons = dialog.findAllComponents({ name: 'NcButton' })
				expect(buttons).toHaveLength(2)

				// Act 2 : click on the confirm button
				await buttons.at(0).find('button').trigger('click')

				// Assert 2
				expect(actionHandler).not.toHaveBeenCalled()
				expect($router.push).not.toHaveBeenCalled()
				expect(updateTokenAction).not.toHaveBeenCalled()
			})

			test('hides "delete conversation" action when not allowed', async () => {
				item.canDeleteConversation = false

				const action = shallowMountAndGetAction('Delete conversation')
				expect(action.exists()).toBe(false)
			})
		})

		test('copies link conversation', async () => {
			const copyTextMock = jest.fn().mockResolvedValueOnce()
			const store = new Vuex.Store(testStoreConfig)
			const wrapper = shallowMount(Conversation, {
				global: {
					plugins: [store],
					stubs: {
						NcActionButton,
						NcListItem: NcListItemStub,
					},
				},
				props: {
					isSearchResult: false,
					item,
				},
			})

			Object.assign(navigator, {
				clipboard: {
					writeText: copyTextMock,
				},
			})

			const el = wrapper.findComponent({ name: 'NcListItem' })
			expect(el.exists()).toBe(true)

			const action = findNcActionButton(el, 'Copy link')
			expect(action.exists()).toBe(true)

			await action.find('button').trigger('click')

			await action.vm.$nextTick()

			expect(copyTextMock).toHaveBeenCalledWith('http://localhost/nc-webroot/call/XXTOKENXX')
			// expect(showSuccess).toHaveBeenCalled()
		})
		test('sets favorite', async () => {
			const toggleFavoriteAction = jest.fn().mockResolvedValueOnce()
			testStoreConfig.modules.conversationsStore.actions.toggleFavorite = toggleFavoriteAction
			const store = new Vuex.Store(testStoreConfig)
			const wrapper = shallowMount(Conversation, {
				global: {
					plugins: [store],
					stubs: {
						NcActionButton,
						NcListItem: NcListItemStub,
					},
				},
				props: {
					isSearchResult: false,
					item,
				},
			})

			const el = wrapper.findComponent({ name: 'NcListItem' })
			expect(el.exists()).toBe(true)

			const action = findNcActionButton(el, 'Add to favorites')
			expect(action.exists()).toBe(true)

			expect(findNcActionButton(el, 'Remove from favorites').exists()).toBe(false)

			await action.find('button').trigger('click')

			expect(toggleFavoriteAction).toHaveBeenCalledWith(expect.anything(), item)
		})

		test('unsets favorite', async () => {
			const toggleFavoriteAction = jest.fn().mockResolvedValueOnce()
			testStoreConfig.modules.conversationsStore.actions.toggleFavorite = toggleFavoriteAction

			item.isFavorite = true

			const store = new Vuex.Store(testStoreConfig)
			const wrapper = shallowMount(Conversation, {
				global: {
					plugins: [store],
					stubs: {
						NcActionButton,
						NcListItem: NcListItemStub,
					},
				},
				props: {
					isSearchResult: false,
					item,
				},
			})

			const el = wrapper.findComponent({ name: 'NcListItem' })
			expect(el.exists()).toBe(true)

			const action = findNcActionButton(el, 'Remove from favorites')
			expect(action.exists()).toBe(true)

			expect(findNcActionButton(el, 'Add to favorites').exists()).toBe(false)

			await action.find('button').trigger('click')

			expect(toggleFavoriteAction).toHaveBeenCalledWith(expect.anything(), item)
		})
		test('marks conversation as unread', async () => {
			const markConversationUnreadAction = jest.fn().mockResolvedValueOnce()
			testStoreConfig.modules.conversationsStore.actions.markConversationUnread = markConversationUnreadAction

			const action = shallowMountAndGetAction('Mark as unread')
			expect(action.exists()).toBe(true)

			await action.find('button').trigger('click')

			expect(markConversationUnreadAction).toHaveBeenCalledWith(expect.anything(), { token: item.token })
		})
		test('marks conversation as read', async () => {
			const clearLastReadMessageAction = jest.fn().mockResolvedValueOnce()
			testStoreConfig.modules.conversationsStore.actions.clearLastReadMessage = clearLastReadMessageAction

			item.unreadMessages = 1
			const action = shallowMountAndGetAction('Mark as read')
			expect(action.exists()).toBe(true)

			await action.find('button').trigger('click')

			expect(clearLastReadMessageAction).toHaveBeenCalledWith(expect.anything(), { token: item.token })
		})
		test('does not show all actions for search result (open conversations)', () => {
			const store = new Vuex.Store(testStoreConfig)
			const wrapper = shallowMount(Conversation, {
				global: {
					plugins: [store],
					stubs: {
						NcActionButton,
						NcListItem: NcListItemStub,
					},
				},
				props: {
					isSearchResult: true,
					item,
				},
			})

			const el = wrapper.findComponent({ name: 'NcListItem' })
			expect(el.exists()).toBe(true)

			const actionButtons = wrapper.findAllComponents(NcActionButton)
			expect(actionButtons.length).toBeTruthy()

			// Join conversation and Copy link actions are intended
			expect(findNcActionButton(el, 'Join conversation').exists()).toBe(true)
			expect(findNcActionButton(el, 'Copy link').exists()).toBe(true)

			// But not default conversation actions
			expect(findNcActionButton(el, 'Add to favorites').exists()).toBe(false)
			expect(findNcActionButton(el, 'Remove from favorites').exists()).toBe(false)
		})
	})
})
