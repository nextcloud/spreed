/*
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick, ref } from 'vue'
import { createStore } from 'vuex'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import ChatView from '../ChatView.vue'
import BreakoutRoomsTab from './BreakoutRooms/BreakoutRoomsTab.vue'
import ParticipantsTab from './Participants/ParticipantsTab.vue'
import RightSidebar from './RightSidebar.vue'
import SearchMessagesTab from './SearchMessages/SearchMessagesTab.vue'
import SharedItemsTab from './SharedItems/SharedItemsTab.vue'
import ThreadsTab from './Threads/ThreadsTab.vue'
import router from '../../__mocks__/router.js'
import { ATTENDEE, CONVERSATION, PARTICIPANT, WEBINAR } from '../../constants.ts'
import storeConfig from '../../store/storeConfig.js'
import { useActorStore } from '../../stores/actor.ts'
import { useSidebarStore } from '../../stores/sidebar.ts'

describe('RightSidebar.vue', () => {
	const TOKEN = 'XXTOKENXX'
	let conversation
	let store
	let testStoreConfig
	let actorStore
	let sidebarStore

	beforeEach(() => {
		vi.mock('../../composables/useGetToken.ts', () => ({
			useGetToken: vi.fn().mockReturnValue(ref('XXTOKENXX')),
		}))
		setActivePinia(createPinia())
		actorStore = useActorStore()
		sidebarStore = useSidebarStore()

		conversation = {
			token: TOKEN,
			displayName: 'Test Conversation',
			participantType: PARTICIPANT.TYPE.USER,
			type: CONVERSATION.TYPE.GROUP,
			lobbyState: WEBINAR.LOBBY.NONE,
			objectType: '',
			breakoutRoomMode: CONVERSATION.BREAKOUT_ROOM_MODE.NOT_CONFIGURED,
			sipEnabled: WEBINAR.SIP.DISABLED,
			attendeePin: '',
			unreadMessages: 0,
			unreadMention: false,
			messageExpiration: 3600,
			lastMessage: {
				expirationTimestamp: 1767903155409,
			},
			remoteServer: '',
		}

		actorStore.actorId = 'user-actor-id'
		actorStore.actorType = ATTENDEE.ACTOR_TYPE.USERS
		actorStore.userId = 'current-user'

		const conversationGetterMock = vi.fn().mockReturnValue(conversation)
		const dummyConversationMock = vi.fn().mockReturnValue({})
		const participantsListMock = vi.fn().mockReturnValue([
			{ attendeeId: 1, displayName: 'User 1' },
			{ attendeeId: 2, displayName: 'User 2' },
		])
		const isInLobbyMock = vi.fn().mockReturnValue(false)

		testStoreConfig = cloneDeep(storeConfig)
		testStoreConfig.modules.conversationsStore.getters.conversation = () => conversationGetterMock
		testStoreConfig.modules.conversationsStore.getters.dummyConversation = () => dummyConversationMock
		testStoreConfig.modules.participantsStore.getters.participantsList = () => participantsListMock
		// Ensure getters object exists before setting properties
		if (!testStoreConfig.getters) {
			testStoreConfig.getters = {}
		}
		store = createStore(testStoreConfig)

		router.push({ name: 'conversation', params: { token: TOKEN } })
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	const ComponentStub = {
		template: '<div><slot /></div>',
	}

	/**
	 * Mount RightSidebar with provided options
	 * @param {object} propsData Props to pass to the component
	 * @param {object} options Additional mount options
	 */
	function mountRightSidebar(propsData = {}, options = {}) {
		const errorHandler = vi.fn((err) => {
			throw err
		})
		return mount(RightSidebar, {
			global: {
				plugins: [router, store],
				config: {
					errorHandler,
				},
				stubs: {
					teleport: true,
					NcButton: ComponentStub,
					NcIconSvgWrapper: ComponentStub,
					SetGuestUsername: ComponentStub,
					SipSettings: ComponentStub,
					InternalSignalingHint: ComponentStub,
					LobbyStatus: ComponentStub,
					ParticipantsTab: ComponentStub,
				},
			},
			props: {
				isInCall: false,
				...propsData,
			},
			...options,
		})
	}

	describe('message expiration', () => {
		it('displays message expiration info when set', async () => {
			try {
				conversation.messageExpiration = 3600
				conversation.lastMessage = {
					expirationTimestamp: 1767909197,
				}
				sidebarStore.showSidebar()
				const wrapper = mountRightSidebar()

				await nextTick()

				expect(wrapper.vm.isMessageExpirationSet).toBeTruthy()
				expect(wrapper.vm.messageExpirationDate).toEqual('Jan 08, 2026, 09:53 PM')
				expect(wrapper.find('.group-message-expiration').text()).toContain('Jan 08, 2026, 09:53 PM')
			} catch (ex) {
				console.error('Error in test case:', ex)
				throw ex
			}
		})

		it('do not display message expiration info when removed', async () => {
			try {
				conversation.messageExpiration = 0
				conversation.lastMessage = {
					expirationTimestamp: 0,
				}
				sidebarStore.showSidebar()
				const wrapper = mountRightSidebar()

				await nextTick()

				expect(wrapper.vm.isMessageExpirationSet).toBeFalsy()
				expect(wrapper.find('.group-message-expiration').exists()).toBeFalsy()
			} catch (ex) {
				console.error('Error in test case:', ex)
				throw ex
			}
		})
	})
})
