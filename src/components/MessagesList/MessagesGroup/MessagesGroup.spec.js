/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createLocalVue, shallowMount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import { createPinia, setActivePinia } from 'pinia'
import Vuex from 'vuex'

import MessagesGroup from './MessagesGroup.vue'
import MessagesSystemGroup from './MessagesSystemGroup.vue'

import { ATTENDEE } from '../../../constants.js'
import storeConfig from '../../../store/storeConfig.js'
import { useGuestNameStore } from '../../../stores/guestName.js'

describe('MessagesGroup.vue', () => {
	const TOKEN = 'XXTOKENXX'
	let store
	let localVue
	let testStoreConfig
	let guestNameStore

	beforeEach(() => {
		localVue = createLocalVue()
		localVue.use(Vuex)
		setActivePinia(createPinia())

		guestNameStore = useGuestNameStore()

		testStoreConfig = cloneDeep(storeConfig)
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
			props: {
				id: 123,
				token: TOKEN,
				previousMessageId: 90,
				nextMessageId: 200,
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

		const avatarEl = wrapper.findComponent({ name: 'AvatarWrapper' })
		expect(avatarEl.attributes('source')).toBe(ATTENDEE.ACTOR_TYPE.USERS)
		expect(avatarEl.attributes('id')).toBe('actor-1')
		expect(avatarEl.attributes('name')).toBe('actor one')

		const authorEl = wrapper.find('.messages__author')
		expect(authorEl.text()).toBe('actor one')

		const messagesEl = wrapper.findAllComponents({ name: 'Message' })
		let message = messagesEl.at(0)
		expect(message.attributes('id')).toBe('100')
		expect(message.attributes('message')).toBe('first')
		expect(message.attributes('actorid')).toBe('actor-1')
		expect(message.attributes('previousmessageid')).toBe('90')
		expect(message.attributes('nextmessageid')).toBe('110')
		expect(message.attributes('istemporary')).toBe('false')

		message = messagesEl.at(1)
		expect(message.attributes('id')).toBe('110')
		expect(message.attributes('message')).toBe('second')
		expect(message.attributes('actorid')).toBe('actor-1')
		expect(message.attributes('previousmessageid')).toBe('100')
		expect(message.attributes('nextmessageid')).toBe('120')
		expect(message.attributes('istemporary')).toBe('false')

		message = messagesEl.at(2)
		expect(message.attributes('id')).toBe('120')
		expect(message.attributes('message')).toBe('third')
		expect(message.attributes('actorid')).toBe('actor-1')
		expect(message.attributes('previousmessageid')).toBe('110')
		expect(message.attributes('nextmessageid')).toBe('200')
		expect(message.attributes('istemporary')).toBe('true')
	})

	test('renders grouped system messages', () => {
		const MESSAGES = [{
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
		}]

		const wrapper = shallowMount(MessagesSystemGroup, {
			localVue,
			store,
			props: {
				id: 123,
				token: TOKEN,
				previousMessageId: 90,
				nextMessageId: 200,
				messages: MESSAGES,
			},
		})

		const avatarEl = wrapper.findComponent({ name: 'AvatarWrapper' })
		expect(avatarEl.exists()).toBe(false)

		const messagesEl = wrapper.findAllComponents({ name: 'Message' })

		let message = messagesEl.at(0)
		expect(message.props('id')).toBe(MESSAGES[0].id)
		expect(message.props('message')).toBe(MESSAGES[0].message)
		expect(message.props('actorid')).toBe(MESSAGES[0].actorid)
		expect(message.props('actordisplayname')).toBe(MESSAGES[0].actordisplayname)
		expect(message.props('previousmessageid')).toBe(MESSAGES[0].previousmessageid)
		expect(message.props('nextmessageid')).toBe(MESSAGES[0].nextmessageid)
		expect(message.props('isfirstmessage')).toBe(MESSAGES[0].isfirstmessage)
		expect(message.props('showauthor')).not.toBeDefined()

		message = messagesEl.at(1)
		expect(message.props('id')).toBe(MESSAGES[1].id)
		expect(message.props('message')).toBe(MESSAGES[1].message)
		expect(message.props('actorid')).toBe(MESSAGES[1].actorid)
		expect(message.props('actordisplayname')).toBe(MESSAGES[1].actordisplayname)
		expect(message.props('previousmessageid')).toBe(MESSAGES[1].previousmessageid)
		expect(message.props('nextmessageid')).toBe(MESSAGES[1].nextmessageid)
		expect(message.props('isfirstmessage')).not.toBeDefined()
		expect(message.props('showauthor')).not.toBeDefined()
	})

	test('renders guest display name', () => {
		// Arrange
		guestNameStore.addGuestName({
			token: TOKEN,
			actorId: 'actor-1',
			actorDisplayName: 'guest-one-display-name',
		}, { noUpdate: false })

		const wrapper = shallowMount(MessagesGroup, {
			localVue,
			store,
			props: {
				id: 123,
				token: TOKEN,
				previousMessageId: 90,
				nextMessageId: 200,
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

		const avatarEl = wrapper.findComponent({ name: 'AvatarWrapper' })
		expect(avatarEl.attributes('source')).toBe(ATTENDEE.ACTOR_TYPE.GUESTS)
		expect(avatarEl.attributes('id')).toBe('actor-1')
		expect(avatarEl.attributes('name')).toBe('guest-one-display-name')

		const authorEl = wrapper.find('.messages__author')
		expect(authorEl.text()).toBe('guest-one-display-name')

		const messagesEl = wrapper.findAllComponents({ name: 'Message' })
		let message = messagesEl.at(0)
		expect(message.attributes('id')).toBe('100')
		expect(message.attributes('actorid')).toBe('actor-1')

		message = messagesEl.at(1)
		expect(message.attributes('id')).toBe('110')
		expect(message.attributes('actorid')).toBe('actor-1')
	})

	test('renders deleted guest display name', () => {
		const wrapper = shallowMount(MessagesGroup, {
			localVue,
			store,
			props: {
				id: 123,
				token: TOKEN,
				previousMessageId: 90,
				nextMessageId: 200,
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

		const avatarEl = wrapper.findComponent({ name: 'AvatarWrapper' })
		expect(avatarEl.attributes('source')).toBe(ATTENDEE.ACTOR_TYPE.USERS)
		expect(avatarEl.attributes('id')).toBe('actor-1')
		expect(avatarEl.attributes('name')).toBe('Deleted user')

		const authorEl = wrapper.find('.messages__author')
		expect(authorEl.text()).toBe('Deleted user')

		const messagesEl = wrapper.findAllComponents({ name: 'Message' })
		let message = messagesEl.at(0)
		expect(message.attributes('id')).toBe('100')
		expect(message.attributes('actorid')).toBe('actor-1')

		message = messagesEl.at(1)
		expect(message.attributes('id')).toBe('110')
		expect(message.attributes('actorid')).toBe('actor-1')
	})
})
