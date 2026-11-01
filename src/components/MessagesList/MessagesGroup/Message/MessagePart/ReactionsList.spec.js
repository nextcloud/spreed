/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, test } from 'vitest'
import NcButton from '@nextcloud/vue/components/NcButton'
import ReactionsList from './ReactionsList.vue'
import { ATTENDEE } from '../../../../../constants.ts'

describe('ReactionsList.vue', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

	test('shows emoji names as titles', () => {
		const actor = { actorId: 'alice', actorType: ATTENDEE.ACTOR_TYPE.USERS, actorDisplayName: 'Alice' }
		const wrapper = mount(ReactionsList, {
			props: {
				token: 'XXTOKENXX',
				detailedReactions: { '😅': [actor], '🔥': [actor] },
				reactionsSorted: ['😅', '🔥'],
			},
			global: {
				stubs: {
					NcDialog: { template: '<div><slot /></div>' },
					AvatarWrapper: true,
				},
			},
		})

		const tabs = wrapper.findAllComponents(NcButton)
		expect(tabs.at(1).attributes('title')).toBe(':sweat_smile:')
		expect(tabs.at(2).attributes('title')).toBe(':fire:')

		const emojis = wrapper.findAll('.reactions-item__emojis > span')
		expect(emojis.map((emoji) => emoji.attributes('title'))).toEqual([':sweat_smile:', ':fire:'])
	})
})
