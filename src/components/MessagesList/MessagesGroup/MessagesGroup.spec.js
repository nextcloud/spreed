/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createLocalVue, shallowMount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import { createPinia, setActivePinia } from 'pinia'
import { computed } from 'vue'
import Vuex from 'vuex'

import MessagesGroup from './MessagesGroup.vue'

import * as useMessageInfoModule from '../../../composables/useMessageInfo.js'
import { ATTENDEE } from '../../../constants.js'
import storeConfig from '../../../store/storeConfig.js'

describe('MessagesGroup.vue', () => {
	const TOKEN = 'XXTOKENXX'
	let store
	let localVue
	let testStoreConfig
	let useMessageInfoSpy

	beforeEach(() => {
		localVue = createLocalVue()
		localVue.use(Vuex)
		setActivePinia(createPinia())

		testStoreConfig = cloneDeep(storeConfig)
		// eslint-disable-next-line import/no-named-as-default-member
		store = new Vuex.Store(testStoreConfig)
		useMessageInfoSpy = jest.spyOn(useMessageInfoModule, 'useMessageInfo')
	})

	afterEach(() => {
		jest.clearAllMocks()
		useMessageInfoSpy.mockRestore()
	})

	test('renders grouped messages', () => {
		useMessageInfoSpy.mockReturnValue({
			actorDisplayName: computed(() => 'actor one'),
			remoteServer: computed(() => ''),
			lastEditor: computed(() => ''),
		})
		const wrapper = shallowMount(MessagesGroup, {
			localVue,
			store,
			propsData: {
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
		expect(messagesEl.at(0).props()).toMatchObject({
			message: {
				id: 100,
				message: 'first',
				actorId: 'actor-1',
			},
			previousMessageId: 90,
			nextMessageId: 110,
		})
		expect(messagesEl.at(1).props()).toMatchObject({
			message: {
				id: 110,
				message: 'second',
				actorId: 'actor-1',
			},
			previousMessageId: 100,
			nextMessageId: 120,
		})
		expect(messagesEl.at(2).props()).toMatchObject({
			message: {
				id: 120,
				message: 'third',
				actorId: 'actor-1',
			},
			previousMessageId: 110,
			nextMessageId: 200,
		})
	})

	test('renders guest display name', () => {
		// Arrange
		useMessageInfoSpy.mockReturnValue({
			actorDisplayName: computed(() => 'guest-one-display-name'),
			remoteServer: computed(() => ''),
			lastEditor: computed(() => ''),
		})

		const wrapper = shallowMount(MessagesGroup, {
			localVue,
			store,
			propsData: {
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
		expect(messagesEl.at(0).props()).toMatchObject({
			message: {
				id: 100,
				actorId: 'actor-1',
			},
		})
		expect(messagesEl.at(1).props()).toMatchObject({
			message: {
				id: 110,
				actorId: 'actor-1',
			},
		})
	})

	test('renders deleted guest display name', () => {
		// Arrange
		useMessageInfoSpy.mockReturnValue({
			actorDisplayName: computed(() => 'Deleted user'),
			remoteServer: computed(() => ''),
			lastEditor: computed(() => ''),
	   })

		const wrapper = shallowMount(MessagesGroup, {
			localVue,
			store,
			propsData: {
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
		expect(messagesEl.at(0).props()).toMatchObject({
			message: {
				id: 100,
				actorId: 'actor-1',
			},
		})
		expect(messagesEl.at(1).props()).toMatchObject({
			message: {
				id: 110,
				actorId: 'actor-1',
			},
		})
	})
})
