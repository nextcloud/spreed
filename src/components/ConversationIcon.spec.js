/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ConversationIcon from './ConversationIcon.vue'
import { CONVERSATION } from '../constants.ts'

/**
 * @param {object} item conversation
 */
function mountIcon(item) {
	return mount(ConversationIcon, {
		props: {
			item: {
				token: 'abc',
				type: CONVERSATION.TYPE.GROUP,
				displayName: 'Conversation',
				objectType: '',
				attributes: 0,
				isDummyConversation: true,
				...item,
			},
		},
	})
}

describe('ConversationIcon.vue', () => {
	it('shows no badge for a group conversation', () => {
		const wrapper = mountIcon({})
		expect(wrapper.findAll('.conversation-icon__type')).toHaveLength(0)
	})

	it('shows the team badge for a team conversation', () => {
		const wrapper = mountIcon({ objectType: CONVERSATION.OBJECT_TYPE.TEAM })
		const badges = wrapper.findAll('.conversation-icon__type')
		expect(badges).toHaveLength(1)
		expect(badges[0].classes()).toContain('conversation-icon__type--team')
		expect(badges[0].attributes('aria-label')).toBe('Team conversation')
	})

	it('shows the team and the federated badge for a federated team conversation', () => {
		// The copy on another server only has the TEAM attribute
		const wrapper = mountIcon({ remoteServer: 'https://host.example', attributes: CONVERSATION.ATTRIBUTE.TEAM })
		const labels = wrapper.findAll('.conversation-icon__type').map((badge) => badge.attributes('aria-label'))
		expect(labels).toEqual(['Federated conversation', 'Team conversation'])
	})

	it('hides the badges together with the user status', () => {
		const wrapper = mount(ConversationIcon, {
			props: {
				hideUserStatus: true,
				item: { token: 'abc', type: CONVERSATION.TYPE.GROUP, displayName: 'Team', objectType: CONVERSATION.OBJECT_TYPE.TEAM, attributes: 0, isDummyConversation: true },
			},
		})
		expect(wrapper.findAll('.conversation-icon__type')).toHaveLength(0)
	})
})
