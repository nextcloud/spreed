/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, test, vi } from 'vitest'
import ConversationIcon from '../ConversationIcon.vue'
import CallReferenceWidget from './CallReferenceWidget.vue'
import { CONVERSATION, MESSAGE } from '../../constants.ts'
import { fetchConversation } from '../../services/conversationsService.ts'

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
						description: 'A useful room description',
						unreadMessages: 2,
						hasCall: true,
						lastMessage: {
							timestamp: 1710000000,
						},
					},
				},
			},
		})

		const wrapper = mountWidget()
		await flushPromises()

		expect(fetchConversation).toHaveBeenCalledWith('XXTOKENXX')
		expect(wrapper.text()).toContain('Live conversation name')
		expect(wrapper.text()).toContain('Group conversation')
		expect(wrapper.text()).toContain('A useful room description')
		expect(wrapper.text()).toContain('2 unread messages')
		expect(wrapper.text()).toContain('Call in progress')
		expect(wrapper.findComponent(ConversationIcon).exists()).toBe(true)
	})

	test('renders a message reference using the provider metadata', async () => {
		vi.mocked(fetchConversation).mockResolvedValueOnce({
			data: {
				ocs: {
					data: {
						token: 'XXTOKENXX',
						displayName: 'Live conversation name',
						type: CONVERSATION.TYPE.GROUP,
						lastMessage: {
							actorDisplayName: 'Latest actor',
							actorType: 'users',
							message: 'latest message',
							messageParameters: {},
							messageType: 'comment',
							systemMessage: '',
							expirationTimestamp: 0,
						},
					},
				},
			},
		})

		const wrapper = mountWidget({
			richObject: { ...richObject, 'message-id': '42' },
			referenceTitle: 'Alice in Project room',
			referenceDescription: 'A message from Alice',
		})
		await flushPromises()

		expect(wrapper.text()).toContain('Alice in Project room')
		expect(wrapper.text()).toContain('A message from Alice')
		expect(wrapper.text()).toContain('Message')
		expect(wrapper.text()).not.toContain('Latest actor: latest message')
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
