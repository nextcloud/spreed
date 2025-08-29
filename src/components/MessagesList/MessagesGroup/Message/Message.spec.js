/*
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { flushPromises, mount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import { createStore } from 'vuex'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconCheckAll from 'vue-material-design-icons/CheckAll.vue'
import Quote from '../../../Quote.vue'
import CallButton from '../../../TopBar/CallButton.vue'
import Message from './Message.vue'
import MessageButtonsBar from './MessageButtonsBar/MessageButtonsBar.vue'
import DeckCard from './MessagePart/DeckCard.vue'
import DefaultParameter from './MessagePart/DefaultParameter.vue'
import FilePreview from './MessagePart/FilePreview.vue'
import Location from './MessagePart/Location.vue'
import Mention from './MessagePart/Mention.vue'
import router from '../../../../__mocks__/router.js'
import * as useIsInCallModule from '../../../../composables/useIsInCall.js'
import { ATTENDEE, CONVERSATION, MESSAGE, PARTICIPANT } from '../../../../constants.ts'
import { EventBus } from '../../../../services/EventBus.ts'
import storeConfig from '../../../../store/storeConfig.js'
import { useActorStore } from '../../../../stores/actor.ts'
import { useTokenStore } from '../../../../stores/token.ts'

describe('Message.vue', () => {
	const TOKEN = 'XXTOKENXX'
	let testStoreConfig
	let store
	let messageProps
	let conversationProps
	let injected
	const getVisualLastReadMessageIdMock = vi.fn()

	let actorStore
	let tokenStore

	beforeEach(() => {
		setActivePinia(createPinia())
		actorStore = useActorStore()
		tokenStore = useTokenStore()

		conversationProps = {
			token: TOKEN,
			lastCommonReadMessage: 0,
			type: CONVERSATION.TYPE.GROUP,
			readOnly: CONVERSATION.STATE.READ_WRITE,
			permissions: PARTICIPANT.PERMISSIONS.MAX_DEFAULT,
		}

		injected = {
			getMessagesListScroller: vi.fn(),
		}

		actorStore.actorId = 'user-id-1'
		actorStore.actorType = ATTENDEE.ACTOR_TYPE.USERS
		tokenStore.token = TOKEN
		testStoreConfig = cloneDeep(storeConfig)
		testStoreConfig.modules.conversationsStore.getters.conversation
			= vi.fn().mockReturnValue((token) => conversationProps)
		testStoreConfig.modules.messagesStore.getters.getVisualLastReadMessageId
			= vi.fn().mockReturnValue(getVisualLastReadMessageIdMock)

		messageProps = {
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
				reactions: [],
			},
		}
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	/**
	 * Shared function to mount component
	 */
	function mountMessage(props) {
		return mount(Message, {
			global: {
				plugins: [router, store],
				provide: injected,
				stubs: {
					Location: true,
				},
			},
			props,
		})
	}

	describe('message rendering', () => {
		beforeEach(() => {
			store = createStore(testStoreConfig)
		})

		test('renders rich text message', async () => {
			const wrapper = mountMessage(messageProps)

			const message = wrapper.findComponent(NcRichText)
			expect(message.text()).toBe('test message')
		})

		test('renders emoji as single plain text', async () => {
			messageProps.isSingleEmoji = true
			messageProps.message.message = '🌧️'
			const wrapper = mountMessage(messageProps)

			const message = wrapper.findComponent(NcRichText)
			expect(message.exists()).toBeTruthy()
			expect(message.text()).toBe('🌧️')
		})

		describe('call button', () => {
			beforeEach(() => {
				testStoreConfig.modules.messagesStore.getters.messagesList = vi.fn().mockReturnValue((token) => {
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
				store = createStore(testStoreConfig)
			})

			test('shows join call button on last message when a call is in progress', () => {
				messageProps.message.id = 2
				messageProps.message.systemMessage = 'call_started'
				messageProps.message.message = 'message two'
				conversationProps.hasCall = true

				const wrapper = mountMessage(messageProps)

				const richText = wrapper.findComponent(NcRichText)
				expect(richText.text()).toBe('message two')

				const callButton = wrapper.findComponent(CallButton)
				expect(callButton.exists()).toBe(true)
			})

			test('does not show join call button on non-last message when a call is in progress', () => {
				messageProps.message.id = 1
				messageProps.message.systemMessage = 'call_started'
				messageProps.message.message = 'message one'
				conversationProps.hasCall = true

				const wrapper = mountMessage(messageProps)

				const callButton = wrapper.findComponent(CallButton)
				expect(callButton.exists()).toBe(false)
			})

			test('does not show join call button when no call is in progress', () => {
				messageProps.message.id = 2
				messageProps.message.systemMessage = 'call_started'
				messageProps.message.message = 'message two'
				conversationProps.hasCall = false

				const wrapper = mountMessage(messageProps)

				const callButton = wrapper.findComponent(CallButton)
				expect(callButton.exists()).toBe(false)
			})

			test('does not show join call button when self is in call', () => {
				messageProps.message.id = 2
				messageProps.message.systemMessage = 'call_started'
				messageProps.message.message = 'message two'
				conversationProps.hasCall = true

				vi.spyOn(useIsInCallModule, 'useIsInCall').mockReturnValue(() => true)

				const wrapper = mountMessage(messageProps)

				const callButton = wrapper.findComponent(CallButton)
				expect(callButton.exists()).toBe(false)
			})
		})

		test('renders deleted system message', () => {
			messageProps.message.systemMessage = 'message_deleted'
			messageProps.message.message = 'message deleted'
			conversationProps.hasCall = true

			const wrapper = mountMessage(messageProps)

			const richText = wrapper.findComponent(NcRichText)
			expect(richText.text()).toBe('message deleted')
		})

		test('renders date', () => {
			const wrapper = mountMessage(messageProps)

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
			messageProps.message.parent = parentMessage

			const messageGetterMock = vi.fn().mockReturnValue(parentMessage)
			testStoreConfig.modules.messagesStore.getters.message = vi.fn(() => messageGetterMock)
			store = createStore(testStoreConfig)

			const wrapper = mountMessage(messageProps)

			const quote = wrapper.findComponent(Quote)
			expect(quote.exists()).toBeTruthy()
			expect(quote.props('message')).toStrictEqual(parentMessage)
		})

		describe('rich objects', () => {
			/**
			 * @param {object} message The rich-object-string message text
			 * @param {object} messageParameters The rich-object-string parameters
			 * @param {object} expectedRichParameters The expected Vue objects for the parameters
			 */
			function renderRichObject(message, messageParameters, expectedRichParameters) {
				messageProps.message.message = message
				messageProps.message.messageParameters = messageParameters
				store.dispatch('processMessage', { token: TOKEN, message: messageProps.message })
				const wrapper = mountMessage(messageProps)

				const messageEl = wrapper.findComponent(NcRichText)
				// note: indices as object keys are on purpose
				expect(Object.keys(messageEl.props('arguments'))).toMatchObject(Object.keys(expectedRichParameters))
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
						name: 'Some call',
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
					},
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
						id: '123',
						path: 'Talk/some-path.txt',
						name: 'some-path.txt',
						type: 'file',
						mimetype: 'txt/plain',
					},
				}
				renderRichObject(
					'{file}',
					params,
					{
						actor: {
							component: Mention,
							props: params.actor,
						},
						file: {
							component: FilePreview,
							props: { file: params.file },
						},
					},
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
						id: '123',
						path: 'Talk/some-path.txt',
						name: 'some-path.txt',
						type: 'file',
						mimetype: 'txt/plain',
					},
				}
				const messageEl = renderRichObject(
					caption,
					params,
					{
						actor: {
							component: Mention,
							props: params.actor,
						},
						file: {
							component: FilePreview,
							props: { file: params.file },
						},
					},
				)

				expect(messageEl.props('text')).toBe('{file}\n\n' + caption)
			})

			test('renders deck cards', () => {
				const params = {
					actor: {
						id: 'alice',
						name: 'Alice',
						type: 'user',
					},
					'deck-card': {
						id: '123',
						name: 'Card name',
						boardname: 'Board name',
						stackname: 'Stack name',
						link: 'https://example.com/some/deck/card/url',
						metadata: '{id:123}',
						type: 'deck-card',
					},
				}
				renderRichObject(
					'{deck-card}',
					params,
					{
						actor: {
							component: Mention,
							props: params.actor,
						},
						'deck-card': {
							component: DeckCard,
							props: params['deck-card'],
						},
					},
				)
			})

			test('renders geo locations', () => {
				const params = {
					'geo-location': {
						id: '123',
						name: 'Location name',
						latitude: 12.345678,
						longitude: 98.765432,
						metadata: '{id:123}',
						type: 'geo-location',
					},
				}
				renderRichObject(
					'{geo-location}',
					params,
					{
						'geo-location': {
							component: Location,
							props: params['geo-location'],
						},
					},
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
						id: '123',
						name: 'Unknown name',
						path: 'some/path',
						type: 'unknown',
					},
				}
				renderRichObject(
					'{unknown}',
					params,
					{
						actor: {
							component: Mention,
							props: params.actor,
						},
						unknown: {
							component: DefaultParameter,
							props: params.unknown,
						},
					},
				)
			})
		})

		test('does not display read marker on the very last message', () => {
			messageProps.lastReadMessageId = 123
			messageProps.nextMessageId = null // last message

			const wrapper = mountMessage(messageProps)

			const marker = wrapper.find('.message-unread-marker')
			expect(marker.exists()).toBe(false)
		})
	})

	describe('actions', () => {
		beforeEach(() => {
			store = createStore(testStoreConfig)
		})

		test('does not render actions for system messages are available', async () => {
			messageProps.message.systemMessage = 'this is a system message'

			const wrapper = mountMessage(messageProps)

			await wrapper.find('.message').trigger('mouseover')
			expect(wrapper.findComponent(MessageButtonsBar).exists()).toBe(false)
		})

		test('does not render actions for temporary messages', async () => {
			messageProps.message.timestamp = 0

			const wrapper = mountMessage(messageProps)

			await wrapper.find('.message').trigger('mouseover')
			expect(wrapper.findComponent(MessageButtonsBar).exists()).toBe(false)
		})

		test('does not render actions for deleted messages', async () => {
			messageProps.message.messageType = MESSAGE.TYPE.COMMENT_DELETED

			const wrapper = mountMessage(messageProps)

			await wrapper.find('.message').trigger('mouseover')
			expect(wrapper.findComponent(MessageButtonsBar).exists()).toBe(false)
		})

		test('Buttons bar is rendered on mouse over', async () => {
			messageProps.message.sendingFailure = 'timeout'
			const wrapper = mountMessage(messageProps)

			// Initial state
			expect(wrapper.findComponent(MessageButtonsBar).exists()).toBe(false)

			// Mouseover
			await wrapper.find('.message').trigger('mouseover')
			expect(wrapper.findComponent(MessageButtonsBar).exists()).toBe(true)

			// Actions are rendered with MessageButtonsBar
			expect(wrapper.findComponent(NcActions).exists()).toBe(true)

			// Mouseleave
			await wrapper.find('.message').trigger('mouseleave')
			expect(wrapper.findComponent(MessageButtonsBar).exists()).toBe(false)
		})
	})

	describe('delete action', () => {
		test('deletes message', async () => {
			let resolveDeleteMessage
			const deleteMessage = vi.fn().mockReturnValue(new Promise((resolve, reject) => { resolveDeleteMessage = resolve }))
			testStoreConfig.modules.messagesStore.actions.deleteMessage = deleteMessage
			store = createStore(testStoreConfig)

			// need to mock the date to be within 6h
			vi.useFakeTimers().setSystemTime(new Date('2020-05-07T10:00:00'))

			const wrapper = mountMessage(messageProps)

			// Hover the messages in order to render the MessageButtonsBar component
			await wrapper.find('.message').trigger('mouseover')
			expect(wrapper.findComponent(MessageButtonsBar).exists()).toBe(true)

			wrapper.findComponent(MessageButtonsBar).vm.$emit('delete')

			expect(deleteMessage).toHaveBeenCalledWith(expect.anything(), {
				token: TOKEN,
				id: 123,
				placeholder: expect.anything(),
			})

			await flushPromises()
			expect(wrapper.vm.isDeleting).toBe(true)
			expect(wrapper.find('.icon-loading-small').exists()).toBe(true)

			resolveDeleteMessage(200)
			await flushPromises()

			expect(wrapper.vm.isDeleting).toBe(false)
			expect(wrapper.find('.icon-loading-small').exists()).toBe(false)

			vi.useRealTimers()
		})
	})

	describe('status', () => {
		beforeEach(() => {
			store = createStore(testStoreConfig)
		})

		test('lets user retry sending a timed out message', async () => {
			messageProps.message.sendingFailure = 'timeout'
			const wrapper = mountMessage(messageProps)

			await wrapper.find('.message-body').trigger('mouseover')
			expect(wrapper.findComponent(MessageButtonsBar).exists()).toBe(true)

			const reloadButton = wrapper.find('.sending-failed')
			expect(reloadButton.exists()).toBe(true)

			await reloadButton.trigger('mouseover')

			const reloadNcButton = wrapper.findComponent(NcButton)
			expect(reloadNcButton.exists()).toBe(true)

			const retryEvent = vi.fn()
			EventBus.on('retry-message', retryEvent)

			await reloadNcButton.vm.$emit('click')

			expect(retryEvent).toHaveBeenCalledWith(123)
		})

		test('displays the message already with a spinner while sending it', () => {
			messageProps.message.timestamp = 0
			const wrapper = mountMessage(messageProps)
			const message = wrapper.findComponent(NcRichText)
			expect(message.text()).toBe('test message')

			expect(wrapper.find('.icon-loading-small').exists()).toBe(true)
		})

		test('displays icon when message was read by everyone', () => {
			conversationProps.lastCommonReadMessage = 123
			const wrapper = mountMessage(messageProps)

			expect(wrapper.findComponent(IconCheck).exists()).toBe(false)
			expect(wrapper.findComponent(IconCheckAll).exists()).toBe(true)
		})

		test('displays sent icon when own message was sent', () => {
			conversationProps.lastCommonReadMessage = 0
			const wrapper = mountMessage(messageProps)

			expect(wrapper.findComponent(IconCheck).exists()).toBe(true)
			expect(wrapper.findComponent(IconCheckAll).exists()).toBe(false)
		})

		test('does not displays check icon for other people\'s messages', () => {
			conversationProps.lastCommonReadMessage = 123
			messageProps.message.actorId = 'user-id-2'
			messageProps.message.actorType = ATTENDEE.ACTOR_TYPE.USERS
			const wrapper = mountMessage(messageProps)

			expect(wrapper.findComponent(IconCheck).exists()).toBe(false)
			expect(wrapper.findComponent(IconCheckAll).exists()).toBe(false)
		})
	})
})
