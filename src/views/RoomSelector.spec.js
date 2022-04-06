import mockAxios from '../__mocks__/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { shallowMount } from '@vue/test-utils'
import { CONVERSATION } from '../constants'
import Button from '@nextcloud/vue/dist/Components/Button'
import RoomSelector from './RoomSelector'

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
	})
	afterEach(() => {
		mockAxios.reset()
		jest.clearAllMocks()
	})

	test('renders sorted conversation list fetched from server', async () => {
		const wrapper = shallowMount(RoomSelector)

		expect(mockAxios.get).toHaveBeenCalledWith(
			generateOcsUrl('/apps/spreed/api/v4/room')
		)

		mockAxios.mockResponse({
			data: {
				ocs: {
					data: conversations,
				},
			},
		})

		// need to wait for re-render, otherwise the list is not rendered yet
		await wrapper.vm.$nextTick()

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

		expect(mockAxios.get).toHaveBeenCalledWith(
			generateOcsUrl('/apps/spreed/api/v4/room')
		)

		mockAxios.mockResponse({
			data: {
				ocs: {
					data: conversations,
				},
			},
		})

		// need to wait for re-render, otherwise the list is not rendered yet
		await wrapper.vm.$nextTick()

		const list = wrapper.findAll('li')
		expect(list.length).toBe(2)
		expect(list.at(0).text()).toBe('conversation one')
		expect(list.at(1).text()).toBe('zzz')
	})
	test('emits select event on select', async () => {
		const wrapper = shallowMount(RoomSelector)

		expect(mockAxios.get).toHaveBeenCalledWith(
			generateOcsUrl('/apps/spreed/api/v4/room')
		)

		mockAxios.mockResponse({
			data: {
				ocs: {
					data: conversations,
				},
			},
		})

		await wrapper.vm.$nextTick()

		const eventHandler = jest.fn()
		wrapper.vm.$root.$on('select', eventHandler)

		const list = wrapper.findAll('li')
		await list.at(1).trigger('click')
		await wrapper.findComponent(Button).vm.$emit('click')

		expect(eventHandler).toHaveBeenCalledWith('token-3')
	})

	test('emits close event', async () => {
		const wrapper = shallowMount(RoomSelector)

		const eventHandler = jest.fn()
		wrapper.vm.$root.$on('close', eventHandler)

		await wrapper.findComponent({ name: 'Modal' }).vm.$emit('close')

		expect(eventHandler).toHaveBeenCalled()
	})
})
