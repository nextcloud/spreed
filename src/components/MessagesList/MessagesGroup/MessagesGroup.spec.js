/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createLocalVue, shallowMount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import { createPinia, setActivePinia } from 'pinia'
import Vuex from 'vuex'
import MessagesGroup from './MessagesGroup.vue'
import { ATTENDEE, MESSAGE } from '../../../constants.ts'
import storeConfig from '../../../store/storeConfig.js'
import { useGuestNameStore } from '../../../stores/guestName.js'

describe('MessagesGroup.vue', () => {
	const TOKEN = 'XXTOKENXX'
	let store
	let guestNameStore
	let localVue
	let testStoreConfig

	beforeEach(() => {
		localVue = createLocalVue()
		localVue.use(Vuex)
		setActivePinia(createPinia())
		guestNameStore = useGuestNameStore()

		testStoreConfig = cloneDeep(storeConfig)
		testStoreConfig.modules.conversationsStore.getters.conversation = () => () => ({})
		testStoreConfig.modules.actorStore.getters.getActorId = () => () => 'actor-1'
		testStoreConfig.modules.actorStore.getters.getActorType = () => () => ATTENDEE.ACTOR_TYPE.USERS
		store = new Vuex.Store(testStoreConfig)
	})

	afterEach(() => {
		jest.clearAllMocks()
	})

	/**
	 * Test avatar, actor name and grouped messages
	 * @param {object} payload test case
	 * @param {boolean} withTemp Whether to include temporary message in group
	 */
	function testMessagesGroup(payload, withTemp = false) {
		// Arrange
		const messages = [{
			id: 100,
			token: TOKEN,
			actorId: payload.actorId,
			actorDisplayName: payload.actorDisplayName,
			actorType: payload.actorType,
			message: 'first',
			messageType: MESSAGE.TYPE.COMMENT,
			messageParameters: {},
			systemMessage: '',
			timestamp: 100,
			isReplyable: true,
		}, {
			id: 110,
			token: TOKEN,
			actorId: payload.actorId,
			actorDisplayName: payload.actorDisplayName,
			actorType: payload.actorType,
			message: 'second',
			messageType: MESSAGE.TYPE.COMMENT,
			messageParameters: {},
			systemMessage: '',
			timestamp: 200,
			isReplyable: true,
		}]
		if (withTemp) {
			messages.push({
				id: 120,
				token: TOKEN,
				actorId: payload.actorId,
				actorDisplayName: payload.actorDisplayName,
				actorType: payload.actorType,
				message: 'third',
				messageType: MESSAGE.TYPE.COMMENT,
				messageParameters: {},
				systemMessage: '',
				timestamp: 0, // temporary
				isReplyable: true,
			})
		}
		const actorInfo = payload.actorDisplayNameWithFallback
			+ (payload.remoteServer ? ` (${payload.remoteServer})` : '')
			+ (payload.lastEditor ? ` (${payload.lastEditor})` : '')

		// Act
		const wrapper = shallowMount(MessagesGroup, {
			localVue,
			store,
			propsData: {
				token: TOKEN,
				previousMessageId: 90,
				nextMessageId: 200,
				messages,
			},
		})

		// Assert
		const avatarEl = wrapper.findComponent({ name: 'AvatarWrapper' })
		expect(avatarEl.attributes('source')).toBe(payload.actorType)
		expect(avatarEl.attributes('id')).toBe(payload.actorId)
		expect(avatarEl.attributes('name')).toBe(payload.actorDisplayName)

		const authorEl = wrapper.find('.messages__author')
		expect(authorEl.text()).toBe(actorInfo)

		const messagesEl = wrapper.findAllComponents({ name: 'Message' })
		expect(messagesEl.at(0).props()).toMatchObject({
			message: {
				id: 100,
				message: 'first',
			},
			previousMessageId: 90,
			nextMessageId: 110,
		})
		expect(messagesEl.at(1).props()).toMatchObject({
			message: {
				id: 110,
				message: 'second',
			},
			previousMessageId: 100,
			nextMessageId: withTemp ? 120 : 200,
		})
		if (withTemp) {
			expect(messagesEl.at(2).props()).toMatchObject({
				message: {
					id: 120,
					message: 'third',
				},
				previousMessageId: 110,
				nextMessageId: 200,
			})
		}
	}

	test('renders grouped messages for user', () => {
		testMessagesGroup({
			actorId: 'actor-1',
			actorType: ATTENDEE.ACTOR_TYPE.USERS,
			actorDisplayName: 'Actor One',
			actorDisplayNameWithFallback: 'Actor One',
		})
	})

	test('renders grouped messages for user (with temporary)', () => {
		testMessagesGroup({
			actorId: 'actor-1',
			actorType: ATTENDEE.ACTOR_TYPE.USERS,
			actorDisplayName: 'Actor One',
			actorDisplayNameWithFallback: 'Actor One',
		}, true)
	})

	test('renders grouped messages for guest with custom name', () => {
		guestNameStore.addGuestName({ token: TOKEN, actorId: 'guest/id', actorDisplayName: 'Custom Guest' }, {})
		testMessagesGroup({
			actorId: 'guest/id',
			actorType: ATTENDEE.ACTOR_TYPE.GUESTS,
			actorDisplayName: 'Custom Guest',
			actorDisplayNameWithFallback: 'Custom Guest',
		})
	})

	test('renders grouped messages for guest with default name', () => {
		testMessagesGroup({
			actorId: 'guest/id',
			actorType: ATTENDEE.ACTOR_TYPE.EMAILS,
			actorDisplayName: 'Guest',
			actorDisplayNameWithFallback: 'Guest',
		})
	})

	test('renders grouped messages for deleted user', () => {
		testMessagesGroup({
			actorId: 'deleted_users',
			actorType: ATTENDEE.ACTOR_TYPE.DELETED_USERS,
			actorDisplayName: '',
			actorDisplayNameWithFallback: 'Deleted user',
		})
	})

	test('renders grouped messages for federated user', () => {
		testMessagesGroup({
			actorId: 'actor@nextcloud.local',
			remoteServer: 'nextcloud.local',
			actorType: ATTENDEE.ACTOR_TYPE.FEDERATED_USERS,
			actorDisplayName: 'Federated Actor',
			actorDisplayNameWithFallback: 'Federated Actor',
		})
	})
})
