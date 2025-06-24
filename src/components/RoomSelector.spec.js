/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { flushPromises, shallowMount } from '@vue/test-utils'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import ConversationSearchResult from './LeftSidebar/ConversationsList/ConversationSearchResult.vue'
import RoomSelector from './RoomSelector.vue'
import { CONVERSATION } from '../constants.ts'
import { useTokenStore } from '../stores/token.ts'
import { generateOCSResponse } from '../test-helpers.js'

jest.mock('@nextcloud/axios', () => ({
	get: jest.fn(),
}))

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

	beforeEach(() => {
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
		jest.clearAllMocks()
	})

	const mountRoomSelector = async (props) => {
		const payload = conversations.filter((conv) => {
			return !props?.listOpenConversations || conv.listable === CONVERSATION.LISTABLE.USERS
		})

		axios.get.mockResolvedValue(generateOCSResponse({ payload }))

		const wrapper = shallowMount(RoomSelector, {
			stubs: {
				ConversationsSearchListVirtual: ConversationsSearchListVirtualStub,
				ConversationSearchResult,
				NcDialog,
			},
			props: props,
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
			await input.vm.$emit('trailing-button-click')

			// Assert
			const list = wrapper.findAllComponents({ name: 'NcListItem' })
			expect(list).toHaveLength(3)
		})

		it('emits select event on select', async () => {
			// Arrange
			const wrapper = await mountRoomSelector()
			const eventHandler = jest.fn()
			wrapper.vm.$on('select', eventHandler)

			// Act: click on second item, then click 'Select conversation'
			const list = wrapper.findComponent({ name: 'ConversationsSearchListVirtual' })
			const items = wrapper.findAllComponents(ConversationSearchResult)
			await items.at(1).vm.$emit('click', items.at(1).props('item'))
			expect(items.at(1).emitted('click')[0][0]).toMatchObject(conversations[0])
			expect(list.emitted('select')[0][0]).toMatchObject(conversations[0])
			await wrapper.findComponent(NcButton).vm.$emit('click')

			// Assert
			expect(eventHandler).toHaveBeenCalledWith(conversations[0])
		})

		it('emits close event', async () => {
			// Arrange
			const wrapper = await mountRoomSelector()
			const eventHandler = jest.fn()
			wrapper.vm.$on('close', eventHandler)

			// Act: close dialog
			const dialog = wrapper.findComponent(NcDialog)
			await dialog.vm.$emit('update:open')

			// Assert
			expect(eventHandler).toHaveBeenCalled()
		})

		it('emits close event on $root as plugin', async () => {
			// Arrange
			const wrapper = await mountRoomSelector({ isPlugin: true })
			const eventHandler = jest.fn()
			wrapper.vm.$root.$on('close', eventHandler)

			// Act: close dialog
			const dialog = wrapper.findComponent(NcDialog)
			await dialog.vm.$emit('update:open')

			// Assert
			expect(eventHandler).toHaveBeenCalled()
		})
	})
})
