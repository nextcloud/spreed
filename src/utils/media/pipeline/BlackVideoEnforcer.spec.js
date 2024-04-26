/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import BlackVideoEnforcer from './BlackVideoEnforcer.js'

/**
 * Helper function to create MediaStreamTrack mocks with just the attributes and
 * methods used by BlackVideoEnforcer.
 *
 * @param {string} id the ID of the track
 */
function newMediaStreamTrackMock(id) {
	/**
	 * MediaStreamTrackMock constructor.
	 */
	function MediaStreamTrackMock() {
		this._endedEventHandlers = []
		this._width = 720
		this._height = 540
		this.id = id
		this.enabled = true
		this.addEventListener = jest.fn((eventName, eventHandler) => {
			if (eventName !== 'ended') {
				return
			}

			this._endedEventHandlers.push(eventHandler)
		})
		this.removeEventListener = jest.fn((eventName, eventHandler) => {
			if (eventName !== 'ended') {
				return
			}

			const index = this._endedEventHandlers.indexOf(eventHandler)
			if (index !== -1) {
				this._endedEventHandlers.splice(index, 1)
			}
		})
		this.stop = jest.fn(() => {
			for (let i = 0; i < this._endedEventHandlers.length; i++) {
				const handler = this._endedEventHandlers[i]
				handler.apply(handler)
			}
		})
		this.getSettings = jest.fn(() => {
			return {
				width: this._width,
				height: this._height,
			}
		})
	}
	return new MediaStreamTrackMock()
}

describe('BlackVideoEnforcer', () => {
	let blackVideoEnforcer
	let outputTrackSetHandler
	let outputTrackEnabledHandler
	let expectedTrackEnabledStateInOutputTrackSetEvent
	let blackVideoTrackCount
	let blackVideoTracks

	beforeAll(() => {
		const originalCreateElement = document.createElement
		jest.spyOn(document, 'createElement').mockImplementation((tagName, options) => {
			if (tagName !== 'canvas') {
				return originalCreateElement(tagName, options)
			}

			return new function() {
				this.getContext = jest.fn(() => {
					return {
						fillRect: jest.fn(),
					}
				})
				this.captureStream = jest.fn(() => {
					const blackVideoTrackLocal = newMediaStreamTrackMock('blackVideoTrack' + blackVideoTrackCount)
					blackVideoTracks[blackVideoTrackCount] = blackVideoTrackLocal
					blackVideoTrackCount++

					blackVideoTrackLocal._width = this.width
					blackVideoTrackLocal._height = this.height

					return {
						getVideoTracks: jest.fn(() => {
							return [blackVideoTrackLocal]
						}),
						getTracks: jest.fn(() => {
							return [blackVideoTrackLocal]
						}),
					}
				})
			}()
		})
	})

	beforeEach(() => {
		jest.useFakeTimers()

		blackVideoTrackCount = 0
		blackVideoTracks = []

		blackVideoEnforcer = new BlackVideoEnforcer()

		expectedTrackEnabledStateInOutputTrackSetEvent = undefined

		outputTrackSetHandler = jest.fn((blackVideoEnforcer, trackId, track) => {
			if (expectedTrackEnabledStateInOutputTrackSetEvent !== undefined) {
				expect(track.enabled).toBe(expectedTrackEnabledStateInOutputTrackSetEvent)
			}
		})
		outputTrackEnabledHandler = jest.fn()

		blackVideoEnforcer.on('outputTrackSet', outputTrackSetHandler)
		blackVideoEnforcer.on('outputTrackEnabled', outputTrackEnabledHandler)
	})

	afterEach(() => {
		clearTimeout(blackVideoEnforcer._disableOrRemoveOutputTrackTimeout)
		clearInterval(blackVideoEnforcer._renderInterval)
	})

	afterAll(() => {
		jest.restoreAllMocks()
	})

	const DISABLE_OR_REMOVE_TIMEOUT = 5000

	const STOPPED = true

	/**
	 * Checks that a black video track has the expected attributes.
	 *
	 * @param {number} index the index of the black video track to check.
	 * @param {number} width the expected width of the black video track.
	 * @param {number} height the expected height of the black video track.
	 * @param {boolean} stopped whether the black video track is expected to
	 *        have been stopped already or not.
	 */
	function assertBlackVideoTrack(index, width, height, stopped = false) {
		expect(blackVideoTracks[index].getSettings().width).toBe(width)
		expect(blackVideoTracks[index].getSettings().height).toBe(height)
		expect(blackVideoTracks[index].stop).toHaveBeenCalledTimes(stopped ? 1 : 0)
	}

	describe('set input track', () => {
		test('sets input track as its output track when setting enabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			blackVideoEnforcer._setInputTrack('default', inputTrack)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', inputTrack)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(0)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT * 5)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(0)
		})

		test('sets black video track as its output track when setting disabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			inputTrack.enabled = false
			blackVideoEnforcer._setInputTrack('default', inputTrack)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', blackVideoTracks[0])
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT - 1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackEnabledHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', false)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540)
		})
	})

	describe('enable/disable input track', () => {
		test('sets black video track as its output track if input track is disabled', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			blackVideoEnforcer._setInputTrack('default', inputTrack)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 2)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			inputTrack.enabled = false
			blackVideoEnforcer._setInputTrackEnabled('default', false)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', blackVideoTracks[0])
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT - 1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackEnabledHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', false)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540)
		})

		test('sets input track as its output track if input track is enabled', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			inputTrack.enabled = false
			blackVideoEnforcer._setInputTrack('default', inputTrack)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 2)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			inputTrack.enabled = true
			blackVideoEnforcer._setInputTrackEnabled('default', true)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', inputTrack)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540, STOPPED)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT * 5)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
		})

		test('sets input track as its output track if input track is later enabled', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			inputTrack.enabled = false
			blackVideoEnforcer._setInputTrack('default', inputTrack)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT * 2)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			inputTrack.enabled = true
			blackVideoEnforcer._setInputTrackEnabled('default', true)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', inputTrack)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540, STOPPED)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT * 5)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
		})

		test('does nothing if input track is enabled again', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			blackVideoEnforcer._setInputTrack('default', inputTrack)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 2)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			blackVideoEnforcer._setInputTrackEnabled('default', true)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(0)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT * 5)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(0)
		})

		test('does nothing if input track is disabled again', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			inputTrack.enabled = false
			blackVideoEnforcer._setInputTrack('default', inputTrack)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 2)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			blackVideoEnforcer._setInputTrackEnabled('default', false)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 2 - 1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackEnabledHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', false)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540)
		})
	})

	describe('remove input track', () => {
		test('sets black video track as its output track and later removes output track when removing enabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			blackVideoEnforcer._setInputTrack('default', inputTrack)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 2)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			blackVideoEnforcer._setInputTrack('default', null)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', blackVideoTracks[0])
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT - 1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTracks[0].stop).toHaveBeenCalledTimes(0)

			expectedTrackEnabledStateInOutputTrackSetEvent = undefined

			jest.advanceTimersByTime(1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', null)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
		})

		test('sets input track as its output track when setting enabled input track after removing enabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')
			const inputTrack2 = newMediaStreamTrackMock('input2')

			blackVideoEnforcer._setInputTrack('default', inputTrack)

			blackVideoEnforcer._setInputTrack('default', null)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 2)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			inputTrack2._width = 320
			inputTrack2._height = 180
			blackVideoEnforcer._setInputTrack('default', inputTrack2)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', inputTrack2)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540, STOPPED)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT * 5)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
		})

		test('sets black video track as its output track when setting disabled input track after removing enabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')
			const inputTrack2 = newMediaStreamTrackMock('input2')

			blackVideoEnforcer._setInputTrack('default', inputTrack)

			blackVideoEnforcer._setInputTrack('default', null)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 2)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			inputTrack2.enabled = false
			inputTrack2._width = 320
			inputTrack2._height = 180
			blackVideoEnforcer._setInputTrack('default', inputTrack2)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', blackVideoTracks[1])
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(2)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
			assertBlackVideoTrack(1, 320, 180)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT - 1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackEnabledHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', false)
			expect(blackVideoTrackCount).toBe(2)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
			assertBlackVideoTrack(1, 320, 180)
		})

		test('sets black video track as its output track and later removes output track when removing disabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			inputTrack.enabled = false
			blackVideoEnforcer._setInputTrack('default', inputTrack)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 2)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			blackVideoEnforcer._setInputTrack('default', null)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', blackVideoTracks[1])
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(2)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
			assertBlackVideoTrack(1, 720, 540)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT - 1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTracks[1].stop).toHaveBeenCalledTimes(0)

			expectedTrackEnabledStateInOutputTrackSetEvent = undefined

			jest.advanceTimersByTime(1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', null)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(2)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
			assertBlackVideoTrack(1, 720, 540, STOPPED)
		})

		test('sets black video track as its output track and later removes output track when later removing disabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			inputTrack.enabled = false
			blackVideoEnforcer._setInputTrack('default', inputTrack)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT * 5)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			blackVideoEnforcer._setInputTrack('default', null)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', blackVideoTracks[1])
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(2)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
			assertBlackVideoTrack(1, 720, 540)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT - 1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTracks[1].stop).toHaveBeenCalledTimes(0)

			expectedTrackEnabledStateInOutputTrackSetEvent = undefined

			jest.advanceTimersByTime(1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', null)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(2)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
			assertBlackVideoTrack(1, 720, 540, STOPPED)
		})

		test('sets black video track as its output track and later removes output track when removing null track', () => {
			expectedTrackEnabledStateInOutputTrackSetEvent = true

			blackVideoEnforcer._setInputTrack('default', null)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', blackVideoTracks[0])
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 640, 480)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT - 1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTracks[0].stop).toHaveBeenCalledTimes(0)

			expectedTrackEnabledStateInOutputTrackSetEvent = undefined

			jest.advanceTimersByTime(1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', null)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 640, 480, STOPPED)
		})

		test('sets black video track as its output track and later removes output track when removing null track again after removing enabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			blackVideoEnforcer._setInputTrack('default', inputTrack)

			blackVideoEnforcer._setInputTrack('default', null)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 2)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			blackVideoEnforcer._setInputTrack('default', null)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', blackVideoTracks[1])
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(2)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
			assertBlackVideoTrack(1, 640, 480)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT - 1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTracks[1].stop).toHaveBeenCalledTimes(0)

			expectedTrackEnabledStateInOutputTrackSetEvent = undefined

			jest.advanceTimersByTime(1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', null)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(2)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
			assertBlackVideoTrack(1, 640, 480, STOPPED)
		})
	})

	describe('stop input track', () => {
		test('sets black video track as its output track when stopping enabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			blackVideoEnforcer._setInputTrack('default', inputTrack)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 2)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			inputTrack.stop()

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', blackVideoTracks[0])
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT - 1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackEnabledHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', false)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540)
		})

		test('sets black video track as its output track when stopping initially disabled and then enabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			inputTrack.enabled = false
			blackVideoEnforcer._setInputTrack('default', inputTrack)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 4)

			blackVideoEnforcer._setInputTrackEnabled('default', true)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 4)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			inputTrack.stop()

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', blackVideoTracks[1])
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(2)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
			assertBlackVideoTrack(1, 720, 540)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT - 1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackEnabledHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', false)
			expect(blackVideoTrackCount).toBe(2)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
			assertBlackVideoTrack(1, 720, 540)
		})

		test('does nothing when stopping disabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			inputTrack.enabled = false
			blackVideoEnforcer._setInputTrack('default', inputTrack)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 2)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			inputTrack.stop()

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 2 - 1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackEnabledHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', false)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540)
		})

		test('does nothing when stopping initially enabled and then disabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			blackVideoEnforcer._setInputTrack('default', inputTrack)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 4)

			blackVideoEnforcer._setInputTrackEnabled('default', false)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 4)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			inputTrack.stop()

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT * 3 / 4 - 1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackEnabledHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', false)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540)
		})

		test('sets black video track as its output track and later removes output track when stopping enabled input track and then removing it', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			blackVideoEnforcer._setInputTrack('default', inputTrack)

			inputTrack.stop()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 2)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			blackVideoEnforcer._setInputTrack('default', null)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', blackVideoTracks[1])
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(2)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
			assertBlackVideoTrack(1, 720, 540)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT - 1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTracks[1].stop).toHaveBeenCalledTimes(0)

			expectedTrackEnabledStateInOutputTrackSetEvent = undefined

			jest.advanceTimersByTime(1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', null)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(2)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
			assertBlackVideoTrack(1, 720, 540, STOPPED)
		})

		test('sets black video track as its output track and later removes output track when stopping disabled input track and then removing it', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			inputTrack.enabled = false
			blackVideoEnforcer._setInputTrack('default', inputTrack)

			inputTrack.stop()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 2)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			blackVideoEnforcer._setInputTrack('default', null)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', blackVideoTracks[1])
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(2)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
			assertBlackVideoTrack(1, 720, 540)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT - 1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTracks[1].stop).toHaveBeenCalledTimes(0)

			expectedTrackEnabledStateInOutputTrackSetEvent = undefined

			jest.advanceTimersByTime(1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', null)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(2)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
			assertBlackVideoTrack(1, 720, 540, STOPPED)
		})

		test('sets input track as its output track when stopping enabled input track and then replacing it with another enabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')
			const inputTrack2 = newMediaStreamTrackMock('input2')

			blackVideoEnforcer._setInputTrack('default', inputTrack)

			inputTrack.stop()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 2)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			inputTrack2._width = 320
			inputTrack2._height = 180
			blackVideoEnforcer._setInputTrack('default', inputTrack2)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', inputTrack2)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540, STOPPED)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT * 5)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
		})

		test('sets black video track as its output track when stopping enabled input track and then replacing it with another disabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')
			const inputTrack2 = newMediaStreamTrackMock('input2')

			blackVideoEnforcer._setInputTrack('default', inputTrack)

			inputTrack.stop()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 2)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			inputTrack2.enabled = false
			inputTrack2._width = 320
			inputTrack2._height = 180
			blackVideoEnforcer._setInputTrack('default', inputTrack2)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', blackVideoTracks[1])
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(2)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
			assertBlackVideoTrack(1, 320, 180)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT - 1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackEnabledHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', false)
			expect(blackVideoTrackCount).toBe(2)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
			assertBlackVideoTrack(1, 320, 180)
		})

		test('sets input track as its output track when stopping disabled input track and then replacing it with another enabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')
			const inputTrack2 = newMediaStreamTrackMock('input2')

			inputTrack.enabled = false
			blackVideoEnforcer._setInputTrack('default', inputTrack)

			inputTrack.stop()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 2)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			inputTrack2._width = 320
			inputTrack2._height = 180
			blackVideoEnforcer._setInputTrack('default', inputTrack2)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', inputTrack2)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540, STOPPED)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT * 5)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
		})

		test('sets black video track as its output track when stopping disabled input track and then replacing it with another disabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')
			const inputTrack2 = newMediaStreamTrackMock('input2')

			inputTrack.enabled = false
			blackVideoEnforcer._setInputTrack('default', inputTrack)

			inputTrack.stop()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 2)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			inputTrack2.enabled = false
			inputTrack2._width = 320
			inputTrack2._height = 180
			blackVideoEnforcer._setInputTrack('default', inputTrack2)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', blackVideoTracks[1])
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(2)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
			assertBlackVideoTrack(1, 320, 180)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT - 1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackEnabledHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', false)
			expect(blackVideoTrackCount).toBe(2)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
			assertBlackVideoTrack(1, 320, 180)
		})

		test('does nothing when stopping a previously removed enabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			blackVideoEnforcer._setInputTrack('default', inputTrack)

			blackVideoEnforcer._setInputTrack('default', null)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT * 5)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			inputTrack.stop()

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
		})

		test('does nothing when stopping a previously removed disabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			inputTrack.enabled = false
			blackVideoEnforcer._setInputTrack('default', inputTrack)

			blackVideoEnforcer._setInputTrack('default', null)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT * 5)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			inputTrack.stop()

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(2)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
			assertBlackVideoTrack(1, 720, 540, STOPPED)
		})

		test('does nothing when stopping a previously replaced enabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')
			const inputTrack2 = newMediaStreamTrackMock('input2')

			blackVideoEnforcer._setInputTrack('default', inputTrack)

			inputTrack2._width = 320
			inputTrack2._height = 180
			blackVideoEnforcer._setInputTrack('default', inputTrack2)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT * 5)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			inputTrack.stop()

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(0)
		})

		test('does nothing when stopping a previously replaced disabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')
			const inputTrack2 = newMediaStreamTrackMock('input2')

			inputTrack.enabled = false
			blackVideoEnforcer._setInputTrack('default', inputTrack)

			inputTrack2._width = 320
			inputTrack2._height = 180
			blackVideoEnforcer._setInputTrack('default', inputTrack2)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT * 5)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			inputTrack.stop()

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
		})

		test('sets black video track as its output track when stopping input track after setting it again', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			blackVideoEnforcer._setInputTrack('default', inputTrack)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 4)

			inputTrack._width = 320
			inputTrack._height = 180
			blackVideoEnforcer._setInputTrack('default', inputTrack)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 4)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			inputTrack.stop()

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', blackVideoTracks[0])
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 320, 180)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT - 1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackEnabledHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', false)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 320, 180)
		})
	})

	describe('update input track', () => {
		test('sets input track as its output track when setting same enabled input track again', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			blackVideoEnforcer._setInputTrack('default', inputTrack)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 2)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			inputTrack._width = 320
			inputTrack._height = 180
			blackVideoEnforcer._setInputTrack('default', inputTrack)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', inputTrack)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(0)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT * 5)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(0)
		})

		test('sets black video track as its output track when setting same disabled input track again', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			inputTrack.enabled = false
			blackVideoEnforcer._setInputTrack('default', inputTrack)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 2)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			inputTrack._width = 320
			inputTrack._height = 180
			blackVideoEnforcer._setInputTrack('default', inputTrack)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', blackVideoTracks[1])
			expect(blackVideoTrackCount).toBe(2)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
			assertBlackVideoTrack(1, 320, 180)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT - 1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackEnabledHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', false)
			expect(blackVideoTrackCount).toBe(2)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
			assertBlackVideoTrack(1, 320, 180)
		})

		test('sets black video track as its output track when setting same now disabled input track again', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			blackVideoEnforcer._setInputTrack('default', inputTrack)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 2)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			inputTrack.enabled = false
			inputTrack._width = 320
			inputTrack._height = 180
			blackVideoEnforcer._setInputTrack('default', inputTrack)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', blackVideoTracks[0])
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 320, 180)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT - 1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackEnabledHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', false)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 320, 180)
		})

		test('sets input track as its output track when setting same now enabled input track again', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			inputTrack.enabled = false
			blackVideoEnforcer._setInputTrack('default', inputTrack)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 2)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			inputTrack.enabled = true
			inputTrack._width = 320
			inputTrack._height = 180
			blackVideoEnforcer._setInputTrack('default', inputTrack)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', inputTrack)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540, STOPPED)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT * 5)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
		})

		test('sets input track as its output track when setting another enabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')
			const inputTrack2 = newMediaStreamTrackMock('input2')

			blackVideoEnforcer._setInputTrack('default', inputTrack)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 2)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			blackVideoEnforcer._setInputTrack('default', inputTrack2)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', inputTrack2)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(0)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT * 5)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(0)
		})

		test('sets black video track as its output track when setting another disabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')
			const inputTrack2 = newMediaStreamTrackMock('input2')

			inputTrack.enabled = false
			blackVideoEnforcer._setInputTrack('default', inputTrack)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 2)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			inputTrack2.enabled = false
			inputTrack2._width = 320
			inputTrack2._height = 180
			blackVideoEnforcer._setInputTrack('default', inputTrack2)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', blackVideoTracks[1])
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(2)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
			assertBlackVideoTrack(1, 320, 180)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT - 1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackEnabledHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', false)
			expect(blackVideoTrackCount).toBe(2)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
			assertBlackVideoTrack(1, 320, 180)
		})

		test('sets black video track as its output track when setting another now disabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')
			const inputTrack2 = newMediaStreamTrackMock('input2')

			blackVideoEnforcer._setInputTrack('default', inputTrack)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 2)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			inputTrack2.enabled = false
			inputTrack2._width = 320
			inputTrack2._height = 180
			blackVideoEnforcer._setInputTrack('default', inputTrack2)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', blackVideoTracks[0])
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 320, 180)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT - 1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackEnabledHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', false)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 320, 180)
		})

		test('sets input track as its output track when setting another now enabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')
			const inputTrack2 = newMediaStreamTrackMock('input2')

			inputTrack.enabled = false
			blackVideoEnforcer._setInputTrack('default', inputTrack)

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT / 2)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			expectedTrackEnabledStateInOutputTrackSetEvent = true

			inputTrack2.enabled = true
			blackVideoEnforcer._setInputTrack('default', inputTrack2)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(1)
			expect(outputTrackSetHandler).toHaveBeenCalledWith(blackVideoEnforcer, 'default', inputTrack2)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540, STOPPED)

			outputTrackSetHandler.mockClear()
			outputTrackEnabledHandler.mockClear()

			jest.advanceTimersByTime(DISABLE_OR_REMOVE_TIMEOUT * 5)

			expect(outputTrackSetHandler).toHaveBeenCalledTimes(0)
			expect(outputTrackEnabledHandler).toHaveBeenCalledTimes(0)
			expect(blackVideoTrackCount).toBe(1)
			assertBlackVideoTrack(0, 720, 540, STOPPED)
		})
	})
})
