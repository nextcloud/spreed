import { createLocalVue, shallowMount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import vOutsideEvents from 'vue-outside-events'
import Vuex, { Store } from 'vuex'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import MessageButtonsBar from './../MessageButtonsBar/MessageButtonsBar.vue'

import { CONVERSATION, PARTICIPANT, ATTENDEE } from '../../../../../constants.js'
import storeConfig from '../../../../../store/storeConfig.js'
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

	beforeEach(() => {
		localVue = createLocalVue()
		localVue.use(vOutsideEvents)
		localVue.use(Vuex)

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
		testStoreConfig.modules.actorStore.getters.getActorType = getActorTypeMock

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
			isLastRead: false,
			isForwarderOpen: false,
			timestamp: new Date('2020-05-07 09:23:00').getTime() / 1000,
			token: TOKEN,
			systemMessage: '',
			messageType: 'comment',
			previousMessageId: 100,
			messageObject: {},
			messageApiData: {
				apiDummyData: 1,
			},
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

		beforeEach(() => {
			store = new Store(testStoreConfig)

			injected = {
				scrollerBoundingClientRect: {
					x: 0,
					y: 0,
					width: 0,
					height: 0,
					top: 0,
					right: 0,
					bottom: 0,
					left: 0,
				},
				getMessagesListScroller: jest.fn(),
			}
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
				testPrivateReplyActionVisible(false)
			})
		})

		describe('delete action', () => {
			test('emits delete event', async () => {
				// need to mock the date to be within 6h
				const mockDate = new Date('2020-05-07 10:00:00')
				jest.spyOn(global.Date, 'now')
					.mockImplementation(() => mockDate)
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
			 * @param {Date} mockDate The message date (deletion only works within 6h)
			 * @param {number} participantType The participant type of the user
			 */
			function testDeleteMessageVisible(visible, mockDate, participantType = PARTICIPANT.TYPE.USER) {
				store = new Store(testStoreConfig)

				// need to mock the date to be within 6h
				if (!mockDate) {
					mockDate = new Date('2020-05-07 10:00:00')
				}

				jest.spyOn(global.Date, 'now')
					.mockImplementation(() => mockDate)

				messageProps.participant.participantType = participantType

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

			test('hides delete action when message is older than 6 hours', () => {
				testDeleteMessageVisible(false, new Date('2020-05-07 15:24:00'))
			})

			test('hides delete action when the conversation is read-only', () => {
				conversationProps.readOnly = CONVERSATION.STATE.READ_ONLY
				testDeleteMessageVisible(false)
			})

			test('show delete action for file messages', () => {
				messageProps.message = '{file}'
				messageProps.messageParameters.file = {}
				testDeleteMessageVisible(true)
			})

			test('hides delete action on other people messages for non-moderators', () => {
				messageProps.actorId = 'another-user'
				conversationProps.type = CONVERSATION.TYPE.GROUP
				testDeleteMessageVisible(false)
			})

			test('shows delete action on other people messages for moderators', () => {
				messageProps.actorId = 'another-user'
				conversationProps.type = CONVERSATION.TYPE.GROUP
				testDeleteMessageVisible(true, null, PARTICIPANT.TYPE.MODERATOR)
			})

			test('shows delete action on other people messages for owner', () => {
				messageProps.actorId = 'another-user'
				conversationProps.type = CONVERSATION.TYPE.PUBLIC
				testDeleteMessageVisible(true, null, PARTICIPANT.TYPE.OWNER)
			})

			test('does not show delete action even for guest moderators', () => {
				messageProps.actorId = 'another-user'
				conversationProps.type = CONVERSATION.TYPE.PUBLIC
				testDeleteMessageVisible(false, null, PARTICIPANT.TYPE.GUEST_MODERATOR)
			})

			test('does not show delete action on other people messages in one to one conversations', () => {
				messageProps.actorId = 'another-user'
				conversationProps.type = CONVERSATION.TYPE.ONE_TO_ONE
				testDeleteMessageVisible(false)
			})
		})

		test('marks message as unread', async () => {
			const updateLastReadMessageAction = jest.fn().mockResolvedValueOnce()
			const fetchConversationAction = jest.fn().mockResolvedValueOnce()
			testStoreConfig.modules.conversationsStore.actions.updateLastReadMessage = updateLastReadMessageAction
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

			expect(fetchConversationAction).toHaveBeenCalledWith(expect.anything(), {
				token: TOKEN,
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
			const actionsGetterMock = jest.fn().mockReturnValue([{
				label: 'first action',
				icon: 'some-icon',
				callback: handler,
			}, {
				label: 'second action',
				icon: 'some-icon2',
				callback: handler2,
			}])
			testStoreConfig.modules.integrationsStore.getters.messageActions = actionsGetterMock
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
			expect(actionButton.exists()).toBe(true)
			await actionButton.find('button').trigger('click')

			expect(handler).toHaveBeenCalledWith({
				apiDummyData: 1,
			},)

			const actionButton2 = findNcActionButton(wrapper, 'second action')
			expect(actionButton2.exists()).toBe(true)
			await actionButton2.find('button').trigger('click')

			expect(handler2).toHaveBeenCalledWith({
				apiDummyData: 1,
			})
		})
	})
})
