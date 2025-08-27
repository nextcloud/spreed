/*
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, test, vi } from 'vitest'
import TrackEnabler from './TrackEnabler.js'

/**
 * Helper function to create MediaStreamTrack mocks with just the attributes and
 * methods used by TrackEnabler.
 *
 * @param {string} id the ID of the track
 */
function newMediaStreamTrackMock(id) {
	/**
	 * MediaStreamTrackMock constructor.
	 */
	function MediaStreamTrackMock() {
		this.id = id
		this.enabled = true
		// "ended" event is not being tested, so there is no need to add even a
		// stub for the event listener methods.
		this.addEventListener = vi.fn()
		this.removeEventListener = vi.fn()
	}
	return new MediaStreamTrackMock()
}

describe('TrackToStream', () => {
	let trackEnabler
	let outputTrackSetHandler
	let outputTrackEnabledHandler
	let expectedTrackEnabledStateInOutputTrackSetEvent

	beforeEach(() => {
		trackEnabler = new TrackEnabler()

		expectedTrackEnabledStateInOutputTrackSetEvent = undefined

		outputTrackSetHandler = vi.fn((trackEnabler, trackId, track) => {
			if (expectedTrackEnabledStateInOutputTrackSetEvent !== undefined) {
				expect(track.enabled).toBe(expectedTrackEnabledStateInOutputTrackSetEvent)
			}
		})
		outputTrackEnabledHandler = vi.fn()

		trackEnabler.on('outputTrackSet', outputTrackSetHandler)
		trackEnabler.on('outputTrackEnabled', outputTrackEnabledHandler)
	})

	test('is enabled by default', () => {
		expect(trackEnabler.isEnabled()).toBe(true)
	})

	describe('enable/disable node', () => {
		test('does nothing if disabled when there is no input track', () => {
			trackEnabler.setEnabled(false)

			expect(trackEnabler.isEnabled()).toBe(false)
			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
		})

		test('does nothing if enabled when there is no input track', () => {
			trackEnabler.setEnabled(false)
			trackEnabler.setEnabled(true)

			expect(trackEnabler.isEnabled()).toBe(true)
			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
		})
	})

	describe('set input track', () => {
		test('sets enabled input track as its output track', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			trackEnabler._setInputTrack('default', inputTrack)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(trackEnabler, 'default', inputTrack)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(inputTrack.enabled).toBe(true)
		})

		test('sets disabled input track as its output track', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			trackEnabler.setEnabled(false)

			expectedTrackEnabledStateInOutputTrackSetEvent = false

			inputTrack.enabled = false
			trackEnabler._setInputTrack('default', inputTrack)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(trackEnabler, 'default', inputTrack)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(inputTrack.enabled).toBe(false)
		})

		test('sets disabled input track as its output track enabling it when node is enabled', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			inputTrack.enabled = false
			trackEnabler._setInputTrack('default', inputTrack)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(trackEnabler, 'default', inputTrack)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(inputTrack.enabled).toBe(true)
		})

		test('sets enabled input track as its output track disabling it when node is disabled', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			trackEnabler.setEnabled(false)

			expectedTrackEnabledStateInOutputTrackSetEvent = false

			inputTrack.enabled = true
			trackEnabler._setInputTrack('default', inputTrack)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(trackEnabler, 'default', inputTrack)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(inputTrack.enabled).toBe(false)
		})
	})

	describe('enable/disable node after setting input track', () => {
		test('enables input track when node is enabled', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			trackEnabler.setEnabled(false)

			inputTrack.enabled = false
			trackEnabler._setInputTrack('default', inputTrack)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			trackEnabler.setEnabled(true)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackEnabledHandler).toHaveBeenCalledWith(trackEnabler, 'default', true)
			expect(inputTrack.enabled).toBe(true)
		})

		test('disables input track when node is disabled', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			trackEnabler._setInputTrack('default', inputTrack)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			trackEnabler.setEnabled(false)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackEnabledHandler).toHaveBeenCalledWith(trackEnabler, 'default', false)
			expect(inputTrack.enabled).toBe(false)
		})
	})

	describe('enable/disable input track', () => {
		test('enables input track again if input track is disabled when node is enabled', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			trackEnabler._setInputTrack('default', inputTrack)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			inputTrack.enabled = false
			trackEnabler._setInputTrackEnabled('default', false)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackEnabledHandler).toHaveBeenCalledWith(trackEnabler, 'default', true)
			expect(inputTrack.enabled).toBe(true)
		})

		test('disables input track again if input track is enabled when node is disabled', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			trackEnabler.setEnabled(false)

			inputTrack.enabled = false
			trackEnabler._setInputTrack('default', inputTrack)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			inputTrack.enabled = true
			trackEnabler._setInputTrackEnabled('default', true)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackEnabledHandler).toHaveBeenCalledWith(trackEnabler, 'default', false)
			expect(inputTrack.enabled).toBe(false)
		})

		test('does nothing if input track is enabled again when node is enabled', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			trackEnabler._setInputTrack('default', inputTrack)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			trackEnabler._setInputTrackEnabled('default', true)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(inputTrack.enabled).toBe(true)
		})

		test('does nothing if input track is disabled again when node is disabled', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			trackEnabler.setEnabled(false)

			inputTrack.enabled = false
			trackEnabler._setInputTrack('default', inputTrack)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			trackEnabler._setInputTrackEnabled('default', false)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(inputTrack.enabled).toBe(false)
		})
	})

	describe('remove input track', () => {
		test('removes output track when removing input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			trackEnabler._setInputTrack('default', inputTrack)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			trackEnabler._setInputTrack('default', null)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(trackEnabler, 'default', null)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
		})
	})

	describe('update input track', () => {
		test('sets input track as its output track when setting same enabled input track again', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			trackEnabler._setInputTrack('default', inputTrack)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			trackEnabler._setInputTrack('default', inputTrack)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(trackEnabler, 'default', inputTrack)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(inputTrack.enabled).toBe(true)
		})

		test('sets input track as its output track when setting same disabled input track again', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			trackEnabler.setEnabled(false)

			inputTrack.enabled = false
			trackEnabler._setInputTrack('default', inputTrack)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = false

			trackEnabler._setInputTrack('default', inputTrack)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(trackEnabler, 'default', inputTrack)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(inputTrack.enabled).toBe(false)
		})

		test('sets input track as its output track enabling it when setting same now disabled input track again', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			trackEnabler._setInputTrack('default', inputTrack)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			inputTrack.enabled = false
			trackEnabler._setInputTrack('default', inputTrack)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(trackEnabler, 'default', inputTrack)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(inputTrack.enabled).toBe(true)
		})

		test('sets input track as its output track disabling it when setting same now enabled input track again', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			trackEnabler.setEnabled(false)

			inputTrack.enabled = false
			trackEnabler._setInputTrack('default', inputTrack)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = false

			inputTrack.enabled = true
			trackEnabler._setInputTrack('default', inputTrack)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(trackEnabler, 'default', inputTrack)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(inputTrack.enabled).toBe(false)
		})

		test('sets input track as its output track when setting another enabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')
			const inputTrack2 = newMediaStreamTrackMock('input2')

			trackEnabler._setInputTrack('default', inputTrack)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			trackEnabler._setInputTrack('default', inputTrack2)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(trackEnabler, 'default', inputTrack2)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(inputTrack.enabled).toBe(true)
		})

		test('sets input track as its output track when setting another disabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')
			const inputTrack2 = newMediaStreamTrackMock('input2')

			trackEnabler.setEnabled(false)

			inputTrack.enabled = false
			trackEnabler._setInputTrack('default', inputTrack)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = false

			inputTrack2.enabled = false
			trackEnabler._setInputTrack('default', inputTrack2)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(trackEnabler, 'default', inputTrack2)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(inputTrack.enabled).toBe(false)
		})

		test('sets input track as its output track enabling it when setting another now disabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')
			const inputTrack2 = newMediaStreamTrackMock('input2')

			trackEnabler._setInputTrack('default', inputTrack)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			inputTrack2.enabled = false
			trackEnabler._setInputTrack('default', inputTrack2)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(trackEnabler, 'default', inputTrack2)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(inputTrack.enabled).toBe(true)
		})

		test('sets input track as its output track disabling it when setting another now enabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')
			const inputTrack2 = newMediaStreamTrackMock('input2')

			trackEnabler.setEnabled(false)

			inputTrack.enabled = false
			trackEnabler._setInputTrack('default', inputTrack)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = false

			inputTrack2.enabled = true
			trackEnabler._setInputTrack('default', inputTrack2)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(trackEnabler, 'default', inputTrack2)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(inputTrack.enabled).toBe(false)
		})
	})
})
