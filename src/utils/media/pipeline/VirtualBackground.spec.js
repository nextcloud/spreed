/**
 *
 * @copyright Copyright (c) 2021, Daniel Calviño Sánchez (danxuliu@gmail.com)
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

import VirtualBackground from './VirtualBackground.js'

/**
 * Helper function to create MediaStreamTrack mocks with just the attributes and
 * methods used by VirtualBackground.
 *
 * @param {string} id the ID of the track
 */
function newMediaStreamTrackMock(id) {
	/**
	 * MediaStreamTrackMock constructor.
	 */
	function MediaStreamTrackMock() {
		this._endedEventHandlers = []
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
	}
	return new MediaStreamTrackMock()
}

describe('VirtualBackground', () => {
	let virtualBackground
	let available
	let effectOutputTrackCount
	let effectOutputTrack

	beforeAll(() => {
		// MediaStream is used in VirtualBackground but not implemented in
		// jsdom, so a stub is needed.
		window.MediaStream = function() {
			this.addTrack = jest.fn()
		}

		jest.spyOn(VirtualBackground.prototype, '_initJitsiStreamBackgroundEffect').mockImplementation(function() {
			this._jitsiStreamBackgroundEffect = {
				getVirtualBackground: jest.fn(() => {
					return this._jitsiStreamBackgroundEffect.virtualBackground
				}),
				setVirtualBackground: jest.fn(() => {
				}),
				startEffect: jest.fn((inputStream) => {
					effectOutputTrackCount++
					const effectOutputTrackLocal = newMediaStreamTrackMock('output' + effectOutputTrackCount)
					effectOutputTrack = effectOutputTrackLocal

					return {
						getVideoTracks: jest.fn(() => {
							return [effectOutputTrackLocal]
						}),
						getTracks: jest.fn(() => {
							return [effectOutputTrackLocal]
						}),
					}
				}),
				updateInputStream: jest.fn(() => {
				}),
				stopEffect: jest.fn(() => {
				}),
			}
		})
		jest.spyOn(VirtualBackground.prototype, 'isAvailable').mockImplementation(function() {
			return available
		})
	})

	beforeEach(() => {
		available = true
		effectOutputTrackCount = 0
		effectOutputTrack = undefined

		virtualBackground = new VirtualBackground()

		jest.spyOn(virtualBackground, '_setOutputTrack')
	})

	afterAll(() => {
		jest.restoreAllMocks()
	})

	describe('get virtual background', () => {
		beforeEach(() => {
			virtualBackground._jitsiStreamBackgroundEffect.virtualBackground = {
				objectWithoutValidation: true,
			}
		})

		test('gets virtual background', () => {
			expect(virtualBackground.getVirtualBackground()).toEqual({
				objectWithoutValidation: true,
			})
			expect(virtualBackground._jitsiStreamBackgroundEffect.getVirtualBackground).toHaveBeenCalledTimes(1)
		})

		test('returns null if get when not available', () => {
			available = false

			expect(virtualBackground.getVirtualBackground()).toBe(undefined)
			// A real VirtualBackground object would not even have a
			// _jitsiStreamBackgroundEffect object if not available, but the
			// mock is kept to perform the assertion.
			expect(virtualBackground._jitsiStreamBackgroundEffect.getVirtualBackground).toHaveBeenCalledTimes(0)
		})
	})

	describe('set virtual background', () => {
		test('sets virtual background type and parameters', () => {
			virtualBackground.setVirtualBackground({
				objectWithoutValidation: true,
			})

			expect(virtualBackground._jitsiStreamBackgroundEffect.setVirtualBackground).toHaveBeenCalledTimes(1)
			expect(virtualBackground._jitsiStreamBackgroundEffect.setVirtualBackground).toHaveBeenNthCalledWith(1, {
				objectWithoutValidation: true,
			})
		})

		test('does nothing if set when not available', () => {
			available = false

			virtualBackground.setVirtualBackground({
				objectWithoutValidation: true,
			})

			// A real VirtualBackground object would not even have a
			// _jitsiStreamBackgroundEffect object if not available, but the
			// mock is kept to perform the assertion.
			expect(virtualBackground._jitsiStreamBackgroundEffect.setVirtualBackground).toHaveBeenCalledTimes(0)
		})
	})

	test('is enabled by default', () => {
		expect(virtualBackground.isEnabled()).toBe(true)
	})

	describe('enable/disable virtual background', () => {
		test('does nothing if disabled when there is no input track', () => {
			virtualBackground.setEnabled(false)

			expect(virtualBackground.isEnabled()).toBe(false)
			expect(virtualBackground._setOutputTrack).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.startEffect).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.updateInputStream).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.stopEffect).toHaveBeenCalledTimes(0)
		})

		test('does nothing if enabled when there is no input track', () => {
			virtualBackground.setEnabled(false)
			virtualBackground.setEnabled(true)

			expect(virtualBackground.isEnabled()).toBe(true)
			expect(virtualBackground._setOutputTrack).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.startEffect).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.updateInputStream).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.stopEffect).toHaveBeenCalledTimes(0)
		})

		test('is disabled if enabled when not available', () => {
			available = false
			virtualBackground.setEnabled(true)

			expect(virtualBackground.isEnabled()).toBe(false)
		})
	})

	describe('set input track', () => {
		test('sets effect output track as its output track when setting input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			virtualBackground._setInputTrack('default', inputTrack)

			expect(virtualBackground._setOutputTrack).toHaveBeenCalledTimes(1)
			expect(virtualBackground._setOutputTrack).toHaveBeenNthCalledWith(1, 'default', effectOutputTrack)
			expect(virtualBackground._jitsiStreamBackgroundEffect.startEffect).toHaveBeenCalledTimes(1)
			expect(virtualBackground._jitsiStreamBackgroundEffect.updateInputStream).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.stopEffect).toHaveBeenCalledTimes(0)
		})

		test('sets input track as its output track if not available when setting input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			available = false
			virtualBackground._setInputTrack('default', inputTrack)

			expect(virtualBackground._setOutputTrack).toHaveBeenCalledTimes(1)
			expect(virtualBackground._setOutputTrack).toHaveBeenNthCalledWith(1, 'default', inputTrack)
			expect(virtualBackground._jitsiStreamBackgroundEffect.startEffect).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.updateInputStream).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.stopEffect).toHaveBeenCalledTimes(0)
		})

		test('sets input track as its output track if not enabled when setting input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			virtualBackground.setEnabled(false)
			virtualBackground._setInputTrack('default', inputTrack)

			expect(virtualBackground._setOutputTrack).toHaveBeenCalledTimes(1)
			expect(virtualBackground._setOutputTrack).toHaveBeenNthCalledWith(1, 'default', inputTrack)
			expect(virtualBackground._jitsiStreamBackgroundEffect.startEffect).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.updateInputStream).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.stopEffect).toHaveBeenCalledTimes(0)
		})

		test('sets input track as its output track if input track is not enabled when setting input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			inputTrack.enabled = false
			virtualBackground._setInputTrack('default', inputTrack)

			expect(virtualBackground._setOutputTrack).toHaveBeenCalledTimes(1)
			expect(virtualBackground._setOutputTrack).toHaveBeenNthCalledWith(1, 'default', inputTrack)
			expect(virtualBackground._jitsiStreamBackgroundEffect.startEffect).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.updateInputStream).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.stopEffect).toHaveBeenCalledTimes(0)
		})
	})

	describe('enable/disable virtual background after setting input track', () => {
		test('sets input track as its output track if disabled', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			virtualBackground._setInputTrack('default', inputTrack)
			virtualBackground.setEnabled(false)

			expect(virtualBackground._setOutputTrack).toHaveBeenCalledTimes(2)
			expect(virtualBackground._setOutputTrack).toHaveBeenNthCalledWith(1, 'default', effectOutputTrack)
			expect(virtualBackground._setOutputTrack).toHaveBeenNthCalledWith(2, 'default', inputTrack)
			expect(virtualBackground._jitsiStreamBackgroundEffect.startEffect).toHaveBeenCalledTimes(1)
			expect(virtualBackground._jitsiStreamBackgroundEffect.updateInputStream).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.stopEffect).toHaveBeenCalledTimes(1)
			expect(effectOutputTrack.stop).toHaveBeenCalledTimes(1)
		})

		test('does nothing if disabled when input track is not enabled', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			inputTrack.enabled = false
			virtualBackground._setInputTrack('default', inputTrack)
			virtualBackground.setEnabled(false)

			expect(virtualBackground._setOutputTrack).toHaveBeenCalledTimes(1)
			expect(virtualBackground._setOutputTrack).toHaveBeenNthCalledWith(1, 'default', inputTrack)
			expect(virtualBackground._jitsiStreamBackgroundEffect.startEffect).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.updateInputStream).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.stopEffect).toHaveBeenCalledTimes(0)
		})

		test('sets effect output track as its output track if enabled', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			virtualBackground.setEnabled(false)
			virtualBackground._setInputTrack('default', inputTrack)
			virtualBackground.setEnabled(true)

			expect(virtualBackground._setOutputTrack).toHaveBeenCalledTimes(2)
			expect(virtualBackground._setOutputTrack).toHaveBeenNthCalledWith(1, 'default', inputTrack)
			expect(virtualBackground._setOutputTrack).toHaveBeenNthCalledWith(2, 'default', effectOutputTrack)
			expect(virtualBackground._jitsiStreamBackgroundEffect.startEffect).toHaveBeenCalledTimes(1)
			expect(virtualBackground._jitsiStreamBackgroundEffect.updateInputStream).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.stopEffect).toHaveBeenCalledTimes(0)
			expect(effectOutputTrack.stop).toHaveBeenCalledTimes(0)
		})

		test('does nothing if enabled when input track is not enabled', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			virtualBackground.setEnabled(false)
			inputTrack.enabled = false
			virtualBackground._setInputTrack('default', inputTrack)
			virtualBackground.setEnabled(true)

			expect(virtualBackground._setOutputTrack).toHaveBeenCalledTimes(1)
			expect(virtualBackground._setOutputTrack).toHaveBeenNthCalledWith(1, 'default', inputTrack)
			expect(virtualBackground._jitsiStreamBackgroundEffect.startEffect).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.updateInputStream).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.stopEffect).toHaveBeenCalledTimes(0)
		})
	})

	describe('enable/disable input track', () => {
		test('sets input track as its output track if input track is disabled', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			virtualBackground._setInputTrack('default', inputTrack)
			virtualBackground._setInputTrackEnabled('default', false)

			expect(virtualBackground._setOutputTrack).toHaveBeenCalledTimes(2)
			expect(virtualBackground._setOutputTrack).toHaveBeenNthCalledWith(1, 'default', effectOutputTrack)
			expect(virtualBackground._setOutputTrack).toHaveBeenNthCalledWith(2, 'default', inputTrack)
			expect(virtualBackground._jitsiStreamBackgroundEffect.startEffect).toHaveBeenCalledTimes(1)
			expect(virtualBackground._jitsiStreamBackgroundEffect.updateInputStream).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.stopEffect).toHaveBeenCalledTimes(1)
			expect(effectOutputTrack.stop).toHaveBeenCalledTimes(1)
		})

		test('sets effect output track as its output track if input track is enabled', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			inputTrack.enabled = false
			virtualBackground._setInputTrack('default', inputTrack)
			virtualBackground._setInputTrackEnabled('default', true)

			expect(virtualBackground._setOutputTrack).toHaveBeenCalledTimes(2)
			expect(virtualBackground._setOutputTrack).toHaveBeenNthCalledWith(1, 'default', inputTrack)
			expect(virtualBackground._setOutputTrack).toHaveBeenNthCalledWith(2, 'default', effectOutputTrack)
			expect(virtualBackground._jitsiStreamBackgroundEffect.startEffect).toHaveBeenCalledTimes(1)
			expect(virtualBackground._jitsiStreamBackgroundEffect.updateInputStream).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.stopEffect).toHaveBeenCalledTimes(0)
			expect(effectOutputTrack.stop).toHaveBeenCalledTimes(0)
		})
	})

	describe('remove input track', () => {
		test('removes output track when removing input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			virtualBackground._setInputTrack('default', inputTrack)
			virtualBackground._setInputTrack('default', null)

			expect(virtualBackground._setOutputTrack).toHaveBeenCalledTimes(2)
			expect(virtualBackground._setOutputTrack).toHaveBeenNthCalledWith(1, 'default', effectOutputTrack)
			expect(virtualBackground._setOutputTrack).toHaveBeenNthCalledWith(2, 'default', null)
			expect(virtualBackground._jitsiStreamBackgroundEffect.startEffect).toHaveBeenCalledTimes(1)
			expect(virtualBackground._jitsiStreamBackgroundEffect.updateInputStream).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.stopEffect).toHaveBeenCalledTimes(1)
			expect(effectOutputTrack.stop).toHaveBeenCalledTimes(1)
		})

		test('removes output track when removing disabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			inputTrack.enabled = false
			virtualBackground._setInputTrack('default', inputTrack)
			virtualBackground._setInputTrack('default', null)

			expect(virtualBackground._setOutputTrack).toHaveBeenCalledTimes(2)
			expect(virtualBackground._setOutputTrack).toHaveBeenNthCalledWith(1, 'default', inputTrack)
			expect(virtualBackground._setOutputTrack).toHaveBeenNthCalledWith(2, 'default', null)
			expect(virtualBackground._jitsiStreamBackgroundEffect.startEffect).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.updateInputStream).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.stopEffect).toHaveBeenCalledTimes(0)
		})
	})

	describe('update input track', () => {
		test('updates effect output track when setting same input track again', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			virtualBackground._setInputTrack('default', inputTrack)
			virtualBackground._setInputTrack('default', inputTrack)

			expect(virtualBackground._setOutputTrack).toHaveBeenCalledTimes(1)
			expect(virtualBackground._setOutputTrack).toHaveBeenNthCalledWith(1, 'default', effectOutputTrack)
			expect(virtualBackground._jitsiStreamBackgroundEffect.startEffect).toHaveBeenCalledTimes(1)
			expect(virtualBackground._jitsiStreamBackgroundEffect.updateInputStream).toHaveBeenCalledTimes(1)
			expect(virtualBackground._jitsiStreamBackgroundEffect.stopEffect).toHaveBeenCalledTimes(0)
			expect(effectOutputTrack.stop).toHaveBeenCalledTimes(0)
		})

		test('sets input track as its output track when setting same disabled input track again', () => {
			const inputTrack = newMediaStreamTrackMock('input')

			inputTrack.enabled = false
			virtualBackground._setInputTrack('default', inputTrack)
			virtualBackground._setInputTrack('default', inputTrack)

			expect(virtualBackground._setOutputTrack).toHaveBeenCalledTimes(2)
			expect(virtualBackground._setOutputTrack).toHaveBeenNthCalledWith(1, 'default', inputTrack)
			expect(virtualBackground._setOutputTrack).toHaveBeenNthCalledWith(2, 'default', inputTrack)
			expect(virtualBackground._jitsiStreamBackgroundEffect.startEffect).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.updateInputStream).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.stopEffect).toHaveBeenCalledTimes(0)
		})

		test('sets new effect output track as its output track when setting another input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')
			const inputTrack2 = newMediaStreamTrackMock('input2')

			virtualBackground._setInputTrack('default', inputTrack)
			const originalEffectOutputTrack = effectOutputTrack
			virtualBackground._setInputTrack('default', inputTrack2)

			expect(virtualBackground._setOutputTrack).toHaveBeenCalledTimes(2)
			expect(virtualBackground._setOutputTrack).toHaveBeenNthCalledWith(1, 'default', originalEffectOutputTrack)
			expect(virtualBackground._setOutputTrack).toHaveBeenNthCalledWith(2, 'default', effectOutputTrack)
			expect(effectOutputTrack).not.toBe(originalEffectOutputTrack)
			expect(virtualBackground._jitsiStreamBackgroundEffect.startEffect).toHaveBeenCalledTimes(2)
			expect(virtualBackground._jitsiStreamBackgroundEffect.updateInputStream).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.stopEffect).toHaveBeenCalledTimes(1)
			expect(originalEffectOutputTrack.stop).toHaveBeenCalledTimes(1)
			expect(effectOutputTrack.stop).toHaveBeenCalledTimes(0)
		})

		test('sets input track as its output track when setting another now disabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')
			const inputTrack2 = newMediaStreamTrackMock('input2')

			virtualBackground._setInputTrack('default', inputTrack)
			inputTrack2.enabled = false
			virtualBackground._setInputTrack('default', inputTrack2)

			expect(virtualBackground._setOutputTrack).toHaveBeenCalledTimes(2)
			expect(virtualBackground._setOutputTrack).toHaveBeenNthCalledWith(1, 'default', effectOutputTrack)
			expect(virtualBackground._setOutputTrack).toHaveBeenNthCalledWith(2, 'default', inputTrack2)
			expect(virtualBackground._jitsiStreamBackgroundEffect.startEffect).toHaveBeenCalledTimes(1)
			expect(virtualBackground._jitsiStreamBackgroundEffect.updateInputStream).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.stopEffect).toHaveBeenCalledTimes(1)
			expect(effectOutputTrack.stop).toHaveBeenCalledTimes(1)
		})

		test('sets effect output track as its output track when setting another now enabled input track', () => {
			const inputTrack = newMediaStreamTrackMock('input')
			const inputTrack2 = newMediaStreamTrackMock('input2')

			inputTrack.enabled = false
			virtualBackground._setInputTrack('default', inputTrack)
			virtualBackground._setInputTrack('default', inputTrack2)

			expect(virtualBackground._setOutputTrack).toHaveBeenCalledTimes(2)
			expect(virtualBackground._setOutputTrack).toHaveBeenNthCalledWith(1, 'default', inputTrack)
			expect(virtualBackground._setOutputTrack).toHaveBeenNthCalledWith(2, 'default', effectOutputTrack)
			expect(virtualBackground._jitsiStreamBackgroundEffect.startEffect).toHaveBeenCalledTimes(1)
			expect(virtualBackground._jitsiStreamBackgroundEffect.updateInputStream).toHaveBeenCalledTimes(0)
			expect(virtualBackground._jitsiStreamBackgroundEffect.stopEffect).toHaveBeenCalledTimes(0)
			expect(effectOutputTrack.stop).toHaveBeenCalledTimes(0)
		})
	})
})
