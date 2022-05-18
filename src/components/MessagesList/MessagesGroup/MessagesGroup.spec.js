import Vuex from 'vuex'
import { createLocalVue, shallowMount } from '@vue/test-utils'
import { ATTENDEE } from '../../../constants.js'
import { cloneDeep } from 'lodash'
import storeConfig from '../../../store/storeConfig.js'

import MessagesGroup from './MessagesGroup.vue'

describe('MessagesGroup.vue', () => {
	const TOKEN = 'XXTOKENXX'
	let store
	let localVue
	let testStoreConfig
	let getGuestNameMock

	beforeEach(() => {
		localVue = createLocalVue()
		localVue.use(Vuex)

		testStoreConfig = cloneDeep(storeConfig)
		getGuestNameMock = jest.fn()
		testStoreConfig.modules.guestNameStore.getters.getGuestName = () => getGuestNameMock
		// eslint-disable-next-line import/no-named-as-default-member
		store = new Vuex.Store(testStoreConfig)
	})

	afterEach(() => {
		jest.clearAllMocks()
	})

	test('renders grouped messages', () => {
		const wrapper = shallowMount(MessagesGroup, {
			localVue,
			store,
			propsData: {
				id: 123,
				token: TOKEN,
				previousMessageId: 90,
				nextMessageId: 200,
				lastReadMessageId: 110,
				messages: [{
					id: 100,
					token: TOKEN,
					actorId: 'actor-1',
					actorDisplayName: 'actor one',
					actorType: ATTENDEE.ACTOR_TYPE.USERS,
					message: 'first',
					messageType: 'comment',
					messageParameters: {},
					systemMessage: '',
					timestamp: 100,
					isReplyable: true,
					dateSeparator: '<date separator>',
				}, {
					id: 110,
					token: TOKEN,
					actorId: 'actor-1',
					actorDisplayName: 'actor one',
					actorType: ATTENDEE.ACTOR_TYPE.USERS,
					message: 'second',
					messageType: 'comment',
					messageParameters: {},
					systemMessage: '',
					timestamp: 200,
					isReplyable: true,
				}, {
					id: 120,
					token: TOKEN,
					actorId: 'actor-1',
					actorDisplayName: 'actor one',
					actorType: ATTENDEE.ACTOR_TYPE.USERS,
					message: 'third',
					messageType: 'comment',
					messageParameters: {},
					systemMessage: '',
					timestamp: 0, // temporary
					isReplyable: true,
				}],
			},
		})

		const dateEl = wrapper.find('.message-group__date-header')
		expect(dateEl.text()).toBe('<date separator>')

		const avatarEl = wrapper.findComponent({ name: 'AuthorAvatar' })
		expect(avatarEl.attributes('authortype')).toBe(ATTENDEE.ACTOR_TYPE.USERS)
		expect(avatarEl.attributes('authorid')).toBe('actor-1')
		expect(avatarEl.attributes('displayname')).toBe('actor one')

		const messagesEl = wrapper.findAllComponents({ name: 'Message' })
		let message = messagesEl.at(0)
		expect(message.attributes('id')).toBe('100')
		expect(message.attributes('message')).toBe('first')
		expect(message.attributes('actorid')).toBe('actor-1')
		expect(message.attributes('actordisplayname')).toBe('actor one')
		expect(message.attributes('previousmessageid')).toBe('90')
		expect(message.attributes('nextmessageid')).toBe('110')
		expect(message.attributes('isfirstmessage')).toBe('true')
		expect(message.attributes('lastreadmessageid')).toBe('110')
		expect(message.attributes('showauthor')).toBe('true')
		expect(message.attributes('istemporary')).not.toBeDefined()

		message = messagesEl.at(1)
		expect(message.attributes('id')).toBe('110')
		expect(message.attributes('message')).toBe('second')
		expect(message.attributes('actorid')).toBe('actor-1')
		expect(message.attributes('actordisplayname')).toBe('actor one')
		expect(message.attributes('previousmessageid')).toBe('100')
		expect(message.attributes('nextmessageid')).toBe('120')
		expect(message.attributes('isfirstmessage')).not.toBeDefined()
		expect(message.attributes('lastreadmessageid')).toBe('110')
		expect(message.attributes('showauthor')).toBe('true')
		expect(message.attributes('istemporary')).not.toBeDefined()

		message = messagesEl.at(2)
		expect(message.attributes('id')).toBe('120')
		expect(message.attributes('message')).toBe('third')
		expect(message.attributes('actorid')).toBe('actor-1')
		expect(message.attributes('actordisplayname')).toBe('actor one')
		expect(message.attributes('previousmessageid')).toBe('110')
		expect(message.attributes('nextmessageid')).toBe('200')
		expect(message.attributes('isfirstmessage')).not.toBeDefined()
		expect(message.attributes('lastreadmessageid')).toBe('110')
		expect(message.attributes('showauthor')).toBe('true')
		expect(message.attributes('istemporary')).toBe('true')
	})

	test('renders grouped system messages', () => {
		const wrapper = shallowMount(MessagesGroup, {
			localVue,
			store,
			propsData: {
				id: 123,
				token: TOKEN,
				previousMessageId: 90,
				nextMessageId: 200,
				lastReadMessageId: 110,
				messages: [{
					id: 100,
					token: TOKEN,
					actorId: 'actor-1',
					actorDisplayName: 'actor one',
					actorType: ATTENDEE.ACTOR_TYPE.USERS,
					message: 'Actor entered the scene',
					messageType: 'comment',
					messageParameters: {},
					systemMessage: 'call_started',
					timestamp: 100,
					isReplyable: false,
					dateSeparator: '<date separator>',
				}, {
					id: 110,
					token: TOKEN,
					actorId: 'actor-1',
					actorDisplayName: 'actor one',
					actorType: ATTENDEE.ACTOR_TYPE.USERS,
					message: 'Actor left the scene',
					messageType: 'comment',
					messageParameters: {},
					systemMessage: 'call_stopped',
					timestamp: 200,
					isReplyable: false,
				}],
			},
		})

		const dateEl = wrapper.find('.message-group__date-header')
		expect(dateEl.text()).toBe('<date separator>')

		const avatarEl = wrapper.findComponent({ name: 'AuthorAvatar' })
		expect(avatarEl.exists()).toBe(false)

		const messagesEl = wrapper.findAllComponents({ name: 'Message' })
		// TODO: date separator
		let message = messagesEl.at(0)
		expect(message.attributes('id')).toBe('100')
		expect(message.attributes('message')).toBe('Actor entered the scene')
		expect(message.attributes('actorid')).toBe('actor-1')
		expect(message.attributes('actordisplayname')).toBe('actor one')
		expect(message.attributes('previousmessageid')).toBe('90')
		expect(message.attributes('nextmessageid')).toBe('110')
		expect(message.attributes('isfirstmessage')).toBe('true')
		expect(message.attributes('lastreadmessageid')).toBe('110')
		expect(message.attributes('showauthor')).not.toBeDefined()

		message = messagesEl.at(1)
		expect(message.attributes('id')).toBe('110')
		expect(message.attributes('message')).toBe('Actor left the scene')
		expect(message.attributes('actorid')).toBe('actor-1')
		expect(message.attributes('actordisplayname')).toBe('actor one')
		expect(message.attributes('previousmessageid')).toBe('100')
		expect(message.attributes('nextmessageid')).toBe('200')
		expect(message.attributes('isfirstmessage')).not.toBeDefined()
		expect(message.attributes('lastreadmessageid')).toBe('110')
		expect(message.attributes('showauthor')).not.toBeDefined()
	})

	test('renders guest display name', () => {
		getGuestNameMock.mockReturnValue('guest-one-display-name')
		const wrapper = shallowMount(MessagesGroup, {
			localVue,
			store,
			propsData: {
				id: 123,
				token: TOKEN,
				previousMessageId: 90,
				nextMessageId: 200,
				lastReadMessageId: 110,
				messages: [{
					id: 100,
					token: TOKEN,
					actorId: 'actor-1',
					actorDisplayName: 'actor one',
					actorType: ATTENDEE.ACTOR_TYPE.GUESTS,
					message: 'first',
					messageType: 'comment',
					messageParameters: {},
					systemMessage: '',
					timestamp: 100,
					isReplyable: true,
					dateSeparator: '<date separator>',
				}, {
					id: 110,
					token: TOKEN,
					actorId: 'actor-1',
					actorDisplayName: 'actor one',
					actorType: ATTENDEE.ACTOR_TYPE.GUESTS,
					message: 'second',
					messageType: 'comment',
					messageParameters: {},
					systemMessage: '',
					timestamp: 200,
					isReplyable: true,
				}],
			},
		})

		const dateEl = wrapper.find('.message-group__date-header')
		expect(dateEl.text()).toBe('<date separator>')

		const avatarEl = wrapper.findComponent({ name: 'AuthorAvatar' })
		expect(avatarEl.attributes('authortype')).toBe(ATTENDEE.ACTOR_TYPE.GUESTS)
		expect(avatarEl.attributes('authorid')).toBe('actor-1')
		expect(avatarEl.attributes('displayname')).toBe('guest-one-display-name')

		const messagesEl = wrapper.findAllComponents({ name: 'Message' })
		let message = messagesEl.at(0)
		expect(message.attributes('id')).toBe('100')
		expect(message.attributes('actorid')).toBe('actor-1')
		expect(message.attributes('actordisplayname')).toBe('guest-one-display-name')

		message = messagesEl.at(1)
		expect(message.attributes('id')).toBe('110')
		expect(message.attributes('actorid')).toBe('actor-1')
		expect(message.attributes('actordisplayname')).toBe('guest-one-display-name')

		expect(getGuestNameMock).toHaveBeenCalledWith(TOKEN, 'actor-1')
	})

	test('renders guest display name', () => {
		const wrapper = shallowMount(MessagesGroup, {
			localVue,
			store,
			propsData: {
				id: 123,
				token: TOKEN,
				previousMessageId: 90,
				nextMessageId: 200,
				lastReadMessageId: 110,
				messages: [{
					id: 100,
					token: TOKEN,
					actorId: 'actor-1',
					actorDisplayName: '',
					actorType: ATTENDEE.ACTOR_TYPE.USERS,
					message: 'first',
					messageType: 'comment',
					messageParameters: {},
					systemMessage: '',
					timestamp: 100,
					isReplyable: true,
					dateSeparator: '<date separator>',
				}, {
					id: 110,
					token: TOKEN,
					actorId: 'actor-1',
					actorDisplayName: '',
					actorType: ATTENDEE.ACTOR_TYPE.USERS,
					message: 'second',
					messageType: 'comment',
					messageParameters: {},
					systemMessage: '',
					timestamp: 200,
					isReplyable: true,
				}],
			},
		})

		const avatarEl = wrapper.findComponent({ name: 'AuthorAvatar' })
		expect(avatarEl.attributes('authortype')).toBe(ATTENDEE.ACTOR_TYPE.USERS)
		expect(avatarEl.attributes('authorid')).toBe('actor-1')
		expect(avatarEl.attributes('displayname')).toBe('Deleted user')

		const messagesEl = wrapper.findAllComponents({ name: 'Message' })
		let message = messagesEl.at(0)
		expect(message.attributes('id')).toBe('100')
		expect(message.attributes('actorid')).toBe('actor-1')
		expect(message.attributes('actordisplayname')).toBe('Deleted user')

		message = messagesEl.at(1)
		expect(message.attributes('id')).toBe('110')
		expect(message.attributes('actorid')).toBe('actor-1')
		expect(message.attributes('actordisplayname')).toBe('Deleted user')
	})
})
