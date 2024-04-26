/**
 * SPDX-FileCopyrightText: Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createLocalVue, shallowMount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import Vuex from 'vuex'

import MessagesList from './MessagesList.vue'

import { ATTENDEE } from '../../constants.js'
import storeConfig from '../../store/storeConfig.js'

describe('MessagesList.vue', () => {
	const TOKEN = 'XXTOKENXX'
	let store
	let localVue
	let testStoreConfig
	const messagesListMock = jest.fn()
	const getVisualLastReadMessageIdMock = jest.fn()

	beforeEach(() => {
		localVue = createLocalVue()
		localVue.use(Vuex)

		testStoreConfig = cloneDeep(storeConfig)
		testStoreConfig.modules.messagesStore.getters.messagesList
			= jest.fn().mockReturnValue(messagesListMock)
		testStoreConfig.modules.messagesStore.getters.getVisualLastReadMessageId
			= jest.fn().mockReturnValue(getVisualLastReadMessageIdMock)
		// eslint-disable-next-line import/no-named-as-default-member
		store = new Vuex.Store(testStoreConfig)

		// scrollTo isn't implemented in JSDOM
		Element.prototype.scrollTo = () => {}

		// hack to catch date separators
		const oldTee = global.t
		global.t = jest.fn().mockImplementation(function(pkg, text, data) {
			if (data && data.relativeDate) {
				return data.relativeDate + ', ' + data.absoluteDate
			}
			return oldTee.apply(this, arguments)
		})
	})

	afterEach(() => {
		jest.clearAllMocks()
	})

	describe('message grouping', () => {
		test('groups consecutive messages by author', () => {
			const messagesGroup1 = [{
				id: 100,
				token: TOKEN,
				actorId: 'alice',
				actorDisplayName: 'Alice',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'hello',
				messageType: 'comment',
				messageParameters: {},
				systemMessage: '',
				timestamp: 100,
				isReplyable: true,
			}, {
				id: 110,
				token: TOKEN,
				actorId: 'alice',
				actorDisplayName: 'Alice',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'how are you ?',
				messageType: 'comment',
				messageParameters: {},
				systemMessage: '',
				timestamp: 200,
				isReplyable: true,
			}]

			const messagesGroup2 = [{
				id: 200,
				token: TOKEN,
				actorId: 'bob',
				actorDisplayName: 'Bob',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'hello!',
				messageType: 'comment',
				messageParameters: {},
				systemMessage: '',
				timestamp: 300,
				isReplyable: true,
			}, {
				id: 210,
				token: TOKEN,
				actorId: 'bob',
				actorDisplayName: 'Bob',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'fine... how abouty you ?',
				messageType: 'comment',
				messageParameters: {},
				systemMessage: '',
				timestamp: 400,
				isReplyable: true,
			}]

			const messagesGroup3 = [{
				id: 300,
				token: TOKEN,
				actorId: 'alice',
				actorDisplayName: 'Alice',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'fine as well, thanks!',
				messageType: 'comment',
				messageParameters: {},
				systemMessage: '',
				timestamp: 0, // temporary
				isReplyable: true,
			}]

			const allMessages = messagesGroup1.concat(messagesGroup2.concat(messagesGroup3))
			messagesListMock.mockReturnValue(allMessages)

			const wrapper = shallowMount(MessagesList, {
				localVue,
				store,
				propsData: {
					token: TOKEN,
					isChatScrolledToBottom: true,
				},
			})

			const groups = wrapper.findAllComponents({ name: 'MessagesGroup' })

			expect(groups.exists()).toBe(true)

			let group = groups.at(0)
			expect(group.props('messages')).toStrictEqual(messagesGroup1)
			expect(group.props('previousMessageId')).toBe(0)
			expect(group.props('nextMessageId')).toBe(200)

			group = groups.at(1)
			expect(group.props('messages')).toStrictEqual(messagesGroup2)
			expect(group.props('previousMessageId')).toBe(110)
			expect(group.props('nextMessageId')).toBe(300)

			group = groups.at(2)
			expect(group.props('messages')).toStrictEqual(messagesGroup3)
			expect(group.props('previousMessageId')).toBe(210)
			expect(group.props('nextMessageId')).toBe(0)

			expect(messagesListMock).toHaveBeenCalledWith(TOKEN)
		})

		test('displays a date separator between days', () => {
			const messages = [{
				id: 100,
				token: TOKEN,
				actorId: 'alice',
				actorDisplayName: 'Alice',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'hello',
				messageType: 'comment',
				messageParameters: {},
				systemMessage: '',
				timestamp: new Date('2020-05-09 13:00:00').getTime() / 1000,
				isReplyable: true,
			}, {
				id: 110,
				token: TOKEN,
				actorId: 'alice',
				actorDisplayName: 'Alice',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'no one here ?',
				messageType: 'comment',
				messageParameters: {},
				systemMessage: '',
				timestamp: new Date('2020-05-10 13:00:00').getTime() / 1000,
				isReplyable: true,
			}, {
				id: 'temp-120',
				token: TOKEN,
				actorId: 'alice',
				actorDisplayName: 'Alice',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'seems no one is there...',
				messageType: 'comment',
				messageParameters: {},
				systemMessage: '',
				timestamp: 0, // temporary, matches current date
				isReplyable: true,
			}]

			const mockDate = new Date('2020-05-11 13:00:00')
			jest.spyOn(global.Date, 'now').mockImplementation(() => mockDate)

			messagesListMock.mockReturnValue(messages)

			const wrapper = shallowMount(MessagesList, {
				localVue,
				store,
				propsData: {
					token: TOKEN,
					isChatScrolledToBottom: true,
				},
			})

			const groups = wrapper.findAllComponents({ name: 'MessagesGroup' })

			expect(groups.exists()).toBeTruthy()

			groups.wrappers.forEach((group, index) => {
				expect(group.props('messages')).toStrictEqual([messages[index]])
			})

			const dateSeparators = wrapper.findAll('.messages-group__date')
			expect(dateSeparators).toHaveLength(3)
			expect(dateSeparators.at(0).text()).toBe('2 days ago, May 9, 2020')
			expect(dateSeparators.at(1).text()).toBe('Yesterday, May 10, 2020')
			expect(dateSeparators.at(2).text()).toBe('Today, May 11, 2020')

			expect(messagesListMock).toHaveBeenCalledWith(TOKEN)
		})

		test('groups system messages with each other', () => {
			const messages = [{
				id: 100,
				token: TOKEN,
				actorId: 'alice',
				actorDisplayName: 'Alice',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'Alice has entered the call',
				messageType: 'comment',
				messageParameters: {},
				systemMessage: 'call_started',
				timestamp: 100,
				isReplyable: true,
			}, {
				id: 110,
				token: TOKEN,
				actorId: 'alice',
				actorDisplayName: 'Alice',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'Alice has exited the call',
				messageType: 'comment',
				messageParameters: {},
				systemMessage: 'call_ended',
				timestamp: 200,
				isReplyable: true,
			}]

			messagesListMock.mockReturnValue(messages)

			const wrapper = shallowMount(MessagesList, {
				localVue,
				store,
				propsData: {
					token: TOKEN,
					isChatScrolledToBottom: true,
				},
			})

			const groups = wrapper.findAll('.messages-group')

			expect(groups.exists()).toBe(true)

			const group = groups.at(0)
			expect(group.props('messages')).toStrictEqual(messages)

			expect(messagesListMock).toHaveBeenCalledWith(TOKEN)
		})

		/**
		 * @param {Array} messages List of messages that should not be grouped
		 */
		function testNotGrouped(messages) {
			messagesListMock.mockReturnValue(messages)

			const wrapper = shallowMount(MessagesList, {
				localVue,
				store,
				propsData: {
					token: TOKEN,
					isChatScrolledToBottom: true,
				},
			})

			const groups = wrapper.findAll('.messages-group')

			expect(groups.exists()).toBeTruthy()

			groups.wrappers.forEach((group, index) => {
				expect(group.props('messages')).toStrictEqual([messages[index]])
			})
		}

		test('does not group system messages with regular messages from the same author', () => {
			testNotGrouped([{
				id: 100,
				token: TOKEN,
				actorId: 'alice',
				actorDisplayName: 'Alice',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'Alice has entered the call',
				messageType: 'comment',
				messageParameters: {},
				systemMessage: 'call_started',
				timestamp: 100,
				isReplyable: true,
			}, {
				id: 110,
				token: TOKEN,
				actorId: 'alice',
				actorDisplayName: 'Alice',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'hello',
				messageType: 'comment',
				messageParameters: {},
				systemMessage: '',
				timestamp: 200,
				isReplyable: true,
			}])
		})

		test('does not group messages of changelog bot', () => {
			testNotGrouped([{
				id: 100,
				token: TOKEN,
				actorId: ATTENDEE.CHANGELOG_BOT_ID,
				actorDisplayName: 'Changelog bot',
				actorType: ATTENDEE.ACTOR_TYPE.BOTS,
				message: 'Alice has entered the call',
				messageType: 'comment',
				messageParameters: {},
				systemMessage: 'call_started',
				timestamp: 100,
				isReplyable: true,
			}, {
				id: 110,
				token: TOKEN,
				actorId: ATTENDEE.CHANGELOG_BOT_ID,
				actorDisplayName: 'Changelog bot',
				actorType: ATTENDEE.ACTOR_TYPE.BOTS,
				message: 'hello',
				messageType: 'comment',
				messageParameters: {},
				systemMessage: '',
				timestamp: 200,
				isReplyable: true,
			}])
		})

		test('does not group messages with different actor types', () => {
			testNotGrouped([{
				id: 100,
				token: TOKEN,
				actorId: 'alice',
				actorDisplayName: 'Alice',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'Alice has entered the call',
				messageType: 'comment',
				messageParameters: {},
				systemMessage: 'call_started',
				timestamp: 100,
				isReplyable: true,
			}, {
				id: 110,
				token: TOKEN,
				actorId: 'alice',
				actorDisplayName: 'Alice',
				actorType: ATTENDEE.ACTOR_TYPE.GUESTS,
				message: 'hello',
				messageType: 'comment',
				messageParameters: {},
				systemMessage: '',
				timestamp: 200,
				isReplyable: true,
			}])
		})

		test('renders a placeholder while loading', () => {
			messagesListMock.mockReturnValue([])

			const wrapper = shallowMount(MessagesList, {
				localVue,
				store,
				propsData: {
					token: TOKEN,
					isChatScrolledToBottom: true,
				},
			})

			const groups = wrapper.findAllComponents({ name: 'MessagesGroup' })
			expect(groups.exists()).toBe(false)

			const placeholder = wrapper.findAllComponents({ name: 'LoadingPlaceholder' })
			expect(placeholder.exists()).toBe(true)
		})
	})
})
