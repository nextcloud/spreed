/*
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { vi } from 'vitest'
import TrackToStream from './TrackToStream.js'

/**
 * Helper function to create MediaStreamTrack mocks with just the attributes
 * used by TrackToStream.
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
	}
	return new MediaStreamTrackMock()
}

describe('TrackToStream', () => {
	let trackToStream
	let streamSetHandler
	let trackReplacedHandler
	let trackEnabledHandler

	beforeAll(() => {
		// MediaStream is used in TrackToStream but not implemented in jsdom, so
		// a stub is needed.
		window.MediaStream = function() {
			this._tracks = []

			this.addTrack = vi.fn((track) => {
				if (this._tracks.includes(track)) {
					console.error('Tried to add again track already added to stream')
					return
				}
				this._tracks.push(track)
			})
			this.removeTrack = vi.fn((track) => {
				const index = this._tracks.indexOf(track)
				if (index < 0) {
					console.error('Tried to delete track not added to stream')
					return
				}
				this._tracks.splice(index, 1)
			})
			this.getTracks = vi.fn(() => {
				return this._tracks
			})
		}
	})

	beforeEach(() => {
		trackToStream = new TrackToStream()
		trackToStream.addInputTrackSlot('audio')
		trackToStream.addInputTrackSlot('video')

		streamSetHandler = vi.fn()
		trackReplacedHandler = vi.fn()
		trackEnabledHandler = vi.fn()

		trackToStream.on('streamSet', streamSetHandler)
		trackToStream.on('trackReplaced', trackReplacedHandler)
		trackToStream.on('trackEnabled', trackEnabledHandler)
	})

	test('has no stream by default', () => {
		expect(trackToStream.getStream()).toBe(null)
	})

	describe('set input track', () => {
		test('creates stream and adds track when setting first input track', () => {
			const audioTrack = newMediaStreamTrackMock('audio')

			trackToStream._setInputTrack('audio', audioTrack)

			expect(trackToStream.getStream()).not.toBe(null)
			expect(trackToStream.getStream().getTracks().length).toBe(1)
			expect(trackToStream.getStream().getTracks()).toContain(audioTrack)
			expect(streamSetHandler).toHaveBeenCalledTimes(1)
			expect(streamSetHandler).toHaveBeenCalledWith(trackToStream, trackToStream.getStream(), null)
			expect(trackReplacedHandler).toHaveBeenCalledTimes(1)
			expect(trackReplacedHandler).toHaveBeenCalledWith(trackToStream, audioTrack, null)
			expect(trackEnabledHandler).toHaveBeenCalledTimes(0)
		})

		test('adds track to existing stream when setting second input track', () => {
			const audioTrack = newMediaStreamTrackMock('audio')
			const videoTrack = newMediaStreamTrackMock('video')

			trackToStream._setInputTrack('audio', audioTrack)

			const stream = trackToStream.getStream()

			trackToStream._setInputTrack('video', videoTrack)

			expect(trackToStream.getStream()).not.toBe(null)
			expect(trackToStream.getStream()).toBe(stream)
			expect(trackToStream.getStream().getTracks().length).toBe(2)
			expect(trackToStream.getStream().getTracks()).toContain(audioTrack)
			expect(trackToStream.getStream().getTracks()).toContain(videoTrack)
			expect(streamSetHandler).toHaveBeenCalledTimes(1)
			expect(streamSetHandler).toHaveBeenCalledWith(trackToStream, trackToStream.getStream(), null)
			expect(trackReplacedHandler).toHaveBeenCalledTimes(2)
			expect(trackReplacedHandler).toHaveBeenNthCalledWith(1, trackToStream, audioTrack, null)
			expect(trackReplacedHandler).toHaveBeenNthCalledWith(2, trackToStream, videoTrack, null)
			expect(trackEnabledHandler).toHaveBeenCalledTimes(0)
		})

		test('does not trigger trackEnabled when setting disabled input track', () => {
			const audioTrack = newMediaStreamTrackMock('audio')

			audioTrack.enabled = false
			trackToStream._setInputTrack('audio', audioTrack)

			expect(trackToStream.getStream()).not.toBe(null)
			expect(trackToStream.getStream().getTracks().length).toBe(1)
			expect(trackToStream.getStream().getTracks()).toContain(audioTrack)
			expect(streamSetHandler).toHaveBeenCalledTimes(1)
			expect(streamSetHandler).toHaveBeenCalledWith(trackToStream, trackToStream.getStream(), null)
			expect(trackReplacedHandler).toHaveBeenCalledTimes(1)
			expect(trackReplacedHandler).toHaveBeenCalledWith(trackToStream, audioTrack, null)
			expect(trackEnabledHandler).toHaveBeenCalledTimes(0)
		})

		test('creates another stream when setting first input track again after removing it', () => {
			const audioTrack = newMediaStreamTrackMock('audio')

			trackToStream._setInputTrack('audio', audioTrack)

			const stream = trackToStream.getStream()

			trackToStream._setInputTrack('audio', null)

			streamSetHandler.mockClear()
			trackReplacedHandler.mockClear()
			trackEnabledHandler.mockClear()

			trackToStream._setInputTrack('audio', audioTrack)

			expect(trackToStream.getStream()).not.toBe(stream)
			expect(trackToStream.getStream()).not.toBe(null)
			expect(trackToStream.getStream().getTracks().length).toBe(1)
			expect(trackToStream.getStream().getTracks()).toContain(audioTrack)
			expect(streamSetHandler).toHaveBeenCalledTimes(1)
			expect(streamSetHandler).toHaveBeenCalledWith(trackToStream, trackToStream.getStream(), null)
			expect(trackReplacedHandler).toHaveBeenCalledTimes(1)
			expect(trackReplacedHandler).toHaveBeenCalledWith(trackToStream, audioTrack, null)
			expect(trackEnabledHandler).toHaveBeenCalledTimes(0)
		})
	})

	describe('enable/disable input track', () => {
		test('triggers event if input track is disabled', () => {
			const audioTrack = newMediaStreamTrackMock('audio')

			audioTrack.enabled = true
			trackToStream._setInputTrack('audio', audioTrack)

			audioTrack.enabled = false
			trackToStream._setInputTrackEnabled('audio', false)

			expect(trackEnabledHandler).toHaveBeenCalledTimes(1)
			expect(trackEnabledHandler).toHaveBeenCalledWith(trackToStream, audioTrack, false)
		})

		test('triggers event if input track is enabled', () => {
			const audioTrack = newMediaStreamTrackMock('audio')

			audioTrack.enabled = false
			trackToStream._setInputTrack('audio', audioTrack)

			audioTrack.enabled = true
			trackToStream._setInputTrackEnabled('audio', true)

			expect(trackEnabledHandler).toHaveBeenCalledTimes(1)
			expect(trackEnabledHandler).toHaveBeenCalledWith(trackToStream, audioTrack, true)
		})
	})

	describe('remove input track', () => {
		test('removes track from existing stream when removing input track', () => {
			const audioTrack = newMediaStreamTrackMock('audio')
			const videoTrack = newMediaStreamTrackMock('video')

			trackToStream._setInputTrack('audio', audioTrack)
			trackToStream._setInputTrack('video', videoTrack)

			const stream = trackToStream.getStream()

			streamSetHandler.mockClear()
			trackReplacedHandler.mockClear()
			trackEnabledHandler.mockClear()

			trackToStream._setInputTrack('audio', null)

			expect(trackToStream.getStream()).not.toBe(null)
			expect(trackToStream.getStream()).toBe(stream)
			expect(trackToStream.getStream().getTracks().length).toBe(1)
			expect(trackToStream.getStream().getTracks()).not.toContain(audioTrack)
			expect(trackToStream.getStream().getTracks()).toContain(videoTrack)
			expect(streamSetHandler).toHaveBeenCalledTimes(0)
			expect(trackReplacedHandler).toHaveBeenCalledTimes(1)
			expect(trackReplacedHandler).toHaveBeenCalledWith(trackToStream, null, audioTrack)
			expect(trackEnabledHandler).toHaveBeenCalledTimes(0)
		})

		test('removes track and stream when removing remaining input track', () => {
			const audioTrack = newMediaStreamTrackMock('audio')
			const videoTrack = newMediaStreamTrackMock('video')

			trackToStream._setInputTrack('audio', audioTrack)
			trackToStream._setInputTrack('video', videoTrack)

			const stream = trackToStream.getStream()
			expect(stream).not.toBe(null)

			streamSetHandler.mockClear()
			trackReplacedHandler.mockClear()
			trackEnabledHandler.mockClear()

			trackToStream._setInputTrack('audio', null)
			trackToStream._setInputTrack('video', null)

			expect(trackToStream.getStream()).toBe(null)
			expect(streamSetHandler).toHaveBeenCalledTimes(1)
			expect(streamSetHandler).toHaveBeenCalledWith(trackToStream, null, stream)
			expect(trackReplacedHandler).toHaveBeenCalledTimes(2)
			expect(trackReplacedHandler).toHaveBeenNthCalledWith(1, trackToStream, null, audioTrack)
			expect(trackReplacedHandler).toHaveBeenNthCalledWith(2, trackToStream, null, videoTrack)
			expect(trackEnabledHandler).toHaveBeenCalledTimes(0)
		})
	})

	describe('update input track', () => {
		test('does nothing when setting same input track again', () => {
			const audioTrack = newMediaStreamTrackMock('audio')

			trackToStream._setInputTrack('audio', audioTrack)

			const stream = trackToStream.getStream()

			streamSetHandler.mockClear()
			trackReplacedHandler.mockClear()
			trackEnabledHandler.mockClear()

			trackToStream._setInputTrack('audio', audioTrack)

			expect(trackToStream.getStream()).not.toBe(null)
			expect(trackToStream.getStream()).toBe(stream)
			expect(trackToStream.getStream().getTracks().length).toBe(1)
			expect(trackToStream.getStream().getTracks()).toContain(audioTrack)
			expect(streamSetHandler).toHaveBeenCalledTimes(0)
			expect(trackReplacedHandler).toHaveBeenCalledTimes(0)
			expect(trackEnabledHandler).toHaveBeenCalledTimes(0)
		})

		test('triggers event when setting same now disabled input track again', () => {
			const audioTrack = newMediaStreamTrackMock('audio')

			trackToStream._setInputTrack('audio', audioTrack)

			const stream = trackToStream.getStream()

			streamSetHandler.mockClear()
			trackReplacedHandler.mockClear()
			trackEnabledHandler.mockClear()

			audioTrack.enabled = false
			trackToStream._setInputTrack('audio', audioTrack)

			expect(trackToStream.getStream()).not.toBe(null)
			expect(trackToStream.getStream()).toBe(stream)
			expect(trackToStream.getStream().getTracks().length).toBe(1)
			expect(trackToStream.getStream().getTracks()).toContain(audioTrack)
			expect(streamSetHandler).toHaveBeenCalledTimes(0)
			expect(trackReplacedHandler).toHaveBeenCalledTimes(0)
			expect(trackEnabledHandler).toHaveBeenCalledTimes(1)
			expect(trackEnabledHandler).toHaveBeenCalledWith(trackToStream, audioTrack, false)
		})

		test('triggers event when setting same now enabled input track again', () => {
			const audioTrack = newMediaStreamTrackMock('audio')

			audioTrack.enabled = false
			trackToStream._setInputTrack('audio', audioTrack)

			const stream = trackToStream.getStream()

			streamSetHandler.mockClear()
			trackReplacedHandler.mockClear()
			trackEnabledHandler.mockClear()

			audioTrack.enabled = true
			trackToStream._setInputTrack('audio', audioTrack)

			expect(trackToStream.getStream()).not.toBe(null)
			expect(trackToStream.getStream()).toBe(stream)
			expect(trackToStream.getStream().getTracks().length).toBe(1)
			expect(trackToStream.getStream().getTracks()).toContain(audioTrack)
			expect(streamSetHandler).toHaveBeenCalledTimes(0)
			expect(trackReplacedHandler).toHaveBeenCalledTimes(0)
			expect(trackEnabledHandler).toHaveBeenCalledTimes(1)
			expect(trackEnabledHandler).toHaveBeenCalledWith(trackToStream, audioTrack, true)
		})

		test('replaces track in existing stream when setting another input track', () => {
			const audioTrack = newMediaStreamTrackMock('audio')
			const audioTrack2 = newMediaStreamTrackMock('audio2')

			trackToStream._setInputTrack('audio', audioTrack)

			const stream = trackToStream.getStream()

			streamSetHandler.mockClear()
			trackReplacedHandler.mockClear()
			trackEnabledHandler.mockClear()

			trackToStream._setInputTrack('audio', audioTrack2)

			expect(trackToStream.getStream()).not.toBe(null)
			expect(trackToStream.getStream()).toBe(stream)
			expect(trackToStream.getStream().getTracks().length).toBe(1)
			expect(trackToStream.getStream().getTracks()).toContain(audioTrack2)
			expect(streamSetHandler).toHaveBeenCalledTimes(0)
			expect(trackReplacedHandler).toHaveBeenCalledTimes(1)
			expect(trackReplacedHandler).toHaveBeenCalledWith(trackToStream, audioTrack2, audioTrack)
			expect(trackEnabledHandler).toHaveBeenCalledTimes(0)
		})

		test('does not trigger trackEnabled when setting another now disabled input track', () => {
			const audioTrack = newMediaStreamTrackMock('audio')
			const audioTrack2 = newMediaStreamTrackMock('audio2')

			trackToStream._setInputTrack('audio', audioTrack)

			const stream = trackToStream.getStream()

			streamSetHandler.mockClear()
			trackReplacedHandler.mockClear()
			trackEnabledHandler.mockClear()

			audioTrack2.enabled = false
			trackToStream._setInputTrack('audio', audioTrack2)

			expect(trackToStream.getStream()).not.toBe(null)
			expect(trackToStream.getStream()).toBe(stream)
			expect(trackToStream.getStream().getTracks().length).toBe(1)
			expect(trackToStream.getStream().getTracks()).toContain(audioTrack2)
			expect(streamSetHandler).toHaveBeenCalledTimes(0)
			expect(trackReplacedHandler).toHaveBeenCalledTimes(1)
			expect(trackReplacedHandler).toHaveBeenCalledWith(trackToStream, audioTrack2, audioTrack)
			expect(trackEnabledHandler).toHaveBeenCalledTimes(0)
		})

		test('does not trigger trackEnabled when setting another now enabled input track', () => {
			const audioTrack = newMediaStreamTrackMock('audio')
			const audioTrack2 = newMediaStreamTrackMock('audio2')

			audioTrack.enabled = false
			trackToStream._setInputTrack('audio', audioTrack)

			streamSetHandler.mockClear()
			trackReplacedHandler.mockClear()
			trackEnabledHandler.mockClear()

			const stream = trackToStream.getStream()

			trackToStream._setInputTrack('audio', audioTrack2)

			expect(trackToStream.getStream()).not.toBe(null)
			expect(trackToStream.getStream()).toBe(stream)
			expect(trackToStream.getStream().getTracks().length).toBe(1)
			expect(trackToStream.getStream().getTracks()).toContain(audioTrack2)
			expect(streamSetHandler).toHaveBeenCalledTimes(0)
			expect(trackReplacedHandler).toHaveBeenCalledTimes(1)
			expect(trackReplacedHandler).toHaveBeenCalledWith(trackToStream, audioTrack2, audioTrack)
			expect(trackEnabledHandler).toHaveBeenCalledTimes(0)
		})
	})
})
