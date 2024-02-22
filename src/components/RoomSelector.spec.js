import { shallowMount } from '@vue/test-utils'
import flushPromises from 'flush-promises'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import RoomSelector from './RoomSelector.vue'

import { CONVERSATION } from '../constants.js'

jest.mock('@nextcloud/axios', () => ({
	get: jest.fn(),
}))

describe('RoomSelector.vue', () => {
	let conversations

	beforeEach(() => {
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
			lastActivity: 1,
			isFavorite: true,
			readOnly: CONVERSATION.STATE.READ_WRITE,
		}, {
			token: 'token-2',
			displayName: 'abc',
			type: CONVERSATION.TYPE.GROUP,
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

		global.OCA.Talk = {
			instance: {
				$store: {
					getters: {
						getToken: jest.fn().mockReturnValue('current-token'),
					},
				},
			},
		}

		axios.get.mockResolvedValue({
			data: {
				ocs: {
					data: conversations,
				},
			},
		})
	})
	afterEach(() => {
		jest.clearAllMocks()
	})

	test('renders sorted conversation list fetched from server', async () => {
		const wrapper = shallowMount(RoomSelector)

		expect(axios.get).toHaveBeenCalledWith(
			generateOcsUrl('/apps/spreed/api/v4/room'),
			{ params: { includeStatus: true } }
		)

		// need to wait for re-render, otherwise the list is not rendered yet
		await flushPromises()

		const list = wrapper.findAll('li')
		expect(list.length).toBe(3)
		expect(list.at(0).text()).toBe('conversation one')
		expect(list.at(1).text()).toBe('zzz')
		expect(list.at(2).text()).toBe('abc')
	})
	test('excludes non-postable conversations', async () => {
		const wrapper = shallowMount(RoomSelector, {
			propsData: {
				showPostableOnly: true,
			},
		})
		expect(axios.get).toHaveBeenCalledWith(
			generateOcsUrl('/apps/spreed/api/v4/room'),
			{ params: { includeStatus: true } }
		)

		// need to wait for re-render, otherwise the list is not rendered yet
		await flushPromises()

		const list = wrapper.findAll('li')
		expect(list.length).toBe(2)
		expect(list.at(0).text()).toBe('conversation one')
		expect(list.at(1).text()).toBe('zzz')
	})
	test('emits select event on select', async () => {
		const wrapper = shallowMount(RoomSelector)

		expect(axios.get).toHaveBeenCalledWith(
			generateOcsUrl('/apps/spreed/api/v4/room'),
			{ params: { includeStatus: true } }
		)
		await flushPromises()

		const eventHandler = jest.fn()
		wrapper.vm.$root.$on('select', eventHandler)

		const list = wrapper.findAll('li')
		await list.at(1).trigger('click')
		await wrapper.findComponent(NcButton).vm.$emit('click')

		expect(eventHandler).toHaveBeenCalledWith(conversations[1])
	})

	test('emits close event', async () => {
		const wrapper = shallowMount(RoomSelector)

		const eventHandler = jest.fn()
		wrapper.vm.$root.$on('close', eventHandler)

		await wrapper.findComponent({ name: 'NcModal' }).vm.$emit('close')

		expect(eventHandler).toHaveBeenCalled()
	})
})
