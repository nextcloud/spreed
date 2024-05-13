/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createLocalVue, shallowMount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import { createPinia, setActivePinia } from 'pinia'
import Vuex, { Store } from 'vuex'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import MessageButtonsBar from './../MessageButtonsBar/MessageButtonsBar.vue'

import * as useMessageInfoModule from '../../../../../composables/useMessageInfo.js'
import { CONVERSATION, PARTICIPANT, ATTENDEE } from '../../../../../constants.js'
import storeConfig from '../../../../../store/storeConfig.js'
import { useIntegrationsStore } from '../../../../../stores/integrations.js'
import { findNcActionButton, findNcButton } from '../../../../../test-helpers.js'

describe('MessageButtonsBar.vue', () => {
	const TOKEN = 'XXTOKENXX'
	let localVue
	let testStoreConfig
	let store
	let messageProps
	let injected
	let conversationProps
	let getActorTypeMock
	let isActorUserMock
	let isActorGuestMock

	beforeEach(() => {
		localVue = createLocalVue()
		localVue.use(Vuex)
		setActivePinia(createPinia())

		conversationProps = {
			token: TOKEN,
			lastCommonReadMessage: 0,
			type: CONVERSATION.TYPE.GROUP,
			readOnly: CONVERSATION.STATE.READ_WRITE,
		}

		testStoreConfig = cloneDeep(storeConfig)
		testStoreConfig.modules.tokenStore.getters.getToken
			= jest.fn().mockReturnValue(() => TOKEN)
		testStoreConfig.modules.conversationsStore.getters.conversation
			= jest.fn().mockReturnValue((token) => conversationProps)
		testStoreConfig.modules.actorStore.getters.getActorId
			= jest.fn().mockReturnValue(() => 'user-id-1')
		getActorTypeMock = jest.fn().mockReturnValue(() => ATTENDEE.ACTOR_TYPE.USERS)
		isActorUserMock = jest.fn().mockReturnValue(() => true)
		isActorGuestMock = jest.fn().mockReturnValue(() => false)
		testStoreConfig.modules.actorStore.getters.getActorType = getActorTypeMock
		testStoreConfig.modules.actorStore.getters.isActorUser = isActorUserMock
		testStoreConfig.modules.actorStore.getters.isActorGuest = isActorGuestMock

		messageProps = {
			message: 'test message',
			actorType: ATTENDEE.ACTOR_TYPE.USERS,
			actorId: 'user-id-1',
			actorDisplayName: 'user-display-name-1',
			messageParameters: {},
			id: 123,
			isTemporary: false,
			isFirstMessage: true,
			isReplyable: true,
			isTranslationAvailable: false,
			canReact: true,
			isReactionsMenuOpen: false,
			isActionMenuOpen: false,
			isEmojiPickerOpen: false,
			isLastRead: false,
			isForwarderOpen: false,
			timestamp: new Date('2020-05-07 09:23:00').getTime() / 1000,
			token: TOKEN,
			systemMessage: '',
			messageType: 'comment',
			previousMessageId: 100,
			participant: {
				actorId: 'user-id-1',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				participantType: PARTICIPANT.TYPE.USER,
			},
			showCommonReadIcon: true,
			showSentIcon: true,
			commonReadIconTooltip: '',
			sentIconTooltip: '',
		}
	})

	afterEach(() => {
		jest.clearAllMocks()
	})

	describe('actions', () => {
		let useMessageInfoSpy

		beforeEach(() => {
			store = new Store(testStoreConfig)

			injected = {
				getMessagesListScroller: jest.fn(),
			}

			useMessageInfoSpy = jest.spyOn(useMessageInfoModule, 'useMessageInfo')
		})

		afterEach(() => {
			useMessageInfoSpy.mockRestore()
		})

		describe('reply action', () => {
			test('replies to message', async () => {
				store = new Store(testStoreConfig)

				const wrapper = shallowMount(MessageButtonsBar, {
					localVue,
					store,
					stubs: {
						NcActionButton,
						NcButton,
					},
					propsData: messageProps,
					provide: injected,
				})

				const replyButton = findNcButton(wrapper, 'Reply')
				expect(replyButton.exists()).toBe(true)
				expect(replyButton.isVisible()).toBe(true)
				await replyButton.trigger('click')

				expect(wrapper.emitted('reply')).toBeTruthy()
			})

			test('hides reply button when not replyable', async () => {
				messageProps.isReplyable = false
				store = new Store(testStoreConfig)

				const wrapper = shallowMount(MessageButtonsBar, {
					localVue,
					store,
					stubs: {
						NcActionButton,
						NcButton,
					},
					propsData: messageProps,
					provide: injected,
				})

				const replyButton = findNcButton(wrapper, 'Reply')
				expect(replyButton.exists()).toBe(false)
			})
		})

		describe('private reply action', () => {
			test('creates a new conversation when replying to message privately', async () => {
				const routerPushMock = jest.fn().mockResolvedValue()
				const createOneToOneConversation = jest.fn()
				testStoreConfig.modules.conversationsStore.actions.createOneToOneConversation = createOneToOneConversation
				store = new Store(testStoreConfig)

				messageProps.actorId = 'another-user'

				const wrapper = shallowMount(MessageButtonsBar, {
					localVue,
					store,
					mocks: {
						$router: {
							push: routerPushMock,
						},
					},
					stubs: {
						NcActionButton,
					},
					propsData: messageProps,
					provide: injected,
				})

				const actionButton = findNcActionButton(wrapper, 'Reply privately')
				expect(actionButton.exists()).toBe(true)

				createOneToOneConversation.mockResolvedValueOnce({
					token: 'new-token',
				})

				await actionButton.find('button').trigger('click')

				expect(createOneToOneConversation).toHaveBeenCalledWith(expect.anything(), 'another-user')

				expect(routerPushMock).toHaveBeenCalledWith({
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
				store = new Store(testStoreConfig)

				const wrapper = shallowMount(MessageButtonsBar, {
					localVue,
					store,
					stubs: {
						NcActionButton,
					},
					propsData: messageProps,
					provide: injected,
				})

				const actionButton = findNcActionButton(wrapper, 'Reply privately')
				expect(actionButton.exists()).toBe(visible)
			}

			test('hides private reply action for own messages', async () => {
				useMessageInfoSpy.mockReturnValue({
					isCurrentUserOwnMessage: () => true,
			   })
				// using default message props which have the
				// actor id set to the current user
				testPrivateReplyActionVisible(false)
			})

			test('hides private reply action for one to one conversation type', async () => {
				messageProps.actorId = 'another-user'
				conversationProps.type = CONVERSATION.TYPE.ONE_TO_ONE
				testPrivateReplyActionVisible(false)
			})

			test('hides private reply action for guest messages', async () => {
				messageProps.actorId = 'guest-user'
				messageProps.actorType = ATTENDEE.ACTOR_TYPE.GUESTS
				testPrivateReplyActionVisible(false)
			})

			test('hides private reply action when current user is a guest', async () => {
				messageProps.actorId = 'another-user'
				getActorTypeMock.mockClear().mockReturnValue(() => ATTENDEE.ACTOR_TYPE.GUESTS)
				isActorUserMock.mockClear().mockReturnValue(() => false)
				isActorGuestMock.mockClear().mockReturnValue(() => true)
				testPrivateReplyActionVisible(false)
			})
		})

		describe('delete action', () => {
			test('emits delete event', async () => {
				// need to mock the date to be within 6h
				const mockDate = new Date('2020-05-07 10:00:00')
				jest.spyOn(global.Date, 'now')
					.mockImplementation(() => mockDate)
				useMessageInfoSpy.mockReturnValue({
					isDeleteable: () => true,
				})
				const wrapper = shallowMount(MessageButtonsBar, {
					localVue,
					store,
					stubs: {
						NcActionButton,
					},
					propsData: messageProps,
					provide: injected,
				})

				const actionButton = findNcActionButton(wrapper, 'Delete')
				expect(actionButton.exists()).toBe(true)

				await actionButton.find('button').trigger('click')

				expect(wrapper.emitted().delete).toBeTruthy()
			})

			/**
			 * @param {boolean} visible Whether or not the delete action is visible
			 */
			function testDeleteMessageVisible(visible) {
				const wrapper = shallowMount(MessageButtonsBar, {
					localVue,
					store,
					stubs: {
						NcActionButton,
					},
					propsData: messageProps,
					provide: injected,
				})

				const actionButton = findNcActionButton(wrapper, 'Delete')
				expect(actionButton.exists()).toBe(visible)
			}

			test('hides delete action when it cannot be deleted', async () => {
				testDeleteMessageVisible(false)
			})

			test('show delete action when it can be deleted', () => {
				useMessageInfoSpy.mockReturnValue({
					isDeleteable: () => true,
				})
				testDeleteMessageVisible(true)
			})
		})

		test('marks message as unread', async () => {
			const updateLastReadMessageAction = jest.fn().mockResolvedValueOnce()
			const fetchConversationAction = jest.fn().mockResolvedValueOnce()
			testStoreConfig.modules.messagesStore.actions.updateLastReadMessage = updateLastReadMessageAction
			testStoreConfig.modules.conversationsStore.actions.fetchConversation = fetchConversationAction
			store = new Store(testStoreConfig)

			messageProps.previousMessageId = 100

			// appears even with more restrictive conditions
			conversationProps.readOnly = CONVERSATION.STATE.READ_ONLY
			messageProps.actorId = 'another-user'
			messageProps.participant = {
				actorId: 'guest-id-1',
				actorType: ATTENDEE.ACTOR_TYPE.GUESTS,
				participantType: PARTICIPANT.TYPE.GUEST,
			}

			const wrapper = shallowMount(MessageButtonsBar, {
				localVue,
				store,
				stubs: {
					NcActionButton,
				},

				propsData: messageProps,
				provide: injected,
			})

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
			const copyTextMock = jest.fn()

			// appears even with more restrictive conditions
			conversationProps.readOnly = CONVERSATION.STATE.READ_ONLY
			messageProps.actorId = 'another-user'
			messageProps.participant = {
				actorId: 'guest-id-1',
				actorType: ATTENDEE.ACTOR_TYPE.GUESTS,
				participantType: PARTICIPANT.TYPE.GUEST,
			}

			const wrapper = shallowMount(MessageButtonsBar, {
				localVue,
				store,
				stubs: {
					NcActionButton,
				},

				propsData: messageProps,
				provide: injected,
			})

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
			const handler = jest.fn()
			const handler2 = jest.fn()
			const actionsGetterMock = [
				{ label: 'first action', icon: 'some-icon', callback: handler },
				{ label: 'second action', icon: 'some-icon2', callback: handler2 },
			]
			const integrationsStore = useIntegrationsStore()
			actionsGetterMock.forEach(action => integrationsStore.addMessageAction(action))
			testStoreConfig.modules.messagesStore.getters.message = jest.fn(() => () => messageProps)
			store = new Store(testStoreConfig)
			const wrapper = shallowMount(MessageButtonsBar, {
				localVue,
				store,
				stubs: {
					NcActionButton,
				},
				propsData: messageProps,
				provide: injected,
			})

			const actionButton = findNcActionButton(wrapper, 'first action')
			expect(actionButton.exists()).toBeTruthy()
			await actionButton.find('button').trigger('click')

			expect(handler).toHaveBeenCalledWith({
				apiVersion: 'v3',
				message: messageProps,
				metadata: conversationProps,
			},)

			const actionButton2 = findNcActionButton(wrapper, 'second action')
			expect(actionButton2.exists()).toBeTruthy()
			await actionButton2.find('button').trigger('click')

			expect(handler2).toHaveBeenCalledWith({
				apiVersion: 'v3',
				message: messageProps,
				metadata: conversationProps,
			})
		})
	})
})
