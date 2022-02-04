import Vuex from 'vuex'
import { createLocalVue, mount, shallowMount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import { EventBus } from '../../../../services/EventBus'
import storeConfig from '../../../../store/storeConfig'
import { CONVERSATION, ATTENDEE } from '../../../../constants'
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
				return {}
			})
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

		test('actions become visible on mouse over', async () => {
			messageProps.sendingFailure = 'timeout'
			const wrapper = mount(Message, {
				localVue,
				store,
				propsData: messageProps,
			})

			await wrapper.vm.$nextTick()

			const messageButtonsBar = wrapper.findComponent(MessageButtonsBar)

			expect(wrapper.vm.showMessageButtonsBar).toBe(false)
			expect(messageButtonsBar.isVisible()).toBe(false)

			await wrapper.find('.message-body').trigger('mouseover')

			expect(wrapper.vm.showMessageButtonsBar).toBe(true)
			expect(messageButtonsBar.isVisible()).toBe(true)

			await wrapper.find('.message-body').trigger('mouseleave')

			expect(wrapper.vm.showMessageButtonsBar).toBe(false)
			expect(messageButtonsBar.isVisible()).toBe(false)

			// actions are always present and rendered
			const actions = wrapper.findAllComponents({ name: 'Actions' })
			expect(actions.length).toBe(2)
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
