import Vuex, { Store } from 'vuex'
import { createLocalVue, mount, shallowMount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import { EventBus } from '../../../../services/EventBus'
import storeConfig from '../../../../store/storeConfig'
import { CONVERSATION, ATTENDEE } from '../../../../constants'

// Components
import Check from 'vue-material-design-icons/Check'
import CheckAll from 'vue-material-design-icons/CheckAll'
import Quote from '../../../Quote'
import Mention from './MessagePart/Mention'
import FilePreview from './MessagePart/FilePreview'
import DeckCard from './MessagePart/DeckCard'
import Location from './MessagePart/Location'
import DefaultParameter from './MessagePart/DefaultParameter'
import MessageButtonsBar from './MessageButtonsBar/MessageButtonsBar.vue'
import Message from './Message'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import EmojiPicker from '@nextcloud/vue/dist/Components/EmojiPicker'

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

		// Dummy message getter so that the message component is always
		// properly mounted.
		testStoreConfig.modules.messagesStore.getters.message
			= jest.fn().mockReturnValue(() => {
				return {
					reactions: '',
				}
			})

		// Dummy hasReactions getter so that the message component is always
		// properly mounted.
		testStoreConfig.modules.messagesStore.getters.hasReactions
			= jest.fn().mockReturnValue(() => false)
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
				reactions: '',
			}
			messageProps.parent = 120

			const messageGetterMock = jest.fn().mockReturnValue(parentMessage)
			testStoreConfig.modules.messagesStore.getters.message = jest.fn(() => messageGetterMock)
			store = new Store(testStoreConfig)

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
			store = new Store(testStoreConfig)
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

		beforeEach(() => {
			store = new Store(testStoreConfig)
		})

		test('does not render actions for system messages are available', async () => {
			messageProps.systemMessage = 'this is a system message'

			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
			})

			await wrapper.vm.$nextTick()

			const messageButtonsBar = wrapper.findComponent(MessageButtonsBar)
			expect(messageButtonsBar.exists()).toBe(false)
		})

		test('does not render actions for temporary messages', async () => {
			messageProps.isTemporary = true

			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
			})

			await wrapper.vm.$nextTick()

			const messageButtonsBar = wrapper.findComponent(MessageButtonsBar)
			expect(messageButtonsBar.exists()).toBe(false)
		})

		test('Buttons bar is rendered on mouse over', async () => {
			messageProps.sendingFailure = 'timeout'
			const wrapper = mount(Message, {
				localVue,
				store,
				propsData: messageProps,
			})

			// Initial state
			expect(wrapper.vm.showMessageButtonsBar).toBe(false)
			expect(wrapper.findComponent(MessageButtonsBar).exists()).toBe(false)

			// Mouseover
			await wrapper.find('.message').trigger('mouseover')
			expect(wrapper.vm.showMessageButtonsBar).toBe(true)
			expect(wrapper.findComponent(MessageButtonsBar).exists()).toBe(true)

			// actions are present and rendered when the buttonsBar is renderend
			const actions = wrapper.findAllComponents({ name: 'Actions' })
			expect(actions.length).toBe(2)

			// Mouseleave
			await wrapper.find('.message').trigger('mouseleave')
			expect(wrapper.vm.showMessageButtonsBar).toBe(false)
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
					ActionButton,
					MessageButtonsBar,
				},
				propsData: messageProps,
			})

			// Hover the messages in order to render the MessageButtonsBar
			// component
			await wrapper.find('.message').trigger('mouseover')
			wrapper.findComponent(MessageButtonsBar).vm.$emit('delete')

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

	describe('reactions', () => {
		beforeEach(() => {
			testStoreConfig.modules.messagesStore.getters.message
				= jest.fn().mockReturnValue(() => {
					return {
						reactions: {
							'❤️': 1,
							'👍': 7,
						},
						id: messageProps.id,
					}
				})
			testStoreConfig.modules.messagesStore.getters.hasReactions
				= jest.fn().mockReturnValue(() => {
					return true
				})
			store = new Store(testStoreConfig)
		})

		test('properly shows reactions', () => {
			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
			})

			const reactionsBar = wrapper.find('.message-body__reactions')
			expect(reactionsBar.isVisible()).toBe(true)

		})

		test('shows reaction buttons with the right emoji count', () => {
			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
			})

			const reactionsBar = wrapper.find('.message-body__reactions')

			// Array of buttons
			const reactionButtons = reactionsBar.findAll('.reaction-button')

			// Number of buttons, 2 passed into the getter and 1 is the emoji
			// picker
			expect(reactionButtons.length).toBe(3)

			// Text of the buttons
			expect(reactionButtons.wrappers[0].text()).toBe('❤️  1')
			expect(reactionButtons.wrappers[1].text()).toBe('👍  7')
		})

		test('dispatches store action upon picking an emoji from the emojipicker', () => {
			const addReactionToMessageAction = jest.fn()
			const userHasReactedGetter = jest.fn().mockReturnValue(() => false)
			testStoreConfig.modules.quoteReplyStore.actions.addReactionToMessage = addReactionToMessageAction
			testStoreConfig.modules.messagesStore.getters.userHasReacted = userHasReactedGetter

			store = new Store(testStoreConfig)

			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
				stubs: {
					EmojiPicker,
				},
				data() {
					return {
						detailedReactionsRequested: true,
					}
				},
			})

			const emojiPicker = wrapper.findComponent(EmojiPicker)

			emojiPicker.vm.$emit('select', '❤️')

			expect(addReactionToMessageAction).toHaveBeenCalledWith(expect.anything(), {
				token: messageProps.token,
				messageId: messageProps.id,
				selectedEmoji: '❤️',
				actorId: messageProps.actorId,
			})

		})

		test('dispatches store action to remove an emoji upon clicking reaction button', async () => {
			const removeReactionFromMessageAction = jest.fn()
			const userHasReactedGetter = jest.fn().mockReturnValue(() => true)
			testStoreConfig.modules.quoteReplyStore.actions.removeReactionFromMessage = removeReactionFromMessageAction
			testStoreConfig.modules.messagesStore.getters.userHasReacted = userHasReactedGetter

			store = new Store(testStoreConfig)

			const wrapper = shallowMount(Message, {
				localVue,
				store,
				propsData: messageProps,
				data() {
					return {
						detailedReactionsRequested: true,
					}
				},
			})

			// Click reaction button upon having already reacted
			await wrapper.find('.reaction-button').trigger('click')

			await wrapper.vm.$nextTick()
			await wrapper.vm.$nextTick()

			expect(removeReactionFromMessageAction).toHaveBeenCalledWith(expect.anything(), {
				token: messageProps.token,
				messageId: messageProps.id,
				selectedEmoji: '❤️',
				actorId: messageProps.actorId,
			})

		})
	})
})
