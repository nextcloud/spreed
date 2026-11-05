/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { emit } from '@nextcloud/event-bus'
import { mount } from '@vue/test-utils'
import { afterAll, afterEach, describe, expect, test, vi } from 'vitest'
import { useInterceptNotifications } from '../useInterceptNotifications.ts'

const router = { push: vi.fn(() => new Promise(() => {})) }

vi.mock('vue-router', () => ({ useRouter: () => router }))
vi.mock('vuex', () => ({ useStore: () => ({}) }))
vi.mock('../../stores/federation.ts', () => ({ useFederationStore: () => ({}) }))

describe('useInterceptNotifications', () => {
	const wrapper = mount({
		setup() {
			useInterceptNotifications()
			return () => null
		},
	})

	afterAll(() => {
		wrapper.unmount()
	})

	afterEach(() => {
		router.push.mockClear()
	})

	test('cancels a conversation link synchronously, before navigation finishes', () => {
		const event = {
			cancelAction: false,
			notification: { app: 'spreed' },
			action: { type: 'WEB', url: 'https://cloud.example.com/index.php/call/abc123#message_42' },
		}
		emit('notifications:action:execute', event)

		expect(event.cancelAction).toBe(true)
		expect(router.push).toHaveBeenCalledWith('/call/abc123#message_42')
	})

	test('keeps default behavior for links without a conversation', () => {
		const event = {
			cancelAction: false,
			notification: { app: 'spreed' },
			action: { type: 'WEB', url: 'https://cloud.example.com/index.php/settings/admin/talk' },
		}
		emit('notifications:action:execute', event)

		expect(event.cancelAction).toBe(false)
		expect(router.push).not.toHaveBeenCalled()
	})

	test('ignores notifications of other apps', () => {
		const event = {
			cancelAction: false,
			notification: { app: 'files' },
			action: { type: 'WEB', url: 'https://cloud.example.com/index.php/call/abc123' },
		}
		emit('notifications:action:execute', event)

		expect(event.cancelAction).toBe(false)
	})
})
