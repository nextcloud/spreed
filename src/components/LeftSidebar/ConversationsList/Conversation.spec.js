/*
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showError, showSuccess } from '@nextcloud/dialogs'
import { flushPromises, mount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import { createStore } from 'vuex'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import IconFileOutline from 'vue-material-design-icons/FileOutline.vue'
import ConversationIcon from '../../ConversationIcon.vue'
import Conversation from './Conversation.vue'
import router from '../../../__mocks__/router.js'
import { ATTENDEE, CONVERSATION, PARTICIPANT } from '../../../constants.ts'
import { leaveConversation } from '../../../services/participantsService.js'
import storeConfig from '../../../store/storeConfig.js'
import { findNcActionButton, findNcButton } from '../../../test-helpers.js'

vi.mock('../../../services/participantsService', () => ({
	leaveConversation: vi.fn(),
}))

const ComponentStub = {
	template: '<div><slot /></div>',
}

describe('Conversation.vue', () => {
	const TOKEN = 'XXTOKENXX'
	let store
	let testStoreConfig
	let item
	let messagesMock

	/**
	 * Shared function to mount component
	 */
	function mountConversation(isSearchResult = false) {
		return mount(Conversation, {
			global: {
				plugins: [router, store],
				stubs: {
					NcModal: ComponentStub,
					NcPopover: ComponentStub,
				},
			},

			props: {
				isSearchResult,
				item,
			},
		})
	}

	beforeEach(() => {
		testStoreConfig = cloneDeep(storeConfig)
		messagesMock = vi.fn().mockReturnValue({})
		testStoreConfig.modules.messagesStore.getters.messages = () => messagesMock
		store = createStore(testStoreConfig)

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
			isArchived: false,
			isSensitive: false,
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
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	test('renders conversation entry', () => {
		const wrapper = mountConversation(false)

		const el = wrapper.findComponent(NcListItem)
		expect(el.exists()).toBe(true)
		expect(el.props('name')).toBe('conversation one')

		const icon = el.findComponent(ConversationIcon)
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
		async function testConversationLabel(item, expectedText, isSearchResult = false) {
			const wrapper = mountConversation(isSearchResult)
			await flushPromises()

			const el = wrapper.find('.conversation__subname')
			expect(el.exists()).toBeTruthy()
			expect(el.text()).toMatch(expectedText)
			return wrapper
		}

		test('display joining conversation message when not joined yet', async () => {
			item.actorId = null
			await testConversationLabel(item, 'Joining conversation …')
		})

		test('displays nothing when there is no last chat message', async () => {
			delete item.lastMessage
			await testConversationLabel(item, 'No messages')
		})

		describe('author name', () => {
			// items are padded from each other visually
			test('displays last chat message with shortened author name', async () => {
				await testConversationLabel(item, 'Alice:hello')
			})

			test('displays last chat message with author name if no space in name', async () => {
				item.lastMessage.actorDisplayName = 'Bob'
				await testConversationLabel(item, 'Bob:hello')
			})

			test('displays own last chat message with "You" as author', async () => {
				item.lastMessage.actorId = 'actor-id-1'

				await testConversationLabel(item, 'You:hello')
			})

			test('displays last system message without author', async () => {
				item.lastMessage.message = 'Alice has joined the call'
				item.lastMessage.systemMessage = 'call_joined'

				await testConversationLabel(item, 'Alice has joined the call')
			})

			test('displays last message without author in one to one conversations', async () => {
				item.type = CONVERSATION.TYPE.ONE_TO_ONE
				await testConversationLabel(item, 'hello')
			})

			test('displays own last message with "You" author in one to one conversations', async () => {
				item.type = CONVERSATION.TYPE.ONE_TO_ONE
				item.lastMessage.actorId = 'actor-id-1'

				await testConversationLabel(item, 'You:hello')
			})

			test('displays last guest message with default author when none set', async () => {
				item.type = CONVERSATION.TYPE.PUBLIC
				item.lastMessage.actorDisplayName = ''
				item.lastMessage.actorType = ATTENDEE.ACTOR_TYPE.GUESTS

				await testConversationLabel(item, 'Guest:hello')
			})

			test('displays description for search results', async () => {
				// search results have no actor id
				item.actorId = null
				item.description = 'This is a description'
				await testConversationLabel(item, 'This is a description', true)
			})
		})

		test('replaces placeholders in rich object of last message', async () => {
			item.lastMessage.message = '{file}'
			item.lastMessage.messageParameters = {
				file: {
					name: 'filename.jpg',
				},
			}
			const wrapper = await testConversationLabel(item, 'Alice:filename.jpg')
			expect(wrapper.findComponent(IconFileOutline).exists()).toBeTruthy()
		})

		test('hides subname for sensitive conversations', () => {
			item.isSensitive = true

			const wrapper = mountConversation(false)

			const el = wrapper.find('.conversation__subname')
			expect(el.exists()).toBe(false)
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
			const wrapper = mountConversation(false)

			const el = wrapper.findComponent(NcListItem)
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
			const wrapper = mountConversation(false)

			const el = wrapper.findComponent(NcListItem)
			expect(el.exists()).toBe(true)

			expect(el.vm.$slots.counter).not.toBeDefined()
		})
	})

	describe('actions and routing', () => {
		test('change route on click event', async () => {
			await router.isReady()
			const wrapper = mountConversation(false)

			const el = wrapper.findComponent(NcListItem)
			expect(el.exists()).toBe(true)

			await el.find('a').trigger('click')
			await flushPromises()

			expect(wrapper.vm.$route.name).toBe('conversation')
			expect(wrapper.vm.$route.params).toStrictEqual({ token: TOKEN })
		})

		/**
		 * @param {string} actionName The name of the action to shallow
		 */
		function shallowMountAndGetAction(actionName) {
			store = createStore(testStoreConfig)
			const wrapper = mountConversation(false)

			const el = wrapper.findComponent(NcListItem)
			expect(el.exists()).toBe(true)

			return findNcActionButton(el, actionName)
		}

		/**
		 * @param {string} actionName The name of the action to shallow
		 * @param {number} buttonsAmount The amount of buttons to be shown in dialog
		 */
		async function shallowMountAndOpenDialog(actionName, buttonsAmount) {
			const wrapper = mountConversation(false)
			const el = wrapper.findComponent(NcListItem)

			const action = findNcActionButton(el, actionName)
			expect(action.exists()).toBeTruthy()

			// Act 1 : click on the button from the menu
			await action.find('button').trigger('click')

			// Assert 1
			const dialog = wrapper.findComponent(NcDialog)
			expect(dialog.exists).toBeTruthy()
			const buttons = dialog.findAllComponents(NcButton)
			expect(buttons).toHaveLength(buttonsAmount)

			return dialog
		}

		describe('leaving conversation', () => {
			let actionHandler

			beforeEach(() => {
				leaveConversation.mockResolvedValue()
				actionHandler = vi.fn().mockResolvedValueOnce()
				testStoreConfig.modules.participantsStore.actions.removeCurrentUserFromConversation = actionHandler
				testStoreConfig.modules.conversationsStore.actions.toggleArchive = actionHandler
				store = createStore(testStoreConfig)
			})

			test('leaves conversation when confirmed', async () => {
				// Arrange
				const dialog = await shallowMountAndOpenDialog('Leave conversation', 3)

				// Act: click on the 'confirm' button
				await findNcButton(dialog, 'Yes').find('button').trigger('click')
				await flushPromises()
				// Assert
				expect(actionHandler).toHaveBeenCalledWith(expect.anything(), { token: TOKEN })
			})

			test('hides "leave conversation" action when not allowed', async () => {
				item.canLeaveConversation = false

				const action = shallowMountAndGetAction('Leave conversation')
				expect(action.exists()).toBe(false)
			})

			test('errors with notification when a new moderator is required before leaving', async () => {
				// Arrange
				actionHandler = vi.fn().mockRejectedValueOnce({ response: { status: 400 } })
				testStoreConfig.modules.participantsStore.actions.removeCurrentUserFromConversation = actionHandler
				store = createStore(testStoreConfig)

				const dialog = await shallowMountAndOpenDialog('Leave conversation', 3)

				// Act: click on the 'confirm' button
				await findNcButton(dialog, 'Yes').find('button').trigger('click')
				await flushPromises()

				// Assert
				expect(actionHandler).toHaveBeenCalledWith(expect.anything(), { token: TOKEN })
				expect(showError).toHaveBeenCalledWith(expect.stringContaining('promote'))
			})

			test('does not leave conversation when not confirmed', async () => {
				// Arrange
				const dialog = await shallowMountAndOpenDialog('Leave conversation', 3)

				// Act: click on the 'decline' button
				await findNcButton(dialog, 'No').find('button').trigger('click')

				// Assert
				expect(actionHandler).not.toHaveBeenCalled()
			})

			test('archives conversation when selected', async () => {
				// Arrange
				const dialog = await shallowMountAndOpenDialog('Leave conversation', 3)

				// Act: click on the 'archive' button
				await findNcButton(dialog, 'Archive conversation').find('button').trigger('click')

				// Assert
				expect(actionHandler).toHaveBeenCalledWith(expect.anything(), item)
			})
		})

		describe('deleting conversation', () => {
			let actionHandler

			beforeEach(() => {
				vi.spyOn(router, 'push')
				actionHandler = vi.fn().mockResolvedValueOnce()
				testStoreConfig.modules.conversationsStore.actions.deleteConversationFromServer = actionHandler
				store = createStore(testStoreConfig)
			})

			test('deletes conversation when confirmed', async () => {
				// Arrange
				const dialog = await shallowMountAndOpenDialog('Delete conversation', 2)

				// Act: click on the 'confirm' button
				await findNcButton(dialog, 'Yes').find('button').trigger('click')
				await flushPromises()

				// Assert
				expect(actionHandler).toHaveBeenCalledWith(expect.anything(), { token: TOKEN })
				expect(router.push).not.toHaveBeenCalled()
			})

			test('does not delete conversation when not confirmed', async () => {
				// Arrange
				const dialog = await shallowMountAndOpenDialog('Delete conversation', 2)

				// Act: click on the 'decline' button
				await findNcButton(dialog, 'No').find('button').trigger('click')
				await flushPromises()

				// Assert
				expect(actionHandler).not.toHaveBeenCalled()
				expect(router.push).not.toHaveBeenCalled()
			})

			test('hides "delete conversation" action when not allowed', async () => {
				item.canDeleteConversation = false

				const action = shallowMountAndGetAction('Delete conversation')
				expect(action.exists()).toBe(false)
			})
		})

		test('copies link conversation', async () => {
			store = createStore(testStoreConfig)
			const copyTextMock = vi.fn().mockResolvedValueOnce()
			const wrapper = mountConversation(false)

			Object.assign(navigator, {
				clipboard: {
					writeText: copyTextMock,
				},
			})

			const el = wrapper.findComponent(NcListItem)
			expect(el.exists()).toBe(true)

			const action = findNcActionButton(el, 'Copy link')
			expect(action.exists()).toBe(true)

			await action.find('button').trigger('click')

			await action.vm.$nextTick()

			expect(copyTextMock).toHaveBeenCalledWith('http://localhost/nc-webroot/call/XXTOKENXX')
			expect(showSuccess).toHaveBeenCalled()
		})
		test('sets favorite', async () => {
			const toggleFavoriteAction = vi.fn().mockResolvedValueOnce()
			testStoreConfig.modules.conversationsStore.actions.toggleFavorite = toggleFavoriteAction
			store = createStore(testStoreConfig)

			const wrapper = mountConversation(false)

			const el = wrapper.findComponent(NcListItem)
			expect(el.exists()).toBe(true)

			const action = findNcActionButton(el, 'Add to favorites')
			expect(action.exists()).toBe(true)

			expect(findNcActionButton(el, 'Remove from favorites').exists()).toBe(false)

			await action.find('button').trigger('click')

			expect(toggleFavoriteAction).toHaveBeenCalledWith(expect.anything(), item)
		})

		test('unsets favorite', async () => {
			const toggleFavoriteAction = vi.fn().mockResolvedValueOnce()
			testStoreConfig.modules.conversationsStore.actions.toggleFavorite = toggleFavoriteAction

			item.isFavorite = true
			store = createStore(testStoreConfig)

			const wrapper = mountConversation(false)

			const el = wrapper.findComponent(NcListItem)
			expect(el.exists()).toBe(true)

			const action = findNcActionButton(el, 'Remove from favorites')
			expect(action.exists()).toBe(true)

			expect(findNcActionButton(el, 'Add to favorites').exists()).toBe(false)

			await action.find('button').trigger('click')

			expect(toggleFavoriteAction).toHaveBeenCalledWith(expect.anything(), item)
		})
		test('marks conversation as unread', async () => {
			const markConversationUnreadAction = vi.fn().mockResolvedValueOnce()
			testStoreConfig.modules.conversationsStore.actions.markConversationUnread = markConversationUnreadAction

			const action = shallowMountAndGetAction('Mark as unread')
			expect(action.exists()).toBe(true)

			await action.find('button').trigger('click')

			expect(markConversationUnreadAction).toHaveBeenCalledWith(expect.anything(), { token: item.token })
		})
		test('marks conversation as read', async () => {
			const clearLastReadMessageAction = vi.fn().mockResolvedValueOnce()
			testStoreConfig.modules.conversationsStore.actions.clearLastReadMessage = clearLastReadMessageAction

			item.unreadMessages = 1
			const action = shallowMountAndGetAction('Mark as read')
			expect(action.exists()).toBe(true)

			await action.find('button').trigger('click')

			expect(clearLastReadMessageAction).toHaveBeenCalledWith(expect.anything(), { token: item.token })
		})
		test('does not show all actions for search result (open conversations)', () => {
			store = createStore(testStoreConfig)
			const wrapper = mountConversation(true)

			const el = wrapper.findComponent(NcListItem)
			expect(el.exists()).toBe(true)

			// Join conversation and Copy link actions are intended
			expect(findNcActionButton(el, 'Join conversation').exists()).toBe(true)
			expect(findNcActionButton(el, 'Copy link').exists()).toBe(true)

			// But not default conversation actions
			expect(findNcActionButton(el, 'Add to favorites').exists()).toBe(false)
			expect(findNcActionButton(el, 'Remove from favorites').exists()).toBe(false)
		})
	})
})
