import { createLocalVue, shallowMount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import Vuex from 'vuex'

import MessagesList from './MessagesList.vue'

import { ATTENDEE } from '../../constants.js'
import storeConfig from '../../store/storeConfig.js'

const fakeTimestamp = (value) => new Date(value).getTime() / 1000

describe('MessagesList.vue', () => {
	const TOKEN = 'XXTOKENXX'
	let store
	let localVue
	let testStoreConfig
	const getVisualLastReadMessageIdMock = jest.fn()

	beforeEach(() => {
		localVue = createLocalVue()
		localVue.use(Vuex)

		testStoreConfig = cloneDeep(storeConfig)
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

	const messagesGroup1 = [{
		id: 100,
		token: TOKEN,
		actorId: 'alice',
		actorDisplayName: 'Alice',
		actorType: ATTENDEE.ACTOR_TYPE.USERS,
		message: 'hello',
		messageType: 'comment',
		messageParameters: [],
		systemMessage: '',
		timestamp: fakeTimestamp('2024-05-01T12:05:00'),
		isReplyable: true,
	}, {
		id: 110,
		token: TOKEN,
		actorId: 'alice',
		actorDisplayName: 'Alice',
		actorType: ATTENDEE.ACTOR_TYPE.USERS,
		message: 'how are you ?',
		messageType: 'comment',
		messageParameters: [],
		systemMessage: '',
		timestamp: fakeTimestamp('2024-05-01T12:06:00'),
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
		messageParameters: [],
		systemMessage: '',
		timestamp: fakeTimestamp('2024-05-01T12:30:00'),
		isReplyable: true,
	}, {
		id: 210,
		token: TOKEN,
		actorId: 'bob',
		actorDisplayName: 'Bob',
		actorType: ATTENDEE.ACTOR_TYPE.USERS,
		message: 'fine... how about you ?',
		messageType: 'comment',
		messageParameters: [],
		systemMessage: '',
		timestamp: fakeTimestamp('2024-05-01T12:31:00'),
		isReplyable: true,
	}]

	const messagesGroup3 = [{
		id: 'temp-300',
		token: TOKEN,
		actorId: 'alice',
		actorDisplayName: 'Alice',
		actorType: ATTENDEE.ACTOR_TYPE.USERS,
		message: 'fine as well, thanks!',
		messageType: 'comment',
		messageParameters: [],
		systemMessage: '',
		timestamp: 0, // temporary
		isReplyable: true,
	}]

	describe('message grouping', () => {
		/**
		 * @param {Array} messagesGroups List of messages that should be grouped
		 */
		function testGrouped(...messagesGroups) {
			messagesGroups.flat().forEach(message => store.commit('addMessage', { token: TOKEN, message }))
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
				expect(group.props('messages')).toStrictEqual(messagesGroups[index])
			})

			return { wrapper, groups }
		}

		/**
		 * @param {Array} messages List of messages that should not be grouped
		 */
		function testNotGrouped(messages) {
			messages.forEach(message => store.commit('addMessage', { token: TOKEN, message }))

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

			return { wrapper, groups }
		}

		test('groups consecutive messages by author', () => {
			const { groups } = testGrouped(messagesGroup1, messagesGroup2, messagesGroup3)

			expect(groups.at(0).props('previousMessageId')).toBe(0)
			expect(groups.at(0).props('nextMessageId')).toBe(200)

			expect(groups.at(1).props('previousMessageId')).toBe(110)
			expect(groups.at(1).props('nextMessageId')).toBe('temp-300')

			expect(groups.at(2).props('previousMessageId')).toBe(210)
			expect(groups.at(2).props('nextMessageId')).toBe(0)
		})

		test('displays a date separator between days', () => {
			jest.useFakeTimers().setSystemTime(new Date('2020-05-11T13:00:00'))

			const { wrapper } = testNotGrouped([{
				id: 100,
				token: TOKEN,
				actorId: 'alice',
				actorDisplayName: 'Alice',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'hello',
				messageType: 'comment',
				messageParameters: [],
				systemMessage: '',
				timestamp: fakeTimestamp('2020-05-09T13:00:00'),
				isReplyable: true,
			}, {
				id: 110,
				token: TOKEN,
				actorId: 'alice',
				actorDisplayName: 'Alice',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'no one here ?',
				messageType: 'comment',
				messageParameters: [],
				systemMessage: '',
				timestamp: fakeTimestamp('2020-05-10T13:00:00'),
				isReplyable: true,
			}, {
				id: 'temp-120',
				token: TOKEN,
				actorId: 'alice',
				actorDisplayName: 'Alice',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'seems no one is there...',
				messageType: 'comment',
				messageParameters: [],
				systemMessage: '',
				timestamp: 0, // temporary, matches current date
				isReplyable: true,
			}])

			const dateSeparators = wrapper.findAll('.messages-group__date')
			expect(dateSeparators).toHaveLength(3)
			expect(dateSeparators.at(0).text()).toBe('2 days ago, May 9, 2020')
			expect(dateSeparators.at(1).text()).toBe('Yesterday, May 10, 2020')
			expect(dateSeparators.at(2).text()).toBe('Today, May 11, 2020')
		})

		test('groups system messages with each other', () => {
			testGrouped([{
				id: 100,
				token: TOKEN,
				actorId: 'alice',
				actorDisplayName: 'Alice',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'Alice has entered the call',
				messageType: 'comment',
				messageParameters: [],
				systemMessage: 'call_started',
				timestamp: fakeTimestamp('2020-05-09T13:00:00'),
				isReplyable: true,
			}, {
				id: 110,
				token: TOKEN,
				actorId: 'alice',
				actorDisplayName: 'Alice',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'Alice has exited the call',
				messageType: 'comment',
				messageParameters: [],
				systemMessage: 'call_ended',
				timestamp: fakeTimestamp('2020-05-09T13:02:00'),
				isReplyable: true,
			}])
		})

		test('does not group system messages with regular messages from the same author', () => {
			testNotGrouped([{
				id: 100,
				token: TOKEN,
				actorId: 'alice',
				actorDisplayName: 'Alice',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'Alice has entered the call',
				messageType: 'comment',
				messageParameters: [],
				systemMessage: 'call_started',
				timestamp: fakeTimestamp('2020-05-09T13:00:00'),
				isReplyable: true,
			}, {
				id: 110,
				token: TOKEN,
				actorId: 'alice',
				actorDisplayName: 'Alice',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'hello',
				messageType: 'comment',
				messageParameters: [],
				systemMessage: '',
				timestamp: fakeTimestamp('2020-05-09T13:02:00'),
				isReplyable: true,
			}])
		})

		test('groups messages of changelog bot', () => {
			testGrouped([{
				id: 100,
				token: TOKEN,
				actorId: ATTENDEE.CHANGELOG_BOT_ID,
				actorDisplayName: 'Talk updates \u2705',
				actorType: ATTENDEE.ACTOR_TYPE.BOTS,
				message: 'New in Talk 16',
				messageType: 'comment',
				messageParameters: [],
				systemMessage: '',
				timestamp: fakeTimestamp('2020-05-09T13:00:00'),
				isReplyable: true,
			}, {
				id: 110,
				token: TOKEN,
				actorId: ATTENDEE.CHANGELOG_BOT_ID,
				actorDisplayName: 'Talk updates \u2705',
				actorType: ATTENDEE.ACTOR_TYPE.BOTS,
				message: '- Calls can now be recorded',
				messageType: 'comment',
				messageParameters: [],
				systemMessage: '',
				timestamp: fakeTimestamp('2020-05-09T13:02:00'),
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
				messageParameters: [],
				systemMessage: 'call_started',
				timestamp: fakeTimestamp('2020-05-09T13:00:00'),
				isReplyable: true,
			}, {
				id: 110,
				token: TOKEN,
				actorId: 'alice',
				actorDisplayName: 'Alice',
				actorType: ATTENDEE.ACTOR_TYPE.GUESTS,
				message: 'hello',
				messageType: 'comment',
				messageParameters: [],
				systemMessage: '',
				timestamp: fakeTimestamp('2020-05-09T13:02:00'),
				isReplyable: true,
			}])
		})

		test('does not group edited messages', () => {
			testNotGrouped([{
				id: 100,
				token: TOKEN,
				actorId: 'alice',
				actorDisplayName: 'Alice',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'hello',
				messageType: 'comment',
				messageParameters: [],
				systemMessage: '',
				timestamp: fakeTimestamp('2024-05-01T12:05:00'),
				isReplyable: true,
			}, {
				id: 110,
				token: TOKEN,
				actorId: 'alice',
				actorDisplayName: 'Alice',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				lastEditActorType: ATTENDEE.ACTOR_TYPE.USERS,
				lastEditActorId: 'alice',
				lastEditActorDisplayName: 'Alice',
				lastEditTimestamp: fakeTimestamp('2024-05-01T12:07:00'),
				message: 'how are you doing?',
				messageType: 'comment',
				messageParameters: [],
				systemMessage: '',
				timestamp: fakeTimestamp('2024-05-01T12:06:00'),
				isReplyable: true,
			}])
		})
	})

	describe('message rendering', () => {
		test('renders a placeholder while loading', () => {
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

		test('renders an empty content after loading', () => {
			store.commit('loadedMessagesOfConversation', { token: TOKEN })
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

			const placeholder = wrapper.findAllComponents({ name: 'NcEmptyContent' })
			expect(placeholder.exists()).toBe(true)
		})
	})
})
