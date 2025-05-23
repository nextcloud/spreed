import { t } from '@nextcloud/l10n'
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { shallowMount } from '@vue/test-utils'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import AvatarWrapper from './AvatarWrapper.vue'
import { ATTENDEE, AVATAR } from '../../constants.ts'

describe('AvatarWrapper.vue', () => {
	const USER_ID = 'user-id'
	const USER_NAME = 'John Doe'
	const PRELOADED_USER_STATUS = { status: 'online', message: null, icon: null }

	describe('render user avatar', () => {
		test('component renders NcAvatar with standard size by default', () => {
			const wrapper = shallowMount(AvatarWrapper, {
				propsData: {
					name: USER_NAME,
				},
			})

			const avatar = wrapper.findComponent(NcAvatar)
			expect(avatar.exists()).toBeTruthy()
			expect(avatar.props('size')).toBe(AVATAR.SIZE.DEFAULT)
		})

		test('component does not render NcAvatar for non-users', () => {
			const wrapper = shallowMount(AvatarWrapper, {
				propsData: {
					name: 'Email Guest',
					source: ATTENDEE.ACTOR_TYPE.EMAILS,
				},
			})

			const avatar = wrapper.findComponent(NcAvatar)
			expect(avatar.exists()).toBeFalsy()
		})

		test('component does not render NcAvatar for federated users', () => {
			const wrapper = shallowMount(AvatarWrapper, {
				propsData: {
					token: 'XXXTOKENXXX',
					name: 'Federated User',
					source: ATTENDEE.ACTOR_TYPE.FEDERATED_USERS,
				},
			})

			const avatar = wrapper.findComponent(NcAvatar)
			expect(avatar.exists()).toBeFalsy()
		})

		test('component renders NcAvatar with specified size', () => {
			const size = 22
			const wrapper = shallowMount(AvatarWrapper, {
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
				propsData: {
					id: USER_ID,
					name: USER_NAME,
					source: ATTENDEE.ACTOR_TYPE.USERS,
					showUserStatus: true,
					preloadedUserStatus: PRELOADED_USER_STATUS,
				},
			})

			const avatar = wrapper.findComponent(NcAvatar)
			await avatar.vm.$nextTick()

			expect(avatar.props('user')).toBe(USER_ID)
			expect(avatar.props('displayName')).toBe(USER_NAME)
			expect(avatar.props('hideStatus')).toBe(false)
			expect(avatar.props('verboseStatus')).toBe(true)
			expect(avatar.props('preloadedUserStatus')).toBe(PRELOADED_USER_STATUS)
			expect(avatar.props('size')).toBe(AVATAR.SIZE.DEFAULT)
		})
	})

	describe('render specific icons', () => {
		const testCases = [
			[null, ATTENDEE.CHANGELOG_BOT_ID, 'Talk updates', ATTENDEE.ACTOR_TYPE.BOTS, 'icon-changelog'],
			[null, ATTENDEE.SAMPLE_BOT_ID, 'Nextcloud', ATTENDEE.ACTOR_TYPE.BOTS, 'icon-changelog'],
			[null, 'federated_user/id', USER_NAME, ATTENDEE.ACTOR_TYPE.FEDERATED_USERS, 'icon-user'],
			[null, 'guest/id', '', ATTENDEE.ACTOR_TYPE.GUESTS, 'icon-user'],
			[null, 'guest/id', t('spreed', 'Guest'), ATTENDEE.ACTOR_TYPE.GUESTS, 'icon-user'],
			[null, 'guest/id', t('spreed', 'Guest'), ATTENDEE.ACTOR_TYPE.EMAILS, 'icon-user'],
			[null, 'deleted_users', '', ATTENDEE.ACTOR_TYPE.DELETED_USERS, 'icon-user'],
			['new', 'guest/id', 'test@mail.com', ATTENDEE.ACTOR_TYPE.EMAILS, 'icon-mail'],
			[null, 'sha-phone', '+12345...', ATTENDEE.ACTOR_TYPE.PHONES, 'icon-phone'],
			[null, 'team/id', 'Team', ATTENDEE.ACTOR_TYPE.CIRCLES, 'icon-team'],
			[null, 'group/id', 'Group', ATTENDEE.ACTOR_TYPE.GROUPS, 'icon-contacts'],
		]

		it.each(testCases)('renders for token \'%s\', id \'%s\', name \'%s\' and source \'%s\' icon \'%s\'', (token, id, name, source, result) => {
			const wrapper = shallowMount(AvatarWrapper, {
				propsData: { token, id, name, source },
			})

			const avatar = wrapper.find('.avatar')
			expect(avatar.exists()).toBeTruthy()
			expect(avatar.classes(result)).toBeTruthy()
		})
	})

	describe('render specific symbols', () => {
		const testCases = [
			['guest/id', USER_NAME, ATTENDEE.ACTOR_TYPE.GUESTS, USER_NAME.charAt(0)],
			['guest/id', USER_NAME, ATTENDEE.ACTOR_TYPE.EMAILS, USER_NAME.charAt(0)],
			['bot-id', USER_NAME, ATTENDEE.ACTOR_TYPE.BOTS, '>_'],
		]

		it.each(testCases)('renders for id \'%s\', name \'%s\' and source \'%s\' symbol \'%s\'', (id, name, source, result) => {
			const wrapper = shallowMount(AvatarWrapper, {
				propsData: { name, source },
			})

			const avatar = wrapper.find('.avatar')
			expect(avatar.exists()).toBeTruthy()
			expect(avatar.text()).toBe(result)
		})
	})
})
