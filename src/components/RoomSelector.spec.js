/*
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { flushPromises, mount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createStore, useStore } from 'vuex'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import ConversationSearchResult from './LeftSidebar/ConversationsList/ConversationSearchResult.vue'
import RoomSelector from './RoomSelector.vue'
import router from '../__mocks__/router.js'
import { CONVERSATION } from '../constants.ts'
import storeConfig from '../store/storeConfig.js'
import { useTokenStore } from '../stores/token.ts'
import { generateOCSResponse } from '../test-helpers.js'

vi.mock('vuex', async () => {
	const vuex = await vi.importActual('vuex')
	return {
		...vuex,
		useStore: vi.fn(),
	}
})

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
	},
}))

const ComponentStub = {
	template: '<div><slot /></div>',
}
const ConversationsSearchListVirtualStub = {
	props: {
		conversations: Array,
		isSearchResult: Boolean,
	},
	components: {
		ConversationSearchResult,
	},
	template: `<ul>
		<ConversationSearchResult v-for="conversation in conversations"
			:key="conversation.token"
			:item="conversation"
			@click="$emit('select', $event)"/>
	</ul>`,
}

describe('RoomSelector', () => {
	let conversations
	let tokenStore

	let testStoreConfig
	let store = null

	beforeEach(() => {
		testStoreConfig = cloneDeep(storeConfig)
		store = createStore(testStoreConfig)
		useStore.mockReturnValue(store)

		tokenStore = useTokenStore()
		tokenStore.token = 'current-token'

		conversations = [{
			token: 'token-3',
			displayName: 'zzz',
			type: CONVERSATION.TYPE.ONE_TO_ONE,
			lastActivity: 3,
			isFavorite: false,
			readOnly: CONVERSATION.STATE.READ_WRITE,
		}, {
			token: 'token-1',
			displayName: 'conversation one',
			type: CONVERSATION.TYPE.PUBLIC,
			listable: CONVERSATION.LISTABLE.USERS,
			lastActivity: 1,
			isFavorite: true,
			readOnly: CONVERSATION.STATE.READ_WRITE,
		}, {
			token: 'token-2',
			displayName: 'abc',
			type: CONVERSATION.TYPE.GROUP,
			listable: CONVERSATION.LISTABLE.USERS,
			lastActivity: 2,
			isFavorite: false,
			readOnly: CONVERSATION.STATE.READ_ONLY,
		}, {
		// all entries below will be filtered out
			token: 'token-changelog',
			displayName: 'changelog',
			type: CONVERSATION.TYPE.CHANGELOG,
		}, {
			token: 'current-token',
			displayName: 'current room',
			type: CONVERSATION.TYPE.GROUP,
		}, {
			token: 'token-password',
			displayName: 'share password room',
			type: CONVERSATION.TYPE.ONE_TO_ONE,
			objectType: 'share:password',
		}, {
			token: 'token-file',
			displayName: 'file room',
			type: CONVERSATION.TYPE.GROUP,
			objectType: 'file',
		}]
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	const mountRoomSelector = async (props) => {
		const payload = conversations.filter((conv) => {
			return !props?.listOpenConversations || conv.listable === CONVERSATION.LISTABLE.USERS
		})

		axios.get.mockResolvedValue(generateOCSResponse({ payload }))

		const wrapper = mount(RoomSelector, {
			global: {
				plugins: [router],
				stubs: {
					ConversationsSearchListVirtual: ConversationsSearchListVirtualStub,
					NcModal: ComponentStub,
				},
			},
			props,
		})
		// need to wait for re-render, otherwise the list is not rendered yet
		await flushPromises()

		return wrapper
	}

	describe('rendering', () => {
		it('renders sorted conversations list fetched from server', async () => {
			// Arrange
			const wrapper = await mountRoomSelector({ isPlugin: true })
			expect(axios.get).toHaveBeenCalledWith(
				generateOcsUrl('/apps/spreed/api/v4/room'),
				{ params: { includeStatus: 1 } },
			)

			// Assert
			const list = wrapper.findAllComponents({ name: 'NcListItem' })
			expect(list).toHaveLength(4)
			expect(list.at(0).props('name')).toBe(conversations[1].displayName)
			expect(list.at(1).props('name')).toBe(conversations[0].displayName)
			expect(list.at(2).props('name')).toBe(conversations[2].displayName)
			expect(list.at(3).props('name')).toBe(conversations[4].displayName)
		})

		it('excludes current conversation if mounted inside of Talk', async () => {
			// Arrange
			await router.push({ name: 'conversation', params: { token: 'current-token' } })
			const wrapper = await mountRoomSelector({ isPlugin: false })
			expect(axios.get).toHaveBeenCalledWith(
				generateOcsUrl('/apps/spreed/api/v4/room'),
				{ params: { includeStatus: 1 } },
			)

			// Assert
			const list = wrapper.findAllComponents({ name: 'NcListItem' })
			expect(list).toHaveLength(3)
			expect(list.at(0).props('name')).toBe(conversations[1].displayName)
			expect(list.at(1).props('name')).toBe(conversations[0].displayName)
			expect(list.at(2).props('name')).toBe(conversations[2].displayName)
		})

		it('renders open conversations list fetched from server', async () => {
			// Arrange
			const wrapper = await mountRoomSelector({ listOpenConversations: true })
			expect(axios.get).toHaveBeenCalledWith(
				generateOcsUrl('/apps/spreed/api/v4/listed-room'),
				{ params: { searchTerm: '' } },
			)

			// Assert
			const list = wrapper.findAllComponents({ name: 'NcListItem' })
			expect(list).toHaveLength(2)
			expect(list.at(0).props('name')).toBe(conversations[1].displayName)
			expect(list.at(1).props('name')).toBe(conversations[2].displayName)
		})

		it('excludes non-postable conversations', async () => {
			// Arrange
			const wrapper = await mountRoomSelector({ showPostableOnly: true })

			// Assert
			const list = wrapper.findAllComponents({ name: 'NcListItem' })
			expect(list).toHaveLength(2)
			expect(list.at(0).props('name')).toBe(conversations[1].displayName)
			expect(list.at(1).props('name')).toBe(conversations[0].displayName)
		})

		it('filters conversations by displayName', async () => {
			// Arrange
			const wrapper = await mountRoomSelector()

			// Act: type 'conversation'
			const input = wrapper.findComponent({ name: 'NcTextField' })
			await input.vm.$emit('update:modelValue', 'conversation')

			// Assert
			const list = wrapper.findAllComponents({ name: 'NcListItem' })
			expect(list).toHaveLength(1)
			expect(list.at(0).props('name')).toBe(conversations[1].displayName)
		})

		it('shows empty content if no conversations matches', async () => {
			// Arrange
			const wrapper = await mountRoomSelector()

			// Act: type 'conversation'
			const input = wrapper.findComponent({ name: 'NcTextField' })
			await input.vm.$emit('update:modelValue', 'qwerty')

			// Assert
			const list = wrapper.findAllComponents({ name: 'NcListItem' })
			expect(list).toHaveLength(0)
		})

		it('shows empty content if no open conversations matches', async () => {
			// Arrange
			const wrapper = await mountRoomSelector({ listOpenConversations: true })

			// Act: type 'conversation'
			const input = wrapper.findComponent({ name: 'NcTextField' })
			await input.vm.$emit('update:modelValue', 'qwerty')

			// Assert
			const list = wrapper.findAllComponents({ name: 'NcListItem' })
			expect(list).toHaveLength(0)
		})
	})

	describe('actions', () => {
		it('clears input field', async () => {
			// Arrange
			const wrapper = await mountRoomSelector()
			const input = wrapper.findComponent({ name: 'NcTextField' })
			await input.vm.$emit('update:modelValue', 'conversation')

			// Act: click trailing button
			await input.find('button').trigger('click')

			// Assert
			const list = wrapper.findAllComponents({ name: 'NcListItem' })
			expect(list).toHaveLength(3)
		})

		it('emits select event on select', async () => {
			// Arrange
			const wrapper = await mountRoomSelector()

			// Act: click on second item, then click 'Select conversation'
			const items = wrapper.findAllComponents(ConversationSearchResult)
			await items.at(1).vm.$emit('click', items.at(1).props('item'))
			await wrapper.findComponent(NcButton).vm.$emit('click')

			// Assert
			const emitted = wrapper.emitted('select')
			expect(emitted).toBeTruthy()
			expect(emitted[0][0]).toMatchObject(conversations[0])
		})

		it('emits close event', async () => {
			// Arrange
			const wrapper = await mountRoomSelector()

			// Act: close dialog
			const dialog = wrapper.findComponent(NcDialog)
			await dialog.vm.$emit('update:open')

			// Assert
			const emitted = wrapper.emitted('close')
			expect(emitted).toBeTruthy()
		})
	})
})
