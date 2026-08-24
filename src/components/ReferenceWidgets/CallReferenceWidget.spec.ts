/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, test, vi } from 'vitest'
import ConversationIcon from '../ConversationIcon.vue'
import { CONVERSATION, MESSAGE } from '../../constants.ts'
import { fetchConversation } from '../../services/conversationsService.ts'
import CallReferenceWidget from './CallReferenceWidget.vue'

vi.mock('../../services/conversationsService.ts', () => ({
	fetchConversation: vi.fn(),
}))

// ConversationIcon reads capabilities/cached conversations from BrowserStorage at import time
vi.mock('../../services/CapabilitiesManager.ts', () => ({
	hasTalkFeature: vi.fn(() => false),
	getTalkConfig: vi.fn(),
}))

describe('CallReferenceWidget.vue', () => {
	const richObject = {
		id: 'XXTOKENXX',
		name: 'Fallback conversation name',
		link: 'https://nextcloud.local/call/XXTOKENXX',
		'call-type': 'group',
	}

	/**
	 * @param props additional props to merge on top of the defaults
	 */
	function mountWidget(props = {}) {
		return mount(CallReferenceWidget, {
			props: {
				richObject,
				accessible: true,
				...props,
			},
		})
	}

	test('renders nothing when the reference is not accessible', async () => {
		const wrapper = mountWidget({ accessible: false })
		await flushPromises()

		expect(wrapper.find('a').exists()).toBe(false)
		expect(fetchConversation).not.toHaveBeenCalled()
	})

	test('renders the live conversation once loaded', async () => {
		vi.mocked(fetchConversation).mockResolvedValueOnce({
			data: {
				ocs: {
					data: {
						token: 'XXTOKENXX',
						displayName: 'Live conversation name',
						type: CONVERSATION.TYPE.GROUP,
					},
				},
			},
		})

		const wrapper = mountWidget()
		await flushPromises()

		expect(fetchConversation).toHaveBeenCalledWith('XXTOKENXX')
		expect(wrapper.text()).toContain('Live conversation name')
		expect(wrapper.findComponent(ConversationIcon).exists()).toBe(true)
	})

	test('falls back to the reference metadata when the live fetch fails', async () => {
		vi.mocked(fetchConversation).mockRejectedValueOnce(new Error('403'))

		const wrapper = mountWidget({ fallbackAvatarUrl: 'https://nextcloud.local/avatar.png' })
		await flushPromises()

		expect(wrapper.text()).toContain('Fallback conversation name')
		expect(wrapper.findComponent(ConversationIcon).exists()).toBe(false)
		expect(wrapper.find('img').attributes('src')).toBe('https://nextcloud.local/avatar.png')
	})

	test('does not show an expired last message', async () => {
		vi.mocked(fetchConversation).mockResolvedValueOnce({
			data: {
				ocs: {
					data: {
						token: 'XXTOKENXX',
						displayName: 'Live conversation name',
						type: CONVERSATION.TYPE.GROUP,
						lastMessage: {
							actorDisplayName: 'Alice',
							actorType: 'users',
							message: 'hello',
							messageParameters: {},
							messageType: 'comment',
							systemMessage: '',
							expirationTimestamp: 1,
						},
					},
				},
			},
		})

		const wrapper = mountWidget()
		await flushPromises()

		expect(wrapper.text()).not.toContain('hello')
	})

	test('does not show a deleted last message', async () => {
		vi.mocked(fetchConversation).mockResolvedValueOnce({
			data: {
				ocs: {
					data: {
						token: 'XXTOKENXX',
						displayName: 'Live conversation name',
						type: CONVERSATION.TYPE.GROUP,
						lastMessage: {
							actorDisplayName: 'Alice',
							actorType: 'users',
							message: 'hello',
							messageParameters: {},
							messageType: MESSAGE.TYPE.COMMENT_DELETED,
							systemMessage: '',
							expirationTimestamp: 0,
						},
					},
				},
			},
		})

		const wrapper = mountWidget()
		await flushPromises()

		expect(wrapper.text()).not.toContain('hello')
	})

	test('shows a non-expired last message with its actor', async () => {
		vi.mocked(fetchConversation).mockResolvedValueOnce({
			data: {
				ocs: {
					data: {
						token: 'XXTOKENXX',
						displayName: 'Live conversation name',
						type: CONVERSATION.TYPE.GROUP,
						lastMessage: {
							actorDisplayName: 'Alice',
							actorType: 'users',
							message: 'hello',
							messageParameters: {},
							messageType: 'comment',
							systemMessage: '',
							expirationTimestamp: 0,
						},
					},
				},
			},
		})

		const wrapper = mountWidget()
		await flushPromises()

		expect(wrapper.text()).toContain('Alice: hello')
	})
})
