/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createLocalVue, shallowMount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import { createPinia, setActivePinia } from 'pinia'
import Vuex from 'vuex'

import MessagesSystemGroup from './MessagesSystemGroup.vue'

import { ATTENDEE } from '../../../constants.js'
import storeConfig from '../../../store/storeConfig.js'

describe('MessagesSystemGroup.vue', () => {
	const TOKEN = 'XXTOKENXX'
	let store
	let localVue
	let testStoreConfig

	beforeEach(() => {
		localVue = createLocalVue()
		localVue.use(Vuex)
		setActivePinia(createPinia())

		testStoreConfig = cloneDeep(storeConfig)
		// eslint-disable-next-line import/no-named-as-default-member
		store = new Vuex.Store(testStoreConfig)
	})

	afterEach(() => {
		jest.clearAllMocks()
	})

	test('renders grouped system messages', () => {
		// prepare
		const MESSAGES = [{
			id: 100,
			token: TOKEN,
			actorId: 'actor-1',
			actorDisplayName: 'actor one',
			actorType: ATTENDEE.ACTOR_TYPE.USERS,
			message: 'Actor left the call',
			messageType: 'system',
			messageParameters: {
				actor: {
					id: 'actor-1',
					displayName: 'actor one',
					type: ATTENDEE.ACTOR_TYPE.USERS,
				}
			},
			systemMessage: 'call_left',
			timestamp: 2000,
			isReplyable: false,
		}, {
			id: 110,
			token: TOKEN,
			actorId: 'actor-1',
			actorDisplayName: 'actor one',
			actorType: ATTENDEE.ACTOR_TYPE.USERS,
			message: 'Actor joined the call',
			messageType: 'system',
			messageParameters: {
				actor: {
					id: 'actor-1',
					displayName: 'actor one',
					type: ATTENDEE.ACTOR_TYPE.USERS,
				}
			},
			systemMessage: 'call_joined',
			timestamp: 1000,
			isReplyable: false,
		}]

		// act
		const wrapper = shallowMount(MessagesSystemGroup, {
			localVue,
			store,
			propsData: {
				id: 123,
				token: TOKEN,
				previousMessageId: 90,
				nextMessageId: 200,
				messages: MESSAGES,
			},
		})

		// assert
		const avatarEl = wrapper.findComponent({ name: 'AvatarWrapper' })
		expect(avatarEl.exists()).toBe(false)

		const messagesEl = wrapper.findAllComponents({ name: 'Message' })
		expect(messagesEl.at(0).props()).toMatchObject({
			message: {
				id: `${MESSAGES[0].id}_combined`,
				message: '{actor} reconnected to the call',
				actorId: MESSAGES[0].actorId,
				actorDisplayName: MESSAGES[0].actorDisplayName,
				messageParameters: {
					actor: {
						id: 'actor-1',
						displayName: 'actor one',
						type: ATTENDEE.ACTOR_TYPE.USERS,
					}
				},
			},
			previousMessageId: 90,
			nextMessageId: 200,
		})
		expect(messagesEl.at(1).props()).toMatchObject({
			message: MESSAGES[0],
			previousMessageId: 90,
			nextMessageId: 110,
		})
		expect(messagesEl.at(2).props()).toMatchObject({
			message: MESSAGES[1],
			previousMessageId: 100,
			nextMessageId: 200,
		})
	})

	describe('renders grouped system message of call actions', () => {
		let MESSAGES
		beforeEach(() => {
			MESSAGES = [{
				id: 100,
				token: TOKEN,
				actorId: 'actor-1',
				actorDisplayName: 'actor one',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'Actor joined the call',
				messageType: 'system',
				messageParameters: {
					actor: {
						id: 'actor-1',
						displayName: 'actor one',
						type: ATTENDEE.ACTOR_TYPE.USERS,
					}
				},
				systemMessage: 'call_joined',
				timestamp: 2000,
				isReplyable: false,
			}, {
				id: 120,
				token: TOKEN,
				actorId: 'actor-2',
				actorDisplayName: 'actor two',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'Actor joined the call',
				messageType: 'system',
				messageParameters: {
					actor: {
						id: 'actor-2',
						displayName: 'actor two',
						type: ATTENDEE.ACTOR_TYPE.USERS,
					}
				},
				systemMessage: 'call_joined',
				timestamp: 1000,
				isReplyable: false,
			}, {
				id: 130,
				token: TOKEN,
				actorId: 'actor-3',
				actorDisplayName: 'actor three',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'Actor joined the call',
				messageType: 'system',
				messageParameters: {
					actor: {
						id: 'actor-3',
						displayName: 'actor three',
						type: ATTENDEE.ACTOR_TYPE.USERS,
					}
				},
				systemMessage: 'call_joined',
				timestamp: 300,
				isReplyable: false,
			}]
		})

		test('renders grouped users joining', () => {
			// act
			const wrapper = shallowMount(MessagesSystemGroup, {
				localVue,
				store,
				propsData: {
					id: 123,
					token: TOKEN,
					previousMessageId: 90,
					nextMessageId: 200,
					messages: MESSAGES,
				},
			})
			// assert
			const messagesEl = wrapper.findAllComponents({ name: 'Message' })
			expect(messagesEl.length).toBe(4) // 3 messages + 1 combined messages
			expect(messagesEl.at(0).props()).toMatchObject({
				message: {
					id: `${MESSAGES[0].id}_combined`,
					message: '{user0}, {user1} and 1 more participant joined the call',
					messageParameters: {
						user0: {
							id: 'actor-1',
							displayName: 'actor one',
							type: ATTENDEE.ACTOR_TYPE.USERS,
						},
						user1: {
							id: 'actor-2',
							displayName: 'actor two',
							type: ATTENDEE.ACTOR_TYPE.USERS,
						},
					},
				},
				previousMessageId: 90,
				nextMessageId: 200,
			})
		})

		test('renders grouped users leaving', () => {
			// prepare
			MESSAGES = MESSAGES.map((message) => {
				return {
					...message,
					systemMessage: 'call_left',
					message: 'Actor left the call',
				}
			})
			// act
			const wrapper = shallowMount(MessagesSystemGroup, {
				localVue,
				store,
				propsData: {
					id: 123,
					token: TOKEN,
					previousMessageId: 90,
					nextMessageId: 200,
					messages: MESSAGES,
				},
			})
			// assert
			const messagesEl = wrapper.findAllComponents({ name: 'Message' })
			expect(messagesEl.length).toBe(4) // 3 messages + 1 combined messages
			expect(messagesEl.at(0).props()).toMatchObject({
				message: {
					id: `${MESSAGES[0].id}_combined`,
					message: '{user0}, {user1} and 1 more participant left the call',
					messageParameters: {
						user0: {
							id: 'actor-1',
							displayName: 'actor one',
							type: ATTENDEE.ACTOR_TYPE.USERS,
						},
						user1: {
							id: 'actor-2',
							displayName: 'actor two',
							type: ATTENDEE.ACTOR_TYPE.USERS,
						},
					},
				},
				previousMessageId: 90,
				nextMessageId: 200,
			})

		})

	})

	describe('renders grouped system message of user actions', () => {
		let MESSAGES
		/**
		 * @param {object} wrapper - Wrapper of the component
		 * @param {string} expectedMessage - Expected message
		 *
		 */
		function testGroupedSystemMessages(wrapper, expectedMessage) {
			const messagesEl = wrapper.findAllComponents({ name: 'Message' })
			// combined message doesn't include the last message (it has a different actor)
			expect(messagesEl.at(0).props().message.message).toBe(expectedMessage)
			expect(messagesEl.at(0).props().message.messageParameters).toStrictEqual({
				actor: {
					id: 'actor-1',
					displayName: 'actor one',
					type: ATTENDEE.ACTOR_TYPE.USERS,
				},
				user0: {
					id: 'actor-4',
					displayName: 'actor four',
					type: ATTENDEE.ACTOR_TYPE.USERS,
				},
				user1: {
					id: 'actor-5',
					displayName: 'actor five',
					type: ATTENDEE.ACTOR_TYPE.USERS,
				},
			})

			expect(messagesEl.at(3).props().message.message).toBe(MESSAGES[2].message)
			expect(messagesEl.at(3).props().message.messageParameters).toStrictEqual({
				actor: {
					id: 'actor-3',
					displayName: 'actor three',
					type: ATTENDEE.ACTOR_TYPE.USERS,
				},
				user: {
					id: 'actor-6',
					displayName: 'actor six',
					type: ATTENDEE.ACTOR_TYPE.USERS,
				},
			})
		}

		beforeEach(() => {
			MESSAGES = [{
				id: 100,
				token: TOKEN,
				actorId: 'actor-1',
				actorDisplayName: 'actor one',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'actor-1 removed {user}',
				messageType: 'system',
				messageParameters: {
					actor: {
						id: 'actor-1',
						displayName: 'actor one',
						type: ATTENDEE.ACTOR_TYPE.USERS,
					},
					user: {
						id: 'actor-4',
						displayName: 'actor four',
						type: ATTENDEE.ACTOR_TYPE.USERS,
					}
				},
				systemMessage: 'user_removed',
				timestamp: 100,
				isReplyable: false,
			}, {
				id: 120,
				token: TOKEN,
				actorId: 'actor-1',
				actorDisplayName: 'actor one',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'actor-1 removed {user}',
				messageType: 'system',
				messageParameters: {
					actor: {
						id: 'actor-1',
						displayName: 'actor one',
						type: ATTENDEE.ACTOR_TYPE.USERS,
					},
					user: {
						id: 'actor-5',
						displayName: 'actor five',
						type: ATTENDEE.ACTOR_TYPE.USERS,
					}
				},
				systemMessage: 'user_removed',
				timestamp: 200,
				isReplyable: false,
			}, {
				id: 130,
				token: TOKEN,
				actorId: 'actor-3',
				actorDisplayName: 'actor three',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				message: 'actor-3 removed {user}',
				messageType: 'system',
				messageParameters: {
					actor: {
						id: 'actor-3',
						displayName: 'actor three',
						type: ATTENDEE.ACTOR_TYPE.USERS,
					},
					user: {
						id: 'actor-6',
						displayName: 'actor six',
						type: ATTENDEE.ACTOR_TYPE.USERS,
					}
				},
				systemMessage: 'user_removed',
				timestamp: 300,
				isReplyable: false,
			}]
		})

		test('renders removed users system messages grouped by the same actor', () => {
			// act
			const wrapper = shallowMount(MessagesSystemGroup, {
				localVue,
				store,
				propsData: {
					id: 123,
					token: TOKEN,
					previousMessageId: 90,
					nextMessageId: 200,
					messages: MESSAGES,
				},
			})
			// assert
			testGroupedSystemMessages(wrapper, '{actor} removed {user0} and {user1}')
		})

		test('renders added users system messages grouped by the same actor', () => {
			// prepare
			MESSAGES = MESSAGES.map((message) => {
				return {
					...message,
					systemMessage: 'user_added',
					message: '{actor} added {user}',
				}
			})
			// act
			const wrapper = shallowMount(MessagesSystemGroup, {
				localVue,
				store,
				propsData: {
					id: 123,
					token: TOKEN,
					previousMessageId: 90,
					nextMessageId: 200,
					messages: MESSAGES,
				},
			})
			// assert
			testGroupedSystemMessages(wrapper, '{actor} added {user0} and {user1}')
		})

		test('renders promoted users to moderators system messages grouped by the same actor', () => {
			// prepare
			MESSAGES = MESSAGES.map((message) => {
				return {
					...message,
					systemMessage: 'moderator_promoted',
					message: '{actor} promoted {user} to moderator',
				}
			})
			// act
			const wrapper = shallowMount(MessagesSystemGroup, {
				localVue,
				store,
				propsData: {
					id: 123,
					token: TOKEN,
					previousMessageId: 90,
					nextMessageId: 200,
					messages: MESSAGES,
				},
			})
			// assert
			testGroupedSystemMessages(wrapper, '{actor} promoted {user0} and {user1} to moderators')
		})

		test('renders demoted users from moderators system messages grouped by the same actor', () => {
			// prepare
			MESSAGES = MESSAGES.map((message) => {
				return {
					...message,
					systemMessage: 'moderator_demoted',
					message: '{actor} demoted {user} from moderator',
				}
			})
			// act
			const wrapper = shallowMount(MessagesSystemGroup, {
				localVue,
				store,
				propsData: {
					id: 123,
					token: TOKEN,
					previousMessageId: 90,
					nextMessageId: 200,
					messages: MESSAGES,
				},
			})
			// assert
			testGroupedSystemMessages(wrapper, '{actor} demoted {user0} and {user1} from moderators')
		})
	})

})
