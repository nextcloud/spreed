/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import { ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import LocalAudioControlButton from './LocalAudioControlButton.vue'
import { PARTICIPANT } from '../../../constants.ts'
import { localCallParticipantModel } from '../../../utils/webrtc/index.js'

vi.mock('../../../utils/webrtc/index.js', () => {
	const handlers = {}
	return {
		localCallParticipantModel: {
			on: vi.fn((event, handler) => { handlers[event] = handler }),
			off: vi.fn(),
			trigger: (event) => handlers[event]?.(),
		},
	}
})

vi.mock('../../../composables/useDevices.js', () => ({
	useDevices: () => ({
		devices: ref([]),
		audioInputId: ref(null),
		audioOutputId: ref(null),
		audioStreamError: ref(null),
		updateDevices: vi.fn(),
		audioOutputSupported: ref(false),
		updatePreferences: vi.fn(),
		subscribeToDevices: vi.fn(),
		unsubscribeFromDevices: vi.fn(),
		updateSubscriptionPermissions: vi.fn(),
	}),
}))

describe('LocalAudioControlButton.vue', () => {
	let model

	beforeEach(() => {
		vi.useFakeTimers()
		model = {
			attributes: {
				audioAvailable: true,
				audioEnabled: false,
				currentVolume: 0,
				volumeThreshold: 0,
			},
			enableAudio: vi.fn(),
			disableAudio: vi.fn(),
		}
	})

	afterEach(() => {
		vi.useRealTimers()
	})

	test('blocks toggling audio for a while after a forced mute', async () => {
		const wrapper = mount(LocalAudioControlButton, {
			props: {
				token: 'XXTOKENXX',
				conversation: { permissions: PARTICIPANT.PERMISSIONS.PUBLISH_AUDIO },
				model,
				disableMutedWarning: true,
			},
		})

		localCallParticipantModel.trigger('forcedMute')
		await wrapper.vm.$nextTick()

		expect(wrapper.findComponent(NcButton).props('disabled')).toBe(true)
		wrapper.vm.toggleAudio()
		expect(model.enableAudio).not.toHaveBeenCalled()

		vi.advanceTimersByTime(3000)
		await wrapper.vm.$nextTick()

		expect(wrapper.findComponent(NcButton).props('disabled')).toBe(false)
		wrapper.vm.toggleAudio()
		expect(model.enableAudio).toHaveBeenCalled()
	})
})
