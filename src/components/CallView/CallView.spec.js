/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import CallView from './CallView.vue'

vi.mock('@nextcloud/event-bus', () => ({
	EventBus: {
		on: vi.fn(),
		off: vi.fn(),
	},
	emit: vi.fn(),
	subscribe: vi.fn(),
	unsubscribe: vi.fn(),
}))

vi.mock('../../utils/webrtc/index.js', () => ({
	callParticipantCollection: {
		callParticipantModels: [],
		on: vi.fn(),
		off: vi.fn(),
	},
	localCallParticipantModel: {
		attributes: {
			peerId: 'local-peer',
		},
	},
	localMediaModel: {
		attributes: {
			localScreen: null,
			videoEnabled: false,
		},
		disableAudio: vi.fn(),
		disableVideo: vi.fn(),
	},
}))

describe('CallView.vue', () => {
	const TOKEN = 'XXTOKENXX'
	let wrapper

	beforeEach(() => {
		setActivePinia(createPinia())
	})

	afterEach(() => {
		wrapper?.unmount()
	})

	const mountCallView = (props = {}) => {
		return mount(CallView, {
			props: {
				token: TOKEN,
				...props,
			},
			global: {
				stubs: {
					ViewerOverlayCallView: true,
					EmptyCallView: true,
					VideoVue: true,
					LocalVideo: true,
					ScreenShare: true,
					PresenterOverlay: true,
					VideosGrid: true,
					ReactionToaster: true,
					LiveTranscriptionRenderer: true,
					BottomBar: true,
				},
			},
		})
	}

	describe('video overlay mouse/keyboard activity tracking', () => {
		beforeEach(() => {
			vi.useFakeTimers()
		})

		afterEach(() => {
			vi.restoreAllMocks()
			vi.useRealTimers()
		})

		test('showVideoOverlay becomes false after 5000ms of inactivity', async () => {
			wrapper = mountCallView()

			expect(wrapper.findComponent({ name: 'VideosGrid' }).props('showVideoOverlay')).toBe(true)

			// Trigger activity to start the timer
			await wrapper.find('div#call-container').trigger('mousemove')
			await flushPromises()

			vi.advanceTimersByTime(5000)
			await flushPromises()

			expect(wrapper.vm.showVideoOverlay).toBe(false)
		})

		test('handleMovement resets the timer', async () => {
			wrapper = mountCallView()

			// Start the initial timer
			expect(wrapper.vm.showVideoOverlay).toBe(true)

			// Advance 3 seconds
			vi.advanceTimersByTime(3000)
			await flushPromises()
			expect(wrapper.vm.showVideoOverlay).toBe(true)

			// Call handleMovement to reset timer
			wrapper.vm.handleMovement()
			await flushPromises()

			// Still visible
			expect(wrapper.vm.showVideoOverlay).toBe(true)

			// Advance another 3 seconds (6 total from start, but 3 from reset)
			vi.advanceTimersByTime(3000)
			await flushPromises()
			expect(wrapper.vm.showVideoOverlay).toBe(true)

			// Advance final 2 seconds (5 from the reset)
			vi.advanceTimersByTime(2000)
			await flushPromises()
			expect(wrapper.vm.showVideoOverlay).toBe(false)
		})

		test('handleMovement sets showVideoOverlay to true', async () => {
			wrapper = mountCallView()

			// Start the timer
			wrapper.vm.handleMovement()
			await flushPromises()

			// Let the overlay hide
			vi.advanceTimersByTime(5000)
			await flushPromises()
			expect(wrapper.vm.showVideoOverlay).toBe(false)

			// Call handleMovement again
			wrapper.vm.handleMovement()
			await flushPromises()

			expect(wrapper.vm.showVideoOverlay).toBe(true)
		})

		test('hideOverlay immediately sets showVideoOverlay to false', async () => {
			wrapper = mountCallView()

			expect(wrapper.vm.showVideoOverlay).toBe(true)

			await wrapper.find('div#call-container').trigger('mouseleave')
			await flushPromises()

			expect(wrapper.vm.showVideoOverlay).toBe(false)
		})
	})
})
