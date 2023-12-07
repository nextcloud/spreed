import { createLocalVue, mount, shallowMount } from '@vue/test-utils'
import flushPromises from 'flush-promises' // TODO fix after migration to @vue/test-utils v2.0.0
import { cloneDeep } from 'lodash'
import { createPinia, setActivePinia } from 'pinia'
import vOutsideEvents from 'vue-outside-events'
import Vuex, { Store } from 'vuex'

import Check from 'vue-material-design-icons/Check.vue'
import CheckAll from 'vue-material-design-icons/CheckAll.vue'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmojiPicker from '@nextcloud/vue/dist/Components/NcEmojiPicker.js'

import Message from './Message.vue'
import MessageButtonsBar from './MessageButtonsBar/MessageButtonsBar.vue'
import DeckCard from './MessagePart/DeckCard.vue'
import DefaultParameter from './MessagePart/DefaultParameter.vue'
import FilePreview from './MessagePart/FilePreview.vue'
import Location from './MessagePart/Location.vue'
import Mention from './MessagePart/Mention.vue'
import Quote from '../../../Quote.vue'

import * as useIsInCallModule from '../../../../composables/useIsInCall.js'
import { CONVERSATION, ATTENDEE, PARTICIPANT } from '../../../../constants.js'
import { EventBus } from '../../../../services/EventBus.js'
import storeConfig from '../../../../store/storeConfig.js'

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

const NcPopoverStub = {
	template: '<slot name="trigger" /><slot/>',
}

describe('Message.vue', () => {
	const TOKEN = 'XXTOKENXX'
	let localVue
	let testStoreConfig
	let store
	let messageProps
	let conversationProps
	let injected
	let getActorTypeMock
	const getVisualLastReadMessageIdMock = jest.fn()

	beforeEach(() => {
		localVue = createLocalVue()
		localVue.use(vOutsideEvents)
		localVue.use(Vuex)
		setActivePinia(createPinia())

		conversationProps = {
			token: TOKEN,
			lastCommonReadMessage: 0,
			type: CONVERSATION.TYPE.GROUP,
			readOnly: CONVERSATION.STATE.READ_WRITE,
			permissions: PARTICIPANT.PERMISSIONS.MAX_DEFAULT,
		}

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

		testStoreConfig = cloneDeep(storeConfig)
		testStoreConfig.modules.tokenStore.getters.getToken
			= jest.fn().mockReturnValue(() => TOKEN)
		testStoreConfig.modules.conversationsStore.getters.conversation
			= jest.fn().mockReturnValue((token) => conversationProps)
		testStoreConfig.modules.actorStore.getters.getActorId
			= jest.fn().mockReturnValue(() => 'user-id-1')
		testStoreConfig.modules.messagesStore.getters.getVisualLastReadMessageId
			= jest.fn().mockReturnValue(getVisualLastReadMessageIdMock)
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
			store = new Store(testStoreConfig)
		})

		test('renders rich text message', async () => {
			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
				provide: injected,
			})

			const message = wrapper.findComponent({ name: 'NcRichText' })
			expect(message.attributes('text')).toBe('test message')
		})

		test('renders emoji as single plain text', async () => {
			messageProps.isSingleEmoji = true
			messageProps.message = '🌧️'
			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
				provide: injected,
			})

			const emoji = wrapper.find('.message-body__main__text')
			expect(emoji.text()).toBe('🌧️')

			const message = wrapper.findComponent({ name: 'NcRichText' })
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
				store = new Store(testStoreConfig)
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
					provide: injected,
				})

				const richText = wrapper.findComponent({ name: 'NcRichText' })
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
					provide: injected,
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
					provide: injected,
				})

				const callButton = wrapper.findComponent({ name: 'CallButton' })
				expect(callButton.exists()).toBe(false)
			})

			test('does not show join call button when self is in call', () => {
				messageProps.id = 2
				messageProps.systemMessage = 'call_started'
				messageProps.message = 'message two'
				conversationProps.hasCall = true

				jest.spyOn(useIsInCallModule, 'useIsInCall').mockReturnValue(() => true)

				const wrapper = shallowMount(Message, {
					localVue,
					store,
					propsData: messageProps,
					provide: injected,
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
				provide: injected,
				mixins: [{
					// mock the isInCall mixin
					computed: {
						isInCall: () => true,
					},
				}],
			})

			const richText = wrapper.findComponent({ name: 'NcRichText' })
			expect(richText.attributes('text')).toBe('message deleted')
		})

		test('renders date', () => {
			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
				provide: injected,
			})

			const date = wrapper.find('.date')
			expect(date.exists()).toBe(true)
			expect(date.text()).toBe('9:23 AM')
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
				reactions: '',
			}
			messageProps.parent = parentMessage

			const messageGetterMock = jest.fn().mockReturnValue(parentMessage)
			testStoreConfig.modules.messagesStore.getters.message = jest.fn(() => messageGetterMock)
			store = new Store(testStoreConfig)

			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
				provide: injected,
			})

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
					provide: injected,
				})

				const messageEl = wrapper.findComponent({ name: 'NcRichText' })
				// note: indices as object keys are on purpose
				expect(messageEl.props('arguments')).toMatchObject(expectedRichParameters)
				return messageEl
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

			test('renders single file preview', () => {
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

			test('renders single file preview with caption', () => {
				const caption = 'text caption'
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
				const messageEl = renderRichObject(
					caption,
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

				expect(messageEl.props('text')).toBe('{file}' + '\n\n' + caption)
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
			getVisualLastReadMessageIdMock.mockReturnValue(123)
			messageProps.nextMessageId = 333
			const observeVisibility = jest.fn()

			const wrapper = shallowMount(Message, {
				localVue,
				store,
				directives: {
					observeVisibility,
				},
				propsData: messageProps,
				provide: injected,
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
				provide: injected,
			})

			const marker = wrapper.find('.new-message-marker')
			expect(marker.exists()).toBe(false)
		})
	})

	describe('actions', () => {

		beforeEach(() => {
			store = new Store(testStoreConfig)
		})

		test('does not render actions for system messages are available', async () => {
			messageProps.systemMessage = 'this is a system message'

			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
				provide: injected,
			})

			await wrapper.find('.message').trigger('mouseover')
			expect(wrapper.findComponent(MessageButtonsBar).exists()).toBe(false)
		})

		test('does not render actions for temporary messages', async () => {
			messageProps.isTemporary = true

			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
				provide: injected,
			})

			await wrapper.find('.message').trigger('mouseover')
			expect(wrapper.findComponent(MessageButtonsBar).exists()).toBe(false)
		})

		test('does not render actions for deleted messages', async () => {
			messageProps.messageType = 'comment_deleted'

			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
				provide: injected,
			})

			await wrapper.find('.message').trigger('mouseover')
			expect(wrapper.findComponent(MessageButtonsBar).exists()).toBe(false)
		})

		test('Buttons bar is rendered on mouse over', async () => {
			messageProps.sendingFailure = 'timeout'
			const wrapper = mount(Message, {
				localVue,
				store,
				propsData: messageProps,
				provide: injected,
			})

			// Initial state
			expect(wrapper.findComponent(MessageButtonsBar).exists()).toBe(false)

			// Mouseover
			await wrapper.find('.message').trigger('mouseover')
			expect(wrapper.findComponent(MessageButtonsBar).exists()).toBe(true)

			// Actions are rendered with MessageButtonsBar
			expect(wrapper.findComponent({ name: 'NcActions' }).exists()).toBe(true)

			// Mouseleave
			await wrapper.find('.message').trigger('mouseleave')
			expect(wrapper.findComponent(MessageButtonsBar).exists()).toBe(false)
		})
	})

	describe('delete action', () => {
		test('deletes message', async () => {
			let resolveDeleteMessage
			const deleteMessage = jest.fn().mockReturnValue(new Promise((resolve, reject) => { resolveDeleteMessage = resolve }))
			testStoreConfig.modules.messagesStore.actions.deleteMessage = deleteMessage
			store = new Store(testStoreConfig)

			// need to mock the date to be within 6h
			const mockDate = new Date('2020-05-07 10:00:00')
			jest.spyOn(global.Date, 'now')
				.mockImplementation(() => mockDate)

			const wrapper = mount(Message, {
				localVue,
				store,
				stubs: {
					NcActionButton,
					MessageButtonsBar,
				},
				propsData: messageProps,
				provide: injected,
			})

			// Hover the messages in order to render the MessageButtonsBar component
			await wrapper.find('.message').trigger('mouseover')
			expect(wrapper.findComponent(MessageButtonsBar).exists()).toBe(true)

			wrapper.findComponent(MessageButtonsBar).vm.$emit('delete')

			expect(deleteMessage).toHaveBeenCalledWith(expect.anything(), {
				message: {
					token: TOKEN,
					id: 123,
				},
				placeholder: expect.anything(),
			})

			await flushPromises()
			expect(wrapper.vm.isDeleting).toBe(true)
			expect(wrapper.find('.icon-loading-small').exists()).toBe(true)

			resolveDeleteMessage(200)
			await flushPromises()

			expect(wrapper.vm.isDeleting).toBe(false)
			expect(wrapper.find('.icon-loading-small').exists()).toBe(false)
		})
	})

	describe('status', () => {
		beforeEach(() => {
			store = new Store(testStoreConfig)
		})

		test('lets user retry sending a timed out message', async () => {
			messageProps.sendingFailure = 'timeout'
			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
				provide: injected,
			})

			await wrapper.find('.message-body').trigger('mouseover')
			expect(wrapper.findComponent(MessageButtonsBar).exists()).toBe(true)

			const reloadButton = wrapper.find('.sending-failed')
			expect(reloadButton.exists()).toBe(true)

			await reloadButton.trigger('mouseover')

			expect(wrapper.vm.showReloadButton).toBe(true)

			const reloadNcButton = wrapper.findComponent(NcButton)
			expect(reloadNcButton.exists()).toBe(true)

			const retryEvent = jest.fn()
			EventBus.$on('retry-message', retryEvent)

			await reloadNcButton.vm.$emit('click')

			expect(retryEvent).toHaveBeenCalledWith(123)
		})

		test('displays the message already with a spinner while sending it', () => {
			messageProps.isTemporary = true
			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
				provide: injected,
			})

			const message = wrapper.findComponent({ name: 'NcRichText' })
			expect(message.attributes('text')).toBe('test message')

			expect(wrapper.find('.icon-loading-small').exists()).toBe(true)
		})

		test('displays icon when message was read by everyone', () => {
			conversationProps.lastCommonReadMessage = 123
			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
				provide: injected,
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
				provide: injected,
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
				provide: injected,
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

	describe('reactions', () => {
		beforeEach(() => {
			messageProps.reactions = { '❤️': 1, '👍': 7 }
			messageProps.reactionsSelf = ['👍']
			store = new Store(testStoreConfig)
		})

		test('shows reaction buttons with count and emoji picker', () => {
			// Arrange
			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
				provide: injected,
				stubs: {
					NcPopover: NcPopoverStub,
				},
			})

			// Assert
			const reactionButtons = wrapper.findAllComponents({ name: 'NcButton' })
			expect(reactionButtons).toHaveLength(3) // 2 for reactions and 1 for emoji picker
			expect(reactionButtons.at(0).text()).toBe('❤️ 1')
			expect(reactionButtons.at(1).text()).toBe('👍 7')
		})

		test('shows reaction buttons with count but without emoji picker when no chat permission', () => {
			// Arrange
			const conversationProps = {
				token: TOKEN,
				lastCommonReadMessage: 0,
				type: CONVERSATION.TYPE.GROUP,
				readOnly: CONVERSATION.STATE.READ_WRITE,
				permissions: PARTICIPANT.PERMISSIONS.MAX_DEFAULT - PARTICIPANT.PERMISSIONS.CHAT,
			}
			testStoreConfig.modules.conversationsStore.getters.conversation
				= jest.fn().mockReturnValue((token) => conversationProps)
			store = new Store(testStoreConfig)

			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
				provide: injected,
				stubs: {
					NcPopover: NcPopoverStub,
				},
			})

			// Assert
			const reactionButtons = wrapper.findAllComponents({ name: 'NcButton' })
			expect(reactionButtons).toHaveLength(2) // 2 for reactions
			expect(reactionButtons.at(0).text()).toBe('❤️ 1')
			expect(reactionButtons.at(1).text()).toBe('👍 7')
		})

		test('doesn\'t mount emoji picker when there are no reactions', () => {
			// Arrange
			messageProps.reactions = { }
			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
				provide: injected,
				stubs: {
					NcEmojiPicker,
				},
			})

			// Assert
			const reactionButtons = wrapper.findAllComponents({ name: 'NcButton' })
			expect(reactionButtons).toHaveLength(0)
			const emojiPicker = wrapper.findComponent(NcEmojiPicker)
			expect(emojiPicker.exists()).toBeFalsy()
			expect(emojiPicker.vm).toBeUndefined()
		})

		test('dispatches store actions upon picking an emoji from the emojipicker', () => {
			// Arrange
			const addReactionToMessageAction = jest.fn()
			const removeReactionFromMessageAction = jest.fn()
			testStoreConfig.modules.messagesStore.actions.addReactionToMessage = addReactionToMessageAction
			testStoreConfig.modules.messagesStore.actions.removeReactionFromMessage = removeReactionFromMessageAction
			store = new Store(testStoreConfig)

			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
				provide: injected,
				stubs: {
					NcEmojiPicker,
				},
				mixins: [{
					computed: {
						showMessageButtonsBar: () => {
							return true
						},
					},
				}],
			})

			// Act
			const emojiPicker = wrapper.findComponent(NcEmojiPicker)
			emojiPicker.vm.$emit('select', '❤️')
			emojiPicker.vm.$emit('select', '👍')

			// Assert
			expect(addReactionToMessageAction).toHaveBeenCalledWith(expect.anything(), {
				token: messageProps.token,
				messageId: messageProps.id,
				selectedEmoji: '❤️',
				actorId: messageProps.actorId,
			})
			expect(removeReactionFromMessageAction).toHaveBeenCalledWith(expect.anything(), {
				token: messageProps.token,
				messageId: messageProps.id,
				selectedEmoji: '👍',
				actorId: messageProps.actorId,
			})
		})

		test('dispatches store actions upon clicking a reaction buttons', () => {
			// Arrange
			const addReactionToMessageAction = jest.fn()
			const removeReactionFromMessageAction = jest.fn()
			testStoreConfig.modules.messagesStore.actions.addReactionToMessage = addReactionToMessageAction
			testStoreConfig.modules.messagesStore.actions.removeReactionFromMessage = removeReactionFromMessageAction
			store = new Store(testStoreConfig)

			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
				provide: injected,
				stubs: {
					NcPopover: NcPopoverStub,
				},
			})

			// Act
			const reactionButtons = wrapper.findAllComponents({ name: 'NcButton' })
			reactionButtons.at(0).vm.$emit('click') // ❤️
			reactionButtons.at(1).vm.$emit('click') // 👍

			// Assert
			expect(addReactionToMessageAction).toHaveBeenCalledWith(expect.anything(), {
				token: messageProps.token,
				messageId: messageProps.id,
				selectedEmoji: '❤️',
				actorId: messageProps.actorId,
			})
			expect(removeReactionFromMessageAction).toHaveBeenCalledWith(expect.anything(), {
				token: messageProps.token,
				messageId: messageProps.id,
				selectedEmoji: '👍',
				actorId: messageProps.actorId,
			})
		})
	})
})
