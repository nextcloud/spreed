import Vuex from 'vuex'
import { createLocalVue, shallowMount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import { EventBus } from '../../../../services/EventBus'
import storeConfig from '../../../../store/storeConfig'
import { CONVERSATION, PARTICIPANT, ATTENDEE } from '../../../../constants'
import Check from 'vue-material-design-icons/Check'
import CheckAll from 'vue-material-design-icons/CheckAll'
import Quote from '../../../Quote'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Mention from './MessagePart/Mention'
import FilePreview from './MessagePart/FilePreview'
import DeckCard from './MessagePart/DeckCard'
import Location from './MessagePart/Location'
import DefaultParameter from './MessagePart/DefaultParameter'
import { findActionButton } from '../../../../test-helpers'

import Message from './Message'

// needed because of https://github.com/vuejs/vue-test-utils/issues/1507
const RichTextStub = {
	props: {
		text: {
			type: String,
		},
		arguments: {
			type: Object,
		},
	},
	template: '<div/>',
}

describe('Message.vue', () => {
	const TOKEN = 'XXTOKENXX'
	let localVue
	let testStoreConfig
	let store
	let messageProps
	let conversationProps
	let getActorTypeMock

	beforeEach(() => {
		localVue = createLocalVue()
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
			timestamp: new Date('2020-05-07 09:23:00').getTime() / 1000,
			token: TOKEN,
			systemMessage: '',
			messageType: 'comment',
		}
	})

	afterEach(() => {
		jest.clearAllMocks()
	})

	describe('message rendering', () => {
		beforeEach(() => {
			store = new Vuex.Store(testStoreConfig)
		})

		test('renders rich text message', async () => {
			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
			})

			const message = wrapper.findComponent({ name: 'RichText' })
			expect(message.attributes('text')).toBe('test message')
		})

		test('renders emoji as single plain text', async () => {
			messageProps.isSingleEmoji = true
			messageProps.message = '🌧️'
			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
			})

			const emoji = wrapper.find('.message-body__main__text')
			expect(emoji.text()).toBe('🌧️')

			const message = wrapper.findComponent({ name: 'RichText' })
			expect(message.exists()).toBe(false)
		})

		describe('call button', () => {
			beforeEach(() => {
				testStoreConfig.modules.messagesStore.getters.messagesList = jest.fn().mockReturnValue((token) => {
					return [{
						id: 1,
						systemMessage: 'call_started',
						message: 'message one',
					}, {
						id: 2,
						systemMessage: 'call_started',
						message: 'message two',
					}]
				})
				store = new Vuex.Store(testStoreConfig)
			})

			test('shows join call button on last message when a call is in progress', () => {
				messageProps.id = 2
				messageProps.systemMessage = 'call_started'
				messageProps.message = 'message two'
				conversationProps.hasCall = true

				const wrapper = shallowMount(Message, {
					localVue,
					store,
					propsData: messageProps,
				})

				const richText = wrapper.findComponent({ name: 'RichText' })
				expect(richText.attributes('text')).toBe('message two')

				const callButton = wrapper.findComponent({ name: 'CallButton' })
				expect(callButton.exists()).toBe(true)
			})

			test('does not show join call button on non-last message when a call is in progress', () => {
				messageProps.id = 1
				messageProps.systemMessage = 'call_started'
				messageProps.message = 'message one'
				conversationProps.hasCall = true

				const wrapper = shallowMount(Message, {
					localVue,
					store,
					propsData: messageProps,
				})

				const callButton = wrapper.findComponent({ name: 'CallButton' })
				expect(callButton.exists()).toBe(false)
			})

			test('does not show join call button when no call is in progress', () => {
				messageProps.id = 2
				messageProps.systemMessage = 'call_started'
				messageProps.message = 'message two'
				conversationProps.hasCall = false

				const wrapper = shallowMount(Message, {
					localVue,
					store,
					propsData: messageProps,
				})

				const callButton = wrapper.findComponent({ name: 'CallButton' })
				expect(callButton.exists()).toBe(false)
			})

			test('does not show join call button when self is in call', () => {
				messageProps.id = 2
				messageProps.systemMessage = 'call_started'
				messageProps.message = 'message two'
				conversationProps.hasCall = true

				const wrapper = shallowMount(Message, {
					localVue,
					store,
					propsData: messageProps,
					mixins: [{
						// mock the isInCall mixin
						computed: {
							isInCall: () => true,
						},
					}],
				})

				const callButton = wrapper.findComponent({ name: 'CallButton' })
				expect(callButton.exists()).toBe(false)
			})
		})

		test('renders deleted system message', () => {
			messageProps.systemMessage = 'comment_deleted'
			messageProps.message = 'message deleted'
			conversationProps.hasCall = true

			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
				mixins: [{
					// mock the isInCall mixin
					computed: {
						isInCall: () => true,
					},
				}],
			})

			const richText = wrapper.findComponent({ name: 'RichText' })
			expect(richText.attributes('text')).toBe('message deleted')
		})

		test('renders date', () => {
			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
			})

			const date = wrapper.find('.date')
			expect(date.exists()).toBe(true)
			expect(date.text()).toBe('09:23')
		})

		test('renders quote block', () => {
			const parentMessage = {
				id: 120,
				message: 'quoted text',
				actorId: 'another-user',
				actorDisplayName: 'anotherUser',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				messageParameters: {},
				token: TOKEN,
				parentId: -1,
			}
			messageProps.parent = 120

			const messageGetterMock = jest.fn().mockReturnValue(parentMessage)
			testStoreConfig.modules.messagesStore.getters.message = jest.fn(() => messageGetterMock)
			store = new Vuex.Store(testStoreConfig)

			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
			})

			// parent message got queried from the store
			expect(messageGetterMock).toHaveBeenCalledWith(TOKEN, 120)

			const quote = wrapper.findComponent(Quote)
			expect(quote.exists()).toBe(true)
			expect(quote.attributes('message')).toBe('quoted text')
		})

		describe('rich objects', () => {
			/**
			 * @param {object} message The rich-object-string message text
			 * @param {object} messageParameters The rich-object-string parameters
			 * @param {object} expectedRichParameters The expected Vue objects for the parameters
			 */
			function renderRichObject(message, messageParameters, expectedRichParameters) {
				messageProps.message = message
				messageProps.messageParameters = messageParameters
				const wrapper = shallowMount(Message, {
					localVue,
					store,
					stubs: {
						RichText: RichTextStub,
					},
					propsData: messageProps,
				})

				const messageEl = wrapper.findComponent({ name: 'RichText' })
				// note: indices as object keys are on purpose
				expect(messageEl.props('arguments')).toStrictEqual(expectedRichParameters)
			}

			test('renders mentions', () => {
				const mentions = {
					'mention-user1': {
						id: 'alice',
						name: 'Alice',
						type: 'user',
					},
					'mention-user2': {
						id: 'bob',
						name: 'Bob',
						type: 'guest',
					},
					'mention-call1': {
						id: 'some_call',
						type: 'call',
					},
				}
				renderRichObject(
					'hello {mention-user1}, {mention-user2} wants to have a {mention-call1} with you',
					mentions,
					{
						'mention-user1': {
							component: Mention,
							props: mentions['mention-user1'],
						},
						'mention-user2': {
							component: Mention,
							props: mentions['mention-user2'],
						},
						'mention-call1': {
							component: Mention,
							props: mentions['mention-call1'],
						},
					}
				)
			})

			test('renders file previews', () => {
				const params = {
					actor: {
						id: 'alice',
						name: 'Alice',
						type: 'user',
					},
					file: {
						path: 'some/path',
						type: 'file',
					},
				}
				renderRichObject(
					'{file}',
					params, {
						actor: {
							component: Mention,
							props: params.actor,
						},
						file: {
							component: FilePreview,
							props: params.file,
						},
					}
				)
			})

			test('renders deck cards', () => {
				const params = {
					actor: {
						id: 'alice',
						name: 'Alice',
						type: 'user',
					},
					'deck-card': {
						metadata: '{id:123}',
						type: 'deck-card',
					},
				}
				renderRichObject(
					'{deck-card}',
					params, {
						actor: {
							component: Mention,
							props: params.actor,
						},
						'deck-card': {
							component: DeckCard,
							props: params['deck-card'],
						},
					}
				)
			})

			test('renders geo locations', () => {
				const params = {
					'geo-location': {
						metadata: '{id:123}',
						type: 'geo-location',
					},
				}
				renderRichObject(
					'{geo-location}',
					params, {
						'geo-location': {
							component: Location,
							props: params['geo-location'],
						},
					}
				)
			})

			test('renders other rich objects', () => {
				const params = {
					actor: {
						id: 'alice',
						name: 'Alice',
						type: 'user',
					},
					unknown: {
						path: 'some/path',
						type: 'unknown',
					},
				}
				renderRichObject(
					'{unknown}',
					params, {
						actor: {
							component: Mention,
							props: params.actor,
						},
						unknown: {
							component: DefaultParameter,
							props: params.unknown,
						},
					}
				)
			})
		})

		test('displays unread message marker that marks the message seen when visible', () => {
			messageProps.lastReadMessageId = 123
			messageProps.nextMessageId = 333
			const observeVisibility = jest.fn()

			const wrapper = shallowMount(Message, {
				localVue,
				store,
				directives: {
					observeVisibility,
				},
				propsData: messageProps,
			})

			const marker = wrapper.find('.new-message-marker')
			expect(marker.exists()).toBe(true)

			expect(observeVisibility).toHaveBeenCalled()
			const directiveValue = observeVisibility.mock.calls[0][1]

			expect(wrapper.vm.seen).toEqual(false)

			directiveValue.value(false)
			expect(wrapper.vm.seen).toEqual(false)

			directiveValue.value(true)
			expect(wrapper.vm.seen).toEqual(true)

			// stays true if it was visible once
			directiveValue.value(false)
			expect(wrapper.vm.seen).toEqual(true)
		})

		test('does not display read marker on the very last message', () => {
			messageProps.lastReadMessageId = 123
			messageProps.nextMessageId = null // last message
			const observeVisibility = jest.fn()

			const wrapper = shallowMount(Message, {
				localVue,
				store,
				directives: {
					observeVisibility,
				},
				propsData: messageProps,
			})

			const marker = wrapper.find('.new-message-marker')
			expect(marker.exists()).toBe(false)
		})
	})

	describe('author rendering', () => {
		const AUTHOR_SELECTOR = '.message-body__author'
		beforeEach(() => {
			store = new Vuex.Store(testStoreConfig)
		})

		test('renders author if first message', async () => {
			messageProps.isFirstMessage = true
			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
			})

			const displayName = wrapper.find(AUTHOR_SELECTOR)
			expect(displayName.text()).toBe('user-display-name-1')
		})

		test('does not render author if not first message', async () => {
			messageProps.isFirstMessage = false
			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
			})

			const displayName = wrapper.find(AUTHOR_SELECTOR)
			expect(displayName.exists()).toBe(false)
		})
	})

	describe('actions', () => {
		const ACTIONS_SELECTOR = '.message__buttons-bar'

		beforeEach(() => {
			store = new Vuex.Store(testStoreConfig)
		})

		test('does not render actions for system messages are available', async () => {
			messageProps.systemMessage = 'this is a system message'

			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
			})

			await wrapper.vm.$nextTick()

			const actionsEl = wrapper.find(ACTIONS_SELECTOR)
			expect(actionsEl.exists()).toBe(false)
		})

		test('does not render actions for temporary messages', async () => {
			messageProps.isTemporary = true

			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
			})

			await wrapper.vm.$nextTick()

			const actionsEl = wrapper.find(ACTIONS_SELECTOR)
			expect(actionsEl.exists()).toBe(false)
		})

		test('actions become visible on mouse over', async () => {
			messageProps.sendingFailure = 'timeout'
			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
			})

			await wrapper.vm.$nextTick()

			const actionsEl = wrapper.find(ACTIONS_SELECTOR)

			expect(wrapper.vm.showActions).toBe(false)
			expect(actionsEl.isVisible()).toBe(false)

			await wrapper.find('.message-body').trigger('mouseover')

			expect(wrapper.vm.showActions).toBe(true)
			expect(actionsEl.isVisible()).toBe(true)

			await wrapper.find('.message-body').trigger('mouseleave')

			expect(wrapper.vm.showActions).toBe(false)
			expect(actionsEl.isVisible()).toBe(false)

			// actions are always present and rendered
			const actions = wrapper.findAllComponents({ name: 'Actions' })
			expect(actions.length).toBe(2)
		})

		describe('reply action', () => {
			test('replies to message', async () => {
				const replyAction = jest.fn()
				testStoreConfig.modules.quoteReplyStore.actions.addMessageToBeReplied = replyAction
				store = new Vuex.Store(testStoreConfig)

				const wrapper = shallowMount(Message, {
					localVue,
					store,
					stubs: {
						ActionButton,
					},
					propsData: messageProps,
				})

				await wrapper.find('.message-body').trigger('mouseover')
				const actionButton = findActionButton(wrapper, 'Reply')
				expect(actionButton.exists()).toBe(true)
				expect(actionButton.isVisible()).toBe(true)
				await actionButton.find('button').trigger('click')

				expect(replyAction).toHaveBeenCalledWith(expect.anything(), {
					id: 123,
					actorId: 'user-id-1',
					actorType: 'users',
					actorDisplayName: 'user-display-name-1',
					message: 'test message',
					messageParameters: {},
					messageType: 'comment',
					systemMessage: '',
					timestamp: new Date('2020-05-07 09:23:00').getTime() / 1000,
					token: TOKEN,
				})
			})

			test('hides reply button when not replyable', async () => {
				messageProps.isReplyable = false
				store = new Vuex.Store(testStoreConfig)

				const wrapper = shallowMount(Message, {
					localVue,
					store,
					stubs: {
						ActionButton,
					},
					propsData: messageProps,
				})

				const actionButton = findActionButton(wrapper, 'Reply')
				expect(actionButton.isVisible()).toBe(false)
			})
		})

		describe('private reply action', () => {
			test('creates a new conversation when replying to message privately', async () => {
				const routerPushMock = jest.fn().mockResolvedValue()
				const createOneToOneConversation = jest.fn()
				testStoreConfig.modules.conversationsStore.actions.createOneToOneConversation = createOneToOneConversation
				store = new Vuex.Store(testStoreConfig)

				messageProps.actorId = 'another-user'

				const wrapper = shallowMount(Message, {
					localVue,
					store,
					mocks: {
						$router: {
							push: routerPushMock,
						},
					},
					stubs: {
						ActionButton,
					},
					propsData: messageProps,
				})

				const actionButton = findActionButton(wrapper, 'Reply privately')
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
				store = new Vuex.Store(testStoreConfig)

				const wrapper = shallowMount(Message, {
					localVue,
					store,
					stubs: {
						ActionButton,
					},
					propsData: messageProps,
				})

				const actionButton = findActionButton(wrapper, 'Reply privately')
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
			test('deletes message', async () => {
				let resolveDeleteMessage
				const deleteMessage = jest.fn().mockReturnValue(new Promise((resolve, reject) => { resolveDeleteMessage = resolve }))
				testStoreConfig.modules.messagesStore.actions.deleteMessage = deleteMessage
				store = new Vuex.Store(testStoreConfig)

				// need to mock the date to be within 6h
				const mockDate = new Date('2020-05-07 10:00:00')
				jest.spyOn(global.Date, 'now')
					.mockImplementation(() => mockDate)

				const wrapper = shallowMount(Message, {
					localVue,
					store,
					stubs: {
						ActionButton,
					},
					propsData: messageProps,
				})

				const actionButton = findActionButton(wrapper, 'Delete')
				expect(actionButton.exists()).toBe(true)

				await actionButton.find('button').trigger('click')

				expect(deleteMessage).toHaveBeenCalledWith(expect.anything(), {
					message: {
						token: TOKEN,
						id: 123,
					},
					placeholder: expect.anything(),
				})

				await wrapper.vm.$nextTick()
				expect(wrapper.vm.isDeleting).toBe(true)
				expect(wrapper.find('.icon-loading-small').exists()).toBe(true)

				resolveDeleteMessage(200)
				// needs two updates...
				await wrapper.vm.$nextTick()
				await wrapper.vm.$nextTick()

				expect(wrapper.vm.isDeleting).toBe(false)
				expect(wrapper.find('.icon-loading-small').exists()).toBe(false)
			})

			/**
			 * @param {boolean} visible Whether or not the delete action is visible
			 * @param {Date} mockDate The message date (deletion only works within 6h)
			 * @param {number} participantType The participant type of the user
			 */
			function testDeleteMessageVisible(visible, mockDate, participantType = PARTICIPANT.TYPE.USER) {
				store = new Vuex.Store(testStoreConfig)

				// need to mock the date to be within 6h
				if (!mockDate) {
					mockDate = new Date('2020-05-07 10:00:00')
				}

				jest.spyOn(global.Date, 'now')
					.mockImplementation(() => mockDate)

				const wrapper = shallowMount(Message, {
					localVue,
					store,
					stubs: {
						ActionButton,
					},
					mixins: [{
						computed: {
							participant: () => {
								return {
									actorId: 'user-id-1',
									actorType: ATTENDEE.ACTOR_TYPE.USERS,
									participantType,
								}
							},
						},
					}],
					propsData: messageProps,
				})

				const actionButton = findActionButton(wrapper, 'Delete')
				expect(actionButton.exists()).toBe(visible)
			}

			test('hides delete action when message is older than 6 hours', () => {
				testDeleteMessageVisible(false, new Date('2020-05-07 15:24:00'))
			})

			test('hides delete action when the conversation is read-only', () => {
				conversationProps.readOnly = CONVERSATION.STATE.READ_ONLY
				testDeleteMessageVisible(false)
			})

			test('hides delete action for file messages', () => {
				messageProps.message = '{file}'
				messageProps.messageParameters.file = {}
				testDeleteMessageVisible(false)
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
			store = new Vuex.Store(testStoreConfig)

			messageProps.previousMessageId = 100

			// appears even with more restrictive conditions
			conversationProps.readOnly = CONVERSATION.STATE.READ_ONLY
			messageProps.actorId = 'another-user'

			const wrapper = shallowMount(Message, {
				localVue,
				store,
				stubs: {
					ActionButton,
				},
				mixins: [{
					computed: {
						participant: () => {
							return {
								actorId: 'guest-id-1',
								actorType: ATTENDEE.ACTOR_TYPE.GUESTS,
								participantType: PARTICIPANT.TYPE.GUEST,
							}
						},
					},
				}],
				propsData: messageProps,
			})

			const actionButton = findActionButton(wrapper, 'Mark as unread')
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

			const wrapper = shallowMount(Message, {
				localVue,
				store,
				mocks: {
					$copyText: copyTextMock,
				},
				stubs: {
					ActionButton,
				},
				mixins: [{
					computed: {
						participant: () => {
							return {
								actorId: 'guest-id-1',
								actorType: ATTENDEE.ACTOR_TYPE.GUESTS,
								participantType: PARTICIPANT.TYPE.GUEST,
							}
						},
					},
				}],
				propsData: messageProps,
			})

			const actionButton = findActionButton(wrapper, 'Copy message link')
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
			testStoreConfig.modules.messageActionsStore.getters.messageActions = actionsGetterMock
			testStoreConfig.modules.messagesStore.getters.message = jest.fn(() => () => messageProps)
			store = new Vuex.Store(testStoreConfig)
			const wrapper = shallowMount(Message, {
				localVue,
				store,
				stubs: {
					ActionButton,
				},
				propsData: messageProps,
			})

			const actionButton = findActionButton(wrapper, 'first action')
			expect(actionButton.exists()).toBe(true)
			await actionButton.find('button').trigger('click')

			expect(handler).toHaveBeenCalledWith({
				apiVersion: 'v3',
				message: messageProps,
				metadata: conversationProps,
			})

			const actionButton2 = findActionButton(wrapper, 'second action')
			expect(actionButton2.exists()).toBe(true)
			await actionButton2.find('button').trigger('click')

			expect(handler2).toHaveBeenCalledWith({
				apiVersion: 'v3',
				message: messageProps,
				metadata: conversationProps,
			})
		})
	})

	describe('status', () => {
		beforeEach(() => {
			store = new Vuex.Store(testStoreConfig)
		})

		test('lets user retry sending a timed out message', async () => {
			messageProps.sendingFailure = 'timeout'
			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
			})

			await wrapper.find('.message-body').trigger('mouseover')

			const reloadButton = wrapper.find('.sending-failed')
			expect(reloadButton.exists()).toBe(true)

			await reloadButton.trigger('mouseover')

			expect(wrapper.vm.showReloadButton).toBe(true)

			const reloadButtonIcon = reloadButton.find('button')
			expect(reloadButtonIcon.exists()).toBe(true)

			const retryEvent = jest.fn()
			EventBus.$on('retry-message', retryEvent)

			await reloadButtonIcon.trigger('click')

			expect(retryEvent).toHaveBeenCalledWith(123)
		})

		test('displays the message already with a spinner while sending it', () => {
			messageProps.isTemporary = true
			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
			})

			const message = wrapper.findComponent({ name: 'RichText' })
			expect(message.attributes('text')).toBe('test message')

			expect(wrapper.find('.icon-loading-small').exists()).toBe(true)
		})

		test('displays icon when message was read by everyone', () => {
			conversationProps.lastCommonReadMessage = 123
			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
				mixins: [{
					computed: {
						participant: () => {
							return {
								actorId: 'user-id-1',
								actorType: ATTENDEE.ACTOR_TYPE.USERS,
							}
						},
					},
				}],
			})

			expect(wrapper.findComponent(Check).exists()).toBe(false)
			expect(wrapper.findComponent(CheckAll).exists()).toBe(true)
		})

		test('displays sent icon when own message was sent', () => {
			conversationProps.lastCommonReadMessage = 0
			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
				mixins: [{
					computed: {
						participant: () => {
							return {
								actorId: 'user-id-1',
								actorType: ATTENDEE.ACTOR_TYPE.USERS,
							}
						},
					},
				}],
			})

			expect(wrapper.findComponent(Check).exists()).toBe(true)
			expect(wrapper.findComponent(CheckAll).exists()).toBe(false)
		})

		test('does not displays check icon for other people\'s messages', () => {
			conversationProps.lastCommonReadMessage = 123
			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
				mixins: [{
					computed: {
						participant: () => {
							return {
								actorId: 'user-id-2',
								actorType: ATTENDEE.ACTOR_TYPE.USERS,
							}
						},
					},
				}],
			})

			expect(wrapper.findComponent(Check).exists()).toBe(false)
			expect(wrapper.findComponent(CheckAll).exists()).toBe(false)
		})
	})
})
