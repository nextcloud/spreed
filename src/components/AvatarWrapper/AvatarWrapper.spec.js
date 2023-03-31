import { mount, shallowMount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import Vuex from 'vuex'

import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'

import AvatarWrapper from './AvatarWrapper.vue'

import storeConfig from '../../store/storeConfig.js'

describe('AvatarWrapper.vue', () => {
	let testStoreConfig
	let store
	const USER_ID = 'user-id'
	const USER_NAME = 'John Doe'
	const preloadedUserStatus = { status: 'online', message: null, icon: null }

	beforeEach(() => {
		testStoreConfig = cloneDeep(storeConfig)
		store = new Vuex.Store(testStoreConfig)
	})

	describe('render user avatar', () => {
		test('component renders NcAvatar with standard size by default', () => {
			const wrapper = shallowMount(AvatarWrapper, {
				store,
				propsData: {
					name: USER_NAME,
				},
			})

			const avatar = wrapper.findComponent(NcAvatar)
			expect(avatar.exists()).toBeTruthy()
			expect(avatar.props('size')).toBe(44)
		})

		test('component does not render NcAvatar for non-users', () => {
			const wrapper = shallowMount(AvatarWrapper, {
				store,
				propsData: {
					name: 'emails',
					source: 'emails',
				},
			})

			const avatar = wrapper.findComponent(NcAvatar)
			expect(avatar.exists()).toBeFalsy()
		})

		test('component renders NcAvatar with smaller size', () => {
			const wrapper = shallowMount(AvatarWrapper, {
				store,
				propsData: {
					name: USER_NAME,
					small: true,
				},
			})

			const avatar = wrapper.findComponent(NcAvatar)
			expect(avatar.props('size')).toBe(22)
		})

		test('component renders user name and status with accepted properties', async () => {
			const wrapper = mount(AvatarWrapper, {
				store,
				propsData: {
					id: USER_ID,
					name: USER_NAME,
					showUserStatus: true,
					preloadedUserStatus,
				},
			})

			const avatar = wrapper.findComponent(NcAvatar)
			await avatar.vm.$nextTick()

			expect(avatar.attributes('title')).toBe(USER_NAME)
			expect(avatar.attributes('aria-label')).toContain(USER_NAME)
			const status = wrapper.findComponent('.avatardiv__user-status--online')
			expect(status.exists()).toBeTruthy()
		})
	})

	describe('render specific icons', () => {
		test('component render emails icon properly', () => {
			const wrapper = shallowMount(AvatarWrapper, {
				store,
				propsData: {
					name: 'emails',
					source: 'emails',
				},
			})

			const icon = wrapper.findComponent('.icon')
			expect(icon.exists()).toBeTruthy()
			expect(icon.classes('icon-mail')).toBeTruthy()
		})

		test('component render groups icon properly', () => {
			const wrapper = shallowMount(AvatarWrapper, {
				store,
				propsData: {
					name: 'groups',
					source: 'groups',
				},
			})

			const icon = wrapper.findComponent('.icon')
			expect(icon.exists()).toBeTruthy()
			expect(icon.classes('icon-contacts')).toBeTruthy()
		})
	})

	describe('render guests', () => {
		test('component render icon of guest properly', () => {
			const wrapper = shallowMount(AvatarWrapper, {
				store,
				propsData: {
					name: t('spreed', 'Guest'),
					source: 'guests',
				},
			})

			const guest = wrapper.findComponent('.guest')
			expect(guest.exists()).toBeTruthy()
			expect(guest.text()).toBe('?')
		})

		test('component render icon of guest with name properly', () => {
			const wrapper = shallowMount(AvatarWrapper, {
				store,
				propsData: {
					name: USER_NAME,
					source: 'guests',
				},
			})

			const guest = wrapper.findComponent('.guest')
			expect(guest.text()).toBe(USER_NAME.charAt(0))
		})

		test('component render icon of deleted user properly', () => {
			const wrapper = shallowMount(AvatarWrapper, {
				store,
				propsData: {
					name: USER_NAME,
					source: 'deleted_users',
				},
			})

			const deleted = wrapper.findComponent('.guest')
			expect(deleted.exists()).toBeTruthy()
			expect(deleted.text()).toBe('X')
		})
	})
})
