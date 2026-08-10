/*
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { shallowMount } from '@vue/test-utils'
import { cloneDeep } from 'es-toolkit'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import { createStore } from 'vuex'
import IconCrownOutline from 'vue-material-design-icons/CrownOutline.vue'
import IconShieldOutline from 'vue-material-design-icons/ShieldOutline.vue'
import MessageItem from './Message/MessageItem.vue'
import MessagesGroup from './MessagesGroup.vue'
import { ATTENDEE, CONVERSATION, MESSAGE, PARTICIPANT } from '../../../constants.ts'
import storeConfig from '../../../store/storeConfig.js'
import { useActorStore } from '../../../stores/actor.ts'
import { useGuestNameStore } from '../../../stores/guestName.ts'

vi.mock('@nextcloud/vue/composables/useIsMobile', () => ({
	useIsSmallMobile: vi.fn(() => false),
}))

describe('MessagesGroup.vue', () => {
	const TOKEN = 'XXTOKENXX'
	let store
	let guestNameStore
	let testStoreConfig

	beforeEach(() => {
		setActivePinia(createPinia())
		guestNameStore = useGuestNameStore()
		const actorStore = useActorStore()

		testStoreConfig = cloneDeep(storeConfig)
		testStoreConfig.modules.conversationsStore.getters.conversation = () => () => ({})
		actorStore.setCurrentUser({ uid: 'actor-1' })
		store = createStore(testStoreConfig)
	})

	afterEach(() => {
		vi.clearAllMocks()
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
			global: {
				plugins: [store],
				provide: {
					'messagesList:isSplitViewEnabled': false,
				},
			},
			props: {
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

		const messagesEl = wrapper.findAllComponents(MessageItem)
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

	describe('role of the author', () => {
		/**
		 * @param {number|null} participantType Participant type of the author, null when they are no participant
		 * @param {number|undefined} conversationType Type of the conversation
		 */
		function mountWithAuthor(participantType, conversationType = CONVERSATION.TYPE.GROUP) {
			testStoreConfig.modules.conversationsStore.getters.conversation = () => () => ({ type: conversationType })
			testStoreConfig.modules.participantsStore.getters.findParticipant = () => () => {
				return participantType === null ? null : { participantType }
			}
			store = createStore(testStoreConfig)

			return shallowMount(MessagesGroup, {
				global: {
					plugins: [store],
					provide: { 'messagesList:isSplitViewEnabled': false },
				},
				props: {
					token: TOKEN,
					previousMessageId: 90,
					nextMessageId: 200,
					messages: [{
						id: 100,
						token: TOKEN,
						actorId: 'actor-1',
						actorDisplayName: 'Alice',
						actorType: ATTENDEE.ACTOR_TYPE.USERS,
						message: 'first',
						messageType: MESSAGE.TYPE.COMMENT,
						messageParameters: {},
						systemMessage: '',
						timestamp: 100,
						isReplyable: true,
					}],
				},
			})
		}

		test('renders a crown for an owner', () => {
			const wrapper = mountWithAuthor(PARTICIPANT.TYPE.OWNER)
			expect(wrapper.findComponent(IconCrownOutline).exists()).toBeTruthy()
			expect(wrapper.findComponent(IconShieldOutline).exists()).toBeFalsy()
		})

		test('renders a shield for a moderator', () => {
			const wrapper = mountWithAuthor(PARTICIPANT.TYPE.MODERATOR)
			expect(wrapper.findComponent(IconShieldOutline).exists()).toBeTruthy()
			expect(wrapper.findComponent(IconCrownOutline).exists()).toBeFalsy()
		})

		test('renders a shield for a guest moderator', () => {
			const wrapper = mountWithAuthor(PARTICIPANT.TYPE.GUEST_MODERATOR)
			expect(wrapper.findComponent(IconShieldOutline).exists()).toBeTruthy()
		})

		test('renders no icon for a regular user', () => {
			const wrapper = mountWithAuthor(PARTICIPANT.TYPE.USER)
			expect(wrapper.findComponent(IconCrownOutline).exists()).toBeFalsy()
			expect(wrapper.findComponent(IconShieldOutline).exists()).toBeFalsy()
		})

		test('renders no icon when the author is no longer a participant', () => {
			const wrapper = mountWithAuthor(null)
			expect(wrapper.findComponent(IconCrownOutline).exists()).toBeFalsy()
			expect(wrapper.findComponent(IconShieldOutline).exists()).toBeFalsy()
		})

		test('renders no icon in a one-to-one conversation', () => {
			// Both participants of a one-to-one conversation are owners by design
			const wrapper = mountWithAuthor(PARTICIPANT.TYPE.OWNER, CONVERSATION.TYPE.ONE_TO_ONE)
			expect(wrapper.findComponent(IconCrownOutline).exists()).toBeFalsy()
		})
	})
})
