/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { Sounds } from '../sounds.js'

const soundsStore = vi.hoisted(() => ({
	shouldPlaySounds: true,
	playAudio: vi.fn(),
	pauseAudio: vi.fn(),
}))

vi.mock('../../stores/sounds.js', () => ({
	useSoundsStore: () => soundsStore,
}))
vi.mock('../../store/index.js', () => ({
	default: { getters: { conversation: vi.fn() } },
}))

describe('Sounds', () => {
	describe('playRaiseHand', () => {
		beforeEach(() => {
			vi.useFakeTimers()
			vi.setSystemTime(100000)
			vi.clearAllMocks()
			soundsStore.shouldPlaySounds = true
			Sounds.lastPlayedRaiseHand = 0
		})

		afterEach(() => {
			vi.useRealTimers()
		})

		it('plays the raise hand sound', () => {
			Sounds.playRaiseHand()
			expect(soundsStore.playAudio).toHaveBeenCalledWith('raiseHand')
		})

		it('does not play when sounds are disabled', () => {
			soundsStore.shouldPlaySounds = false
			Sounds.playRaiseHand()
			expect(soundsStore.playAudio).not.toHaveBeenCalled()
		})

		it('does not play again within the block timeout', () => {
			Sounds.playRaiseHand()
			vi.advanceTimersByTime(Sounds.BLOCK_SOUND_TIMEOUT - 1)
			Sounds.playRaiseHand()
			expect(soundsStore.playAudio).toHaveBeenCalledTimes(1)
		})

		it('plays again after the block timeout', () => {
			Sounds.playRaiseHand()
			vi.advanceTimersByTime(Sounds.BLOCK_SOUND_TIMEOUT + 1)
			Sounds.playRaiseHand()
			expect(soundsStore.playAudio).toHaveBeenCalledTimes(2)
		})
	})
})
