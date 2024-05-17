/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { shallowMount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import Vuex from 'vuex'

import { t } from '@nextcloud/l10n'

import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'

import AvatarWrapper from './AvatarWrapper.vue'

import storeConfig from '../../store/storeConfig.js'

describe('AvatarWrapper.vue', () => {
	let testStoreConfig
	let store
	const USER_ID = 'user-id'
	const USER_NAME = 'John Doe'
	const PRELOADED_USER_STATUS = { status: 'online', message: null, icon: null }
	const MENU_CONTAINER = '#menu-container'

	beforeEach(() => {
		testStoreConfig = cloneDeep(storeConfig)
		testStoreConfig.modules.uiModeStore.getters.getMainContainerSelector = jest.fn().mockReturnValue(() => MENU_CONTAINER)
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

		test('component renders NcAvatar with specified size', () => {
			const size = 22
			const wrapper = shallowMount(AvatarWrapper, {
				store,
				propsData: {
					name: USER_NAME,
					size,
				},
			})

			const avatar = wrapper.findComponent(NcAvatar)
			expect(avatar.props('size')).toBe(size)
		})

		test('component pass props to NcAvatar correctly', async () => {
			const wrapper = shallowMount(AvatarWrapper, {
				store,
				propsData: {
					id: USER_ID,
					name: USER_NAME,
					showUserStatus: true,
					preloadedUserStatus: PRELOADED_USER_STATUS,
				},
			})

			const avatar = wrapper.findComponent(NcAvatar)
			await avatar.vm.$nextTick()

			expect(avatar.props('user')).toBe(USER_ID)
			expect(avatar.props('displayName')).toBe(USER_NAME)
			expect(avatar.props('menuContainer')).toBe(MENU_CONTAINER)
			expect(avatar.props('showUserStatus')).toBe(true)
			expect(avatar.props('showUserStatusCompact')).toBe(false)
			expect(avatar.props('preloadedUserStatus')).toBe(PRELOADED_USER_STATUS)
			expect(avatar.props('size')).toBe(44)
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

			const icon = wrapper.find('.icon')
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

			const icon = wrapper.find('.icon')
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

			const guest = wrapper.find('.guest')
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

			const guest = wrapper.find('.guest')
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

			const deleted = wrapper.find('.guest')
			expect(deleted.exists()).toBeTruthy()
			expect(deleted.text()).toBe('X')
		})
	})
})
