/*
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { cloneDeep } from 'es-toolkit'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import { computed } from 'vue'
import { createStore } from 'vuex'
import MessageButtonsBar from './../MessageButtonsBar/MessageButtonsBar.vue'
import router from '../../../../../__mocks__/router.js'
import * as useMessageInfoModule from '../../../../../composables/useMessageInfo.ts'
import { ATTENDEE, CONVERSATION, MESSAGE, PARTICIPANT } from '../../../../../constants.ts'
import storeConfig from '../../../../../store/storeConfig.js'
import { useActorStore } from '../../../../../stores/actor.ts'
import { useIntegrationsStore } from '../../../../../stores/integrations.js'
import { useTokenStore } from '../../../../../stores/token.ts'
import { findNcActionButton, findNcButton } from '../../../../../test-helpers.js'

describe('MessageButtonsBar.vue', () => {
	const TOKEN = 'XXTOKENXX'
	let testStoreConfig
	let store
	let messageProps
	let injected
	let conversationProps
	let actorStore
	let tokenStore
	let useMessageInfoSpy

	beforeEach(() => {
		setActivePinia(createPinia())
		actorStore = useActorStore()
		tokenStore = useTokenStore()

		injected = {
			getMessagesListScroller: vi.fn(),
		}
		useMessageInfoSpy = vi.spyOn(useMessageInfoModule, 'useMessageInfo')

		conversationProps = {
			token: TOKEN,
			lastCommonReadMessage: 0,
			type: CONVERSATION.TYPE.GROUP,
			readOnly: CONVERSATION.STATE.READ_WRITE,
			permissions: PARTICIPANT.PERMISSIONS.CHAT,
		}

		testStoreConfig = cloneDeep(storeConfig)
		testStoreConfig.modules.conversationsStore.getters.conversation
			= vi.fn().mockReturnValue((token) => conversationProps)
		actorStore.actorType = ATTENDEE.ACTOR_TYPE.USERS
		actorStore.actorId = 'user-id-1'
		tokenStore.token = TOKEN

		messageProps = {
			previousMessageId: 100,
			message: {
				message: 'test message',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				actorId: 'user-id-1',
				actorDisplayName: 'user-display-name-1',
				messageParameters: {},
				id: 123,
				isReplyable: true,
				timestamp: new Date('2020-05-07 09:23:00').getTime() / 1000,
				token: TOKEN,
				systemMessage: '',
				messageType: MESSAGE.TYPE.COMMENT,
			},
			isActionMenuOpen: false,
			isEmojiPickerOpen: false,
			isReactionsMenuOpen: false,
			isForwarderOpen: false,
			canReact: true,
			readInfo: {
				showCommonReadIcon: true,
				showSentIcon: true,
				commonReadIconTitle: '',
				sentIconTitle: '',
			},
			isTranslationAvailable: false,
		}
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	const ComponentStub = {
		template: '<div><slot /></div>',
	}

	/**
	 * Shared function to mount component
	 */
	function mountMessageButtonsBar(props) {
		return mount(MessageButtonsBar, {
			global: {
				plugins: [router, store],
				stubs: {
					NcPopover: ComponentStub,
				},
				provide: injected,
			},
			props,
		})
	}

	describe('actions', () => {
		describe('reply action', () => {
			test('replies to message', async () => {
				store = createStore(testStoreConfig)

				const wrapper = mountMessageButtonsBar(messageProps)

				const replyButton = findNcButton(wrapper, 'Reply')
				expect(replyButton.exists()).toBe(true)
				expect(replyButton.isVisible()).toBe(true)
				await replyButton.trigger('click')

				expect(wrapper.emitted('reply')).toBeTruthy()
			})

			test('hides reply button when not replyable', async () => {
				messageProps.message.isReplyable = false
				store = createStore(testStoreConfig)

				const wrapper = mountMessageButtonsBar(messageProps)

				const replyButton = findNcButton(wrapper, 'Reply')
				expect(replyButton.exists()).toBe(false)
			})

			test('hides reply button when no chat permission', async () => {
				conversationProps.permissions = 0
				store = createStore(testStoreConfig)

				const wrapper = mountMessageButtonsBar(messageProps)

				const replyButton = findNcButton(wrapper, 'Reply')
				expect(replyButton.exists()).toBe(false)
			})
		})

		describe('private reply action', () => {
			test('creates a new conversation when replying to message privately', async () => {
				vi.spyOn(router, 'push')

				const createOneToOneConversation = vi.fn()
				testStoreConfig.modules.conversationsStore.actions.createOneToOneConversation = createOneToOneConversation
				store = createStore(testStoreConfig)

				messageProps.message.actorId = 'another-user'

				const wrapper = mountMessageButtonsBar(messageProps)

				const actionButton = findNcActionButton(wrapper, 'Reply privately')
				expect(actionButton.exists()).toBe(true)

				createOneToOneConversation.mockResolvedValueOnce({
					token: 'new-token',
				})

				await actionButton.find('button').trigger('click')

				expect(createOneToOneConversation).toHaveBeenCalledWith(expect.anything(), 'another-user')

				expect(router.push).toHaveBeenCalledWith({
					name: 'conversation',
					params: {
						token: 'new-token',
					},
				})
			})

			/**
			 * @param {boolean} visible Whether or not the reply-private action is visible
			 */
			function testPrivateReplyActionVisible(visible) {
				store = createStore(testStoreConfig)

				const wrapper = mountMessageButtonsBar(messageProps)

				const actionButton = findNcActionButton(wrapper, 'Reply privately')
				expect(actionButton.exists()).toBe(visible)
			}

			test('hides private reply action for own messages', async () => {
				useMessageInfoSpy.mockReturnValue({
					isCurrentUserOwnMessage: computed(() => true),
				})
				// using default message props which have the
				// actor id set to the current user
				testPrivateReplyActionVisible(false)
			})

			test('hides private reply action for one to one conversation type', async () => {
				messageProps.message.actorId = 'another-user'
				conversationProps.type = CONVERSATION.TYPE.ONE_TO_ONE
				testPrivateReplyActionVisible(false)
			})

			test('hides private reply action for guest messages', async () => {
				messageProps.message.actorId = 'guest-user'
				messageProps.message.actorType = ATTENDEE.ACTOR_TYPE.GUESTS
				testPrivateReplyActionVisible(false)
			})

			test('hides private reply action when current user is a guest', async () => {
				messageProps.message.actorId = 'another-user'
				actorStore.actorType = ATTENDEE.ACTOR_TYPE.GUESTS
				testPrivateReplyActionVisible(false)
			})
		})

		describe('delete action', () => {
			test('emits delete event', async () => {
				// need to mock the date to be within 6h
				vi.useFakeTimers().setSystemTime(new Date('2020-05-07T10:00:00'))

				useMessageInfoSpy.mockReturnValue({
					isDeleteable: computed(() => true),
				})
				const wrapper = mountMessageButtonsBar(messageProps)

				const actionButton = findNcActionButton(wrapper, 'Delete')
				expect(actionButton.exists()).toBe(true)

				await actionButton.find('button').trigger('click')

				expect(wrapper.emitted().delete).toBeTruthy()

				vi.useRealTimers()
			})

			/**
			 * @param {boolean} visible Whether or not the delete action is visible
			 */
			function testDeleteMessageVisible(visible) {
				const wrapper = mountMessageButtonsBar(messageProps)

				const actionButton = findNcActionButton(wrapper, 'Delete')
				expect(actionButton.exists()).toBe(visible)
			}

			test('hides delete action when it cannot be deleted', async () => {
				useMessageInfoSpy.mockReturnValue({
					isDeleteable: computed(() => false),
				})
				testDeleteMessageVisible(false)
			})

			test('show delete action when it can be deleted', () => {
				useMessageInfoSpy.mockReturnValue({
					isDeleteable: computed(() => true),
				})
				testDeleteMessageVisible(true)
			})
		})

		test('marks message as unread', async () => {
			const updateLastReadMessageAction = vi.fn().mockResolvedValueOnce()
			const fetchConversationAction = vi.fn().mockResolvedValueOnce()
			testStoreConfig.modules.messagesStore.actions.updateLastReadMessage = updateLastReadMessageAction
			testStoreConfig.modules.conversationsStore.actions.fetchConversation = fetchConversationAction
			store = createStore(testStoreConfig)

			messageProps.previousMessageId = 100

			// appears even with more restrictive conditions
			conversationProps.readOnly = CONVERSATION.STATE.READ_ONLY
			messageProps.message.actorId = 'another-user'

			const wrapper = mountMessageButtonsBar(messageProps)

			const actionButton = findNcActionButton(wrapper, 'Mark as unread')
			expect(actionButton.exists()).toBe(true)

			await actionButton.find('button').trigger('click')
			// needs two updates...
			await wrapper.vm.$nextTick()
			await wrapper.vm.$nextTick()

			expect(updateLastReadMessageAction).toHaveBeenCalledWith(expect.anything(), {
				token: TOKEN,
				id: 100,
				updateVisually: true,
			})
		})

		test('copies message link', async () => {
			const copyTextMock = vi.fn()

			// appears even with more restrictive conditions
			conversationProps.readOnly = CONVERSATION.STATE.READ_ONLY
			messageProps.message.actorId = 'another-user'

			const wrapper = mountMessageButtonsBar(messageProps)

			Object.assign(navigator, {
				clipboard: {
					writeText: copyTextMock,
				},
			})

			const actionButton = findNcActionButton(wrapper, 'Copy message link')
			expect(actionButton.exists()).toBe(true)

			await actionButton.find('button').trigger('click')

			expect(copyTextMock).toHaveBeenCalledWith('http://localhost/nc-webroot/call/XXTOKENXX#message_123')
		})

		test('renders clickable custom actions', async () => {
			const handler = vi.fn()
			const handler2 = vi.fn()
			const actionsGetterMock = [
				{ label: 'first action', icon: 'some-icon', callback: handler },
				{ label: 'second action', icon: 'some-icon2', callback: handler2 },
			]
			const integrationsStore = useIntegrationsStore()
			actionsGetterMock.forEach((action) => integrationsStore.addMessageAction(action))
			testStoreConfig.modules.messagesStore.getters.message = vi.fn(() => () => messageProps)
			store = createStore(testStoreConfig)
			const wrapper = mountMessageButtonsBar(messageProps)

			const actionButton = findNcActionButton(wrapper, 'first action')
			expect(actionButton.exists()).toBeTruthy()
			await actionButton.find('button').trigger('click')

			expect(handler).toHaveBeenCalledWith({
				apiVersion: 'v3',
				message: messageProps.message,
				metadata: conversationProps,
			})

			const actionButton2 = findNcActionButton(wrapper, 'second action')
			expect(actionButton2.exists()).toBeTruthy()
			await actionButton2.find('button').trigger('click')

			expect(handler2).toHaveBeenCalledWith({
				apiVersion: 'v3',
				message: messageProps.message,
				metadata: conversationProps,
			})
		})
	})
})
