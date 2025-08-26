/*
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { vi } from 'vitest'
import { mediaDevicesManager } from '../../webrtc/index.js'
import MediaDevicesSource from './MediaDevicesSource.js'

/**
 * Helper function to create MediaStreamTrack mocks with just the attributes and
 * methods used by MediaDevicesSource.
 *
 * @param {string} id the ID of the track
 * @param {kind} kind the kind ("audio" or "video") of the track
 * @param {deviceId} deviceId the ID of the device that this track belongs to
 */
function newMediaStreamTrackMock(id, kind, deviceId = undefined) {
	/**
	 * MediaStreamTrackMock constructor.
	 */
	function MediaStreamTrackMock() {
		this._endedEventHandlers = []
		this.id = id
		this.kind = kind
		this.addEventListener = vi.fn((eventName, eventHandler) => {
			if (eventName !== 'ended') {
				return
			}

			this._endedEventHandlers.push(eventHandler)
		})
		this.removeEventListener = vi.fn((eventName, eventHandler) => {
			if (eventName !== 'ended') {
				return
			}

			const index = this._endedEventHandlers.indexOf(eventHandler)
			if (index !== -1) {
				this._endedEventHandlers.splice(index, 1)
			}
		})
		this.stop = vi.fn()
		this.getSettings = vi.fn(() => {
			return {
				deviceId: deviceId || kind + '-device',
			}
		})
	}
	return new MediaStreamTrackMock()
}

/**
 * Helper function to create MediaStreamTrack mocks with just the attributes and
 * methods used by MediaDevicesSource.
 *
 * @param {string} id the ID of the track
 */
function newMediaStreamMock(id) {
	/**
	 * MediaStreamMock constructor.
	 */
	function MediaStreamMock() {
		this._tracks = []
		this.id = id
		this.getTracks = vi.fn(() => {
			return this._tracks
		})
		this.getAudioTracks = vi.fn(() => {
			return this._tracks.filter((track) => track.kind === 'audio')
		})
		this.getVideoTracks = vi.fn(() => {
			return this._tracks.filter((track) => track.kind === 'video')
		})
	}
	return new MediaStreamMock()
}

describe('MediaDevicesSource', () => {
	let mediaDevicesSource
	let retryNoVideoCallback
	let getUserMediaAudioTrack
	let getUserMediaVideoTrack
	let expectedAudioTrack
	let expectedVideoTrack

	beforeAll(() => {
		vi.spyOn(mediaDevicesManager, 'on')
		vi.spyOn(mediaDevicesManager, 'off')

		mediaDevicesManager._storeDeviceId = vi.fn()
	})

	beforeEach(() => {
		mediaDevicesSource = new MediaDevicesSource()

		retryNoVideoCallback = vi.fn()

		// Clear event listeners
		mediaDevicesManager._handlers = []
		mediaDevicesManager.on.mockClear()
		mediaDevicesManager.off.mockClear()

		vi.spyOn(mediaDevicesManager, 'getUserMedia').mockImplementation(async (constraints) => {
			// MediaDevicesManager.getUserMedia() updates the received
			// constraints if audio or video is requested but no audio or video
			// device is selected.
			if (constraints.audio && !getUserMediaAudioTrack) {
				constraints.audio = false
			}
			if (constraints.video && !getUserMediaVideoTrack) {
				constraints.video = false
			}

			// MediaDevicesManager.getUserMedia() will throw if the given
			// constraints reject both audio and video, or if there are no
			// selected devices.
			if (!constraints.audio && !constraints.video) {
				throw new Error('Audio and/or video is required')
			}

			const stream = newMediaStreamMock(JSON.stringify(constraints))

			if (constraints.audio && getUserMediaAudioTrack) {
				stream._tracks.push(getUserMediaAudioTrack)
			}
			if (constraints.video && getUserMediaVideoTrack) {
				stream._tracks.push(getUserMediaVideoTrack)
			}

			return stream
		})

		getUserMediaAudioTrack = null
		getUserMediaVideoTrack = null
		expectedAudioTrack = null
		expectedVideoTrack = null
		console.debug = vi.fn()
	})

	afterEach(() => {
		vi.clearAllMocks()
		mediaDevicesManager.getUserMedia.mockRestore()
	})

	afterAll(() => {
		vi.restoreAllMocks()
	})

	describe('get allowed state', () => {
		test('audio and video are allowed by default', () => {
			expect(mediaDevicesSource.isAudioAllowed()).toBe(true)
			expect(mediaDevicesSource.isVideoAllowed()).toBe(true)
		})

		test('after modifying the audio state', () => {
			mediaDevicesSource.setAudioAllowed(false)

			expect(mediaDevicesSource.isAudioAllowed()).toBe(false)
			expect(mediaDevicesSource.isVideoAllowed()).toBe(true)
		})

		test('after modifying the video state', () => {
			mediaDevicesSource.setVideoAllowed(false)

			expect(mediaDevicesSource.isAudioAllowed()).toBe(true)
			expect(mediaDevicesSource.isVideoAllowed()).toBe(false)
		})
	})

	describe('start', () => {
		/**
		 * Checks the expected output tracks and event listeners.
		 */
		function assertStateAfterStart() {
			expect(mediaDevicesSource.getOutputTrack('audio')).toBe(expectedAudioTrack)
			expect(mediaDevicesSource.getOutputTrack('video')).toBe(expectedVideoTrack)

			expect(mediaDevicesManager.on).toHaveBeenCalledTimes(2)
			expect(mediaDevicesManager.on).toHaveBeenNthCalledWith(1, 'change:audioInputId', mediaDevicesSource._handleAudioInputIdChangedBound)
			expect(mediaDevicesManager.on).toHaveBeenNthCalledWith(2, 'change:videoInputId', mediaDevicesSource._handleVideoInputIdChangedBound)
		}

		describe('with audio and video', () => {
			test('when there are audio and video devices', () => {
				getUserMediaAudioTrack = newMediaStreamTrackMock('audio', 'audio')
				getUserMediaVideoTrack = newMediaStreamTrackMock('video', 'video')

				expectedAudioTrack = getUserMediaAudioTrack
				expectedVideoTrack = getUserMediaVideoTrack

				return mediaDevicesSource.start(retryNoVideoCallback).then(() => {
					assertStateAfterStart()

					expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
					expect(retryNoVideoCallback).not.toHaveBeenCalled()
				})
			})

			test('when there are audio and video devices but video could not be got', () => {
				getUserMediaAudioTrack = newMediaStreamTrackMock('audio', 'audio')
				getUserMediaVideoTrack = newMediaStreamTrackMock('video', 'video')

				vi.spyOn(mediaDevicesManager, 'getUserMedia').mockImplementationOnce(async (constraints) => {
					throw new Error('Video could not be got')
				})

				expectedAudioTrack = getUserMediaAudioTrack

				return mediaDevicesSource.start(retryNoVideoCallback).then(() => {
					assertStateAfterStart()

					expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(2)
					expect(retryNoVideoCallback).toHaveBeenCalledTimes(1)
					expect(retryNoVideoCallback).toHaveBeenCalledWith(new Error('Video could not be got'))
				})
			})

			test('when there are audio and video devices but none could be got', () => {
				getUserMediaAudioTrack = newMediaStreamTrackMock('audio', 'audio')
				getUserMediaVideoTrack = newMediaStreamTrackMock('video', 'video')

				vi.spyOn(mediaDevicesManager, 'getUserMedia').mockImplementationOnce(async (constraints) => {
					throw new Error('Audio and video could not be got')
				})
				vi.spyOn(mediaDevicesManager, 'getUserMedia').mockImplementationOnce(async (constraints) => {
					throw new Error('Audio could not be got')
				})

				expect.hasAssertions()

				return mediaDevicesSource.start(retryNoVideoCallback).catch(() => {
					assertStateAfterStart()

					expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(2)
					expect(retryNoVideoCallback).toHaveBeenCalledTimes(1)
					expect(retryNoVideoCallback).toHaveBeenCalledWith(new Error('Audio and video could not be got'))
				})
			})

			test('when there are audio but no video devices', () => {
				getUserMediaAudioTrack = newMediaStreamTrackMock('audio', 'audio')

				expectedAudioTrack = getUserMediaAudioTrack

				return mediaDevicesSource.start(retryNoVideoCallback).then(() => {
					assertStateAfterStart()

					expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
					expect(retryNoVideoCallback).not.toHaveBeenCalled()
				})
			})

			test('when there are audio but no video devices and audio could not be got', () => {
				getUserMediaAudioTrack = newMediaStreamTrackMock('audio', 'audio')

				vi.spyOn(mediaDevicesManager, 'getUserMedia').mockImplementationOnce(async (constraints) => {
					constraints.video = false

					throw new Error('Audio could not be got')
				})

				expect.hasAssertions()

				return mediaDevicesSource.start(retryNoVideoCallback).catch(() => {
					assertStateAfterStart()

					expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
					expect(retryNoVideoCallback).not.toHaveBeenCalled()
				})
			})

			test('when there are video but no audio devices', () => {
				getUserMediaVideoTrack = newMediaStreamTrackMock('video', 'video')

				expectedVideoTrack = getUserMediaVideoTrack

				return mediaDevicesSource.start(retryNoVideoCallback).then(() => {
					assertStateAfterStart()

					expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
					expect(retryNoVideoCallback).not.toHaveBeenCalled()
				})
			})

			test('when there are video but no audio devices and video could not be got', () => {
				getUserMediaVideoTrack = newMediaStreamTrackMock('video', 'video')

				vi.spyOn(mediaDevicesManager, 'getUserMedia').mockImplementationOnce(async (constraints) => {
					constraints.audio = false

					throw new Error('Video could not be got')
				})

				expect.hasAssertions()

				return mediaDevicesSource.start(retryNoVideoCallback).catch(() => {
					assertStateAfterStart()

					expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
					expect(retryNoVideoCallback).not.toHaveBeenCalled()
				})
			})

			test('when there are no audio nor video devices', () => {
				expect.hasAssertions()

				return mediaDevicesSource.start(retryNoVideoCallback).catch(() => {
					assertStateAfterStart()

					expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
					expect(retryNoVideoCallback).not.toHaveBeenCalled()
				})
			})
		})

		describe('with audio', () => {
			beforeEach(() => {
				mediaDevicesSource.setVideoAllowed(false)
			})

			test('when there are audio and video devices', () => {
				getUserMediaAudioTrack = newMediaStreamTrackMock('audio', 'audio')
				getUserMediaVideoTrack = newMediaStreamTrackMock('video', 'video')

				expectedAudioTrack = getUserMediaAudioTrack

				return mediaDevicesSource.start(retryNoVideoCallback).then(() => {
					assertStateAfterStart()

					expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
					expect(retryNoVideoCallback).not.toHaveBeenCalled()
				})
			})

			test('when there are audio and video devices but audio could not be got', () => {
				getUserMediaAudioTrack = newMediaStreamTrackMock('audio', 'audio')
				getUserMediaVideoTrack = newMediaStreamTrackMock('video', 'video')

				vi.spyOn(mediaDevicesManager, 'getUserMedia').mockImplementationOnce(async (constraints) => {
					throw new Error('Audio could not be got')
				})

				expect.hasAssertions()

				return mediaDevicesSource.start(retryNoVideoCallback).catch(() => {
					assertStateAfterStart()

					expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
					expect(retryNoVideoCallback).not.toHaveBeenCalled()
				})
			})

			test('when there are audio but no video devices', () => {
				getUserMediaAudioTrack = newMediaStreamTrackMock('audio', 'audio')

				expectedAudioTrack = getUserMediaAudioTrack

				return mediaDevicesSource.start(retryNoVideoCallback).then(() => {
					assertStateAfterStart()

					expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
					expect(retryNoVideoCallback).not.toHaveBeenCalled()
				})
			})

			test('when there are audio but no video devices and audio could not be got', () => {
				getUserMediaAudioTrack = newMediaStreamTrackMock('audio', 'audio')

				vi.spyOn(mediaDevicesManager, 'getUserMedia').mockImplementationOnce(async (constraints) => {
					throw new Error('Audio could not be got')
				})

				expect.hasAssertions()

				return mediaDevicesSource.start(retryNoVideoCallback).catch(() => {
					assertStateAfterStart()

					expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
					expect(retryNoVideoCallback).not.toHaveBeenCalled()
				})
			})

			test('when there are video but no audio devices', () => {
				getUserMediaVideoTrack = newMediaStreamTrackMock('video', 'video')

				expect.hasAssertions()

				return mediaDevicesSource.start(retryNoVideoCallback).catch(() => {
					assertStateAfterStart()

					expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
					expect(retryNoVideoCallback).not.toHaveBeenCalled()
				})
			})

			test('when there are no audio nor video devices', () => {
				expect.hasAssertions()

				return mediaDevicesSource.start(retryNoVideoCallback).catch(() => {
					assertStateAfterStart()

					expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
					expect(retryNoVideoCallback).not.toHaveBeenCalled()
				})
			})
		})

		describe('with video', () => {
			beforeEach(() => {
				mediaDevicesSource.setAudioAllowed(false)
			})

			test('when there are audio and video devices', () => {
				getUserMediaAudioTrack = newMediaStreamTrackMock('audio', 'audio')
				getUserMediaVideoTrack = newMediaStreamTrackMock('video', 'video')

				expectedVideoTrack = getUserMediaVideoTrack

				return mediaDevicesSource.start(retryNoVideoCallback).then(() => {
					assertStateAfterStart()

					expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
					expect(retryNoVideoCallback).not.toHaveBeenCalled()
				})
			})

			test('when there are audio and video devices but video could not be got', () => {
				getUserMediaAudioTrack = newMediaStreamTrackMock('audio', 'audio')
				getUserMediaVideoTrack = newMediaStreamTrackMock('video', 'video')

				vi.spyOn(mediaDevicesManager, 'getUserMedia').mockImplementationOnce(async (constraints) => {
					throw new Error('Video could not be got')
				})

				expect.hasAssertions()

				return mediaDevicesSource.start(retryNoVideoCallback).catch(() => {
					assertStateAfterStart()

					expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
					expect(retryNoVideoCallback).not.toHaveBeenCalled()
				})
			})

			test('when there are audio but no video devices', () => {
				getUserMediaAudioTrack = newMediaStreamTrackMock('audio', 'audio')

				expect.hasAssertions()

				return mediaDevicesSource.start(retryNoVideoCallback).catch(() => {
					assertStateAfterStart()

					expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
					expect(retryNoVideoCallback).not.toHaveBeenCalled()
				})
			})

			test('when there are video but no audio devices', () => {
				getUserMediaVideoTrack = newMediaStreamTrackMock('video', 'video')

				expectedVideoTrack = getUserMediaVideoTrack

				return mediaDevicesSource.start(retryNoVideoCallback).then(() => {
					assertStateAfterStart()

					expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
					expect(retryNoVideoCallback).not.toHaveBeenCalled()
				})
			})

			test('when there are video but no audio devices and video could not be got', () => {
				getUserMediaVideoTrack = newMediaStreamTrackMock('video', 'video')

				vi.spyOn(mediaDevicesManager, 'getUserMedia').mockImplementationOnce(async (constraints) => {
					throw new Error('Video could not be got')
				})

				expect.hasAssertions()

				return mediaDevicesSource.start(retryNoVideoCallback).catch(() => {
					assertStateAfterStart()

					expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
					expect(retryNoVideoCallback).not.toHaveBeenCalled()
				})
			})

			test('when there are no audio nor video devices', () => {
				expect.hasAssertions()

				return mediaDevicesSource.start(retryNoVideoCallback).catch(() => {
					assertStateAfterStart()

					expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
					expect(retryNoVideoCallback).not.toHaveBeenCalled()
				})
			})
		})

		describe('with no audio nor video', () => {
			beforeEach(() => {
				mediaDevicesSource.setAudioAllowed(false)
				mediaDevicesSource.setVideoAllowed(false)
			})

			test('when there are audio and video devices', () => {
				getUserMediaAudioTrack = newMediaStreamTrackMock('audio', 'audio')
				getUserMediaVideoTrack = newMediaStreamTrackMock('video', 'video')

				expect.hasAssertions()

				return mediaDevicesSource.start(retryNoVideoCallback).catch(() => {
					assertStateAfterStart()

					expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
					expect(retryNoVideoCallback).not.toHaveBeenCalled()
				})
			})

			test('when there are audio but no video devices', () => {
				getUserMediaAudioTrack = newMediaStreamTrackMock('audio', 'audio')

				expect.hasAssertions()

				return mediaDevicesSource.start(retryNoVideoCallback).catch(() => {
					assertStateAfterStart()

					expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
					expect(retryNoVideoCallback).not.toHaveBeenCalled()
				})
			})

			test('when there are video but no audio devices', () => {
				getUserMediaVideoTrack = newMediaStreamTrackMock('video', 'video')

				expect.hasAssertions()

				return mediaDevicesSource.start(retryNoVideoCallback).catch(() => {
					assertStateAfterStart()

					expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
					expect(retryNoVideoCallback).not.toHaveBeenCalled()
				})
			})

			test('when there are no audio nor video devices', () => {
				expect.hasAssertions()

				return mediaDevicesSource.start(retryNoVideoCallback).catch(() => {
					assertStateAfterStart()

					expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
					expect(retryNoVideoCallback).not.toHaveBeenCalled()
				})
			})
		})
	})

	describe('change input id in MediaDevicesManager', () => {
		beforeEach(() => {
			getUserMediaAudioTrack = newMediaStreamTrackMock('audio', 'audio', 'audio-device')
			getUserMediaVideoTrack = newMediaStreamTrackMock('video', 'video', 'video-device')
		})

		test('to same device', async () => {
			await mediaDevicesSource.start(retryNoVideoCallback)

			mediaDevicesManager.set('audioInputId', 'audio-device')

			expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
			expect(mediaDevicesSource.getOutputTrack('audio')).toBe(getUserMediaAudioTrack)
			expect(getUserMediaAudioTrack.stop).not.toHaveBeenCalled()
		})

		test('from a device to no device', async () => {
			await mediaDevicesSource.start(retryNoVideoCallback)

			mediaDevicesManager.set('audioInputId', null)

			expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
			expect(mediaDevicesSource.getOutputTrack('audio')).toBe(null)
			expect(getUserMediaAudioTrack.stop).toHaveBeenCalledTimes(1)
		})

		test('from a device to another device', async () => {
			await mediaDevicesSource.start(retryNoVideoCallback)

			const originalGetUserMediaAudioTrack = getUserMediaAudioTrack

			getUserMediaAudioTrack = newMediaStreamTrackMock('audio-2', 'audio', 'audio-device-2')

			mediaDevicesManager.set('audioInputId', 'audio-device-2')

			// Wait until getUserMedia(), internally called by
			// MediaDevicesSource when the id is set, finishes.
			await new Promise(process.nextTick)

			expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(2)
			expect(mediaDevicesSource.getOutputTrack('audio')).toBe(getUserMediaAudioTrack)
			expect(getUserMediaAudioTrack.stop).not.toHaveBeenCalled()
			expect(originalGetUserMediaAudioTrack.stop).toHaveBeenCalledTimes(1)
		})

		test('from a device to another device but track could not be got', async () => {
			await mediaDevicesSource.start(retryNoVideoCallback)

			vi.spyOn(mediaDevicesManager, 'getUserMedia').mockImplementationOnce(async (constraints) => {
				throw new Error('Audio could not be got')
			})

			mediaDevicesManager.set('audioInputId', 'audio-device-2')

			// Wait until getUserMedia(), internally called by
			// MediaDevicesSource when the id is set, finishes.
			await new Promise(process.nextTick)

			// Verify that the error case is handled correctly
			expect(mediaDevicesSource.getOutputTrack('audio')).toBe(null)
			expect(getUserMediaAudioTrack.stop).toHaveBeenCalledTimes(1)
		})

		test('from a device to another device when not allowed', async () => {
			mediaDevicesSource.setAudioAllowed(false)

			await mediaDevicesSource.start(retryNoVideoCallback)

			getUserMediaAudioTrack = newMediaStreamTrackMock('audio-2', 'audio', 'audio-device-2')

			mediaDevicesManager.set('audioInputId', 'audio-device-2')

			expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
			expect(mediaDevicesSource.getOutputTrack('audio')).toBe(null)
		})

		test('from no device to a device', async () => {
			getUserMediaAudioTrack = null
			getUserMediaVideoTrack = null

			try {
				await mediaDevicesSource.start(retryNoVideoCallback)
			} catch (exception) {
				// expected Error: Audio and/or video is required
			}

			getUserMediaAudioTrack = newMediaStreamTrackMock('audio', 'audio', 'audio-device')

			mediaDevicesManager.set('audioInputId', 'audio-device')

			// Wait until getUserMedia(), internally called by
			// MediaDevicesSource when the id is set, finishes.
			await new Promise(process.nextTick)

			expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(2)
			expect(mediaDevicesSource.getOutputTrack('audio')).toBe(getUserMediaAudioTrack)
			expect(getUserMediaAudioTrack.stop).not.toHaveBeenCalled()
		})

		test('from no device to a device but track could not be got', async () => {
			getUserMediaAudioTrack = null
			getUserMediaVideoTrack = null

			try {
				await mediaDevicesSource.start(retryNoVideoCallback)
			} catch (exception) {
				// expected Error: Audio and/or video is required
			}

			vi.spyOn(mediaDevicesManager, 'getUserMedia').mockImplementationOnce(async (constraints) => {
				throw new Error('Audio could not be got')
			})

			mediaDevicesManager.set('audioInputId', 'audio-device')

			// Wait until getUserMedia(), internally called by
			// MediaDevicesSource when the id is set, finishes.
			await new Promise(process.nextTick)

			expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(2)
			expect(mediaDevicesSource.getOutputTrack('audio')).toBe(null)
		})

		test('from no device to a device when not allowed', async () => {
			getUserMediaAudioTrack = null
			getUserMediaVideoTrack = null

			try {
				await mediaDevicesSource.start(retryNoVideoCallback)
			} catch (exception) {
				// expected Error: Audio and/or video is required
			}

			mediaDevicesSource.setAudioAllowed(false)

			getUserMediaAudioTrack = newMediaStreamTrackMock('audio', 'audio', 'audio-device')

			mediaDevicesManager.set('audioInputId', 'audio-device')

			expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
			expect(mediaDevicesSource.getOutputTrack('audio')).toBe(null)
		})

		test('several times in a row before the track for the first one was got', async () => {
			await mediaDevicesSource.start(retryNoVideoCallback)

			const originalGetUserMediaAudioTrack = getUserMediaAudioTrack

			getUserMediaAudioTrack = newMediaStreamTrackMock('audio-2', 'audio', 'audio-device-2')

			mediaDevicesManager.set('audioInputId', 'audio-device-2')

			const firstChangedGetUserMediaAudioTrack = getUserMediaAudioTrack

			getUserMediaAudioTrack = newMediaStreamTrackMock('audio-3', 'audio', 'audio-device-3')

			mediaDevicesManager.set('audioInputId', 'audio-device-3')

			getUserMediaAudioTrack = newMediaStreamTrackMock('audio-4', 'audio', 'audio-device-4')

			mediaDevicesManager.set('audioInputId', 'audio-device-4')

			getUserMediaAudioTrack = newMediaStreamTrackMock('audio-5', 'audio', 'audio-device-5')

			mediaDevicesManager.set('audioInputId', 'audio-device-5')

			getUserMediaAudioTrack = newMediaStreamTrackMock('audio-6', 'audio', 'audio-device-6')

			mediaDevicesManager.set('audioInputId', 'audio-device-6')

			// Wait until getUserMedia(), internally called by
			// MediaDevicesSource when the id is set, finishes.
			await new Promise(process.nextTick)

			expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(3)
			expect(mediaDevicesSource.getOutputTrack('audio')).toBe(getUserMediaAudioTrack)
			expect(getUserMediaAudioTrack.stop).not.toHaveBeenCalled()
			expect(originalGetUserMediaAudioTrack.stop).toHaveBeenCalledTimes(1)
			expect(firstChangedGetUserMediaAudioTrack.stop).toHaveBeenCalledTimes(1)
		})

		test('several times in a row before the track for the first one was got finally setting again the first one', async () => {
			await mediaDevicesSource.start(retryNoVideoCallback)

			const originalGetUserMediaAudioTrack = getUserMediaAudioTrack

			getUserMediaAudioTrack = newMediaStreamTrackMock('audio-2', 'audio', 'audio-device-2')

			mediaDevicesManager.set('audioInputId', 'audio-device-2')

			const firstChangedGetUserMediaAudioTrack = getUserMediaAudioTrack

			getUserMediaAudioTrack = newMediaStreamTrackMock('audio-3', 'audio', 'audio-device-3')

			mediaDevicesManager.set('audioInputId', 'audio-device-3')

			getUserMediaAudioTrack = newMediaStreamTrackMock('audio-4', 'audio', 'audio-device-4')

			mediaDevicesManager.set('audioInputId', 'audio-device-4')

			getUserMediaAudioTrack = newMediaStreamTrackMock('audio-5', 'audio', 'audio-device-5')

			mediaDevicesManager.set('audioInputId', 'audio-device-5')

			mediaDevicesManager.set('audioInputId', 'audio-device-2')

			// Wait until getUserMedia(), internally called by
			// MediaDevicesSource when the id is set, finishes.
			await new Promise(process.nextTick)

			expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(2)
			expect(mediaDevicesSource.getOutputTrack('audio')).toBe(firstChangedGetUserMediaAudioTrack)
			expect(firstChangedGetUserMediaAudioTrack.stop).not.toHaveBeenCalled()
			expect(originalGetUserMediaAudioTrack.stop).toHaveBeenCalledTimes(1)
		})
	})

	describe('allow and disallow audio and video', () => {
		beforeEach(() => {
			getUserMediaAudioTrack = newMediaStreamTrackMock('audio', 'audio')
			getUserMediaVideoTrack = newMediaStreamTrackMock('video', 'video')
		})

		test('disallow and allow again before starting', async () => {
			mediaDevicesManager.set('audioInputId', 'audio-device')

			mediaDevicesSource.setAudioAllowed(false)
			mediaDevicesSource.setAudioAllowed(true)

			expect(mediaDevicesManager.getUserMedia).not.toHaveBeenCalled()
		})

		test('disallow and allow again after stopping', async () => {
			mediaDevicesManager.set('audioInputId', 'audio-device')

			await mediaDevicesSource.start(retryNoVideoCallback)

			mediaDevicesSource.stop()

			mediaDevicesSource.setAudioAllowed(false)
			mediaDevicesSource.setAudioAllowed(true)

			expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
		})

		test('allow while active with a device', async () => {
			mediaDevicesManager.set('audioInputId', 'audio-device')

			mediaDevicesSource.setAudioAllowed(false)

			await mediaDevicesSource.start(retryNoVideoCallback)

			mediaDevicesSource.setAudioAllowed(true)

			// Wait until getUserMedia(), internally called by
			// MediaDevicesSource when allowing the media, finishes.
			await new Promise(process.nextTick)

			expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(2)
			expect(mediaDevicesSource.getOutputTrack('audio')).toBe(getUserMediaAudioTrack)
			expect(getUserMediaAudioTrack.stop).not.toHaveBeenCalled()
		})

		test('allow while active with no device', async () => {
			mediaDevicesManager.set('audioInputId', null)

			mediaDevicesSource.setAudioAllowed(false)

			await mediaDevicesSource.start(retryNoVideoCallback)

			mediaDevicesSource.setAudioAllowed(true)

			expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
			expect(mediaDevicesSource.getOutputTrack('audio')).toBe(null)
		})

		test('allow again while active', async () => {
			mediaDevicesManager.set('audioInputId', 'audio-device')

			await mediaDevicesSource.start(retryNoVideoCallback)

			mediaDevicesSource.setAudioAllowed(true)

			expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
			expect(mediaDevicesSource.getOutputTrack('audio')).toBe(getUserMediaAudioTrack)
			expect(getUserMediaAudioTrack.stop).not.toHaveBeenCalled()
		})

		test('disallow while active with a device', async () => {
			mediaDevicesManager.set('audioInputId', 'audio-device')

			await mediaDevicesSource.start(retryNoVideoCallback)

			mediaDevicesSource.setAudioAllowed(false)

			expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
			expect(mediaDevicesSource.getOutputTrack('audio')).toBe(null)
			expect(getUserMediaAudioTrack.stop).toHaveBeenCalledTimes(1)
		})

		test('disallow while active with no device', async () => {
			mediaDevicesManager.set('audioInputId', null)

			await mediaDevicesSource.start(retryNoVideoCallback)

			mediaDevicesSource.setAudioAllowed(false)

			expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
			expect(mediaDevicesSource.getOutputTrack('audio')).toBe(null)
		})

		test('disallow again while active', async () => {
			mediaDevicesManager.set('audioInputId', 'audio-device')

			await mediaDevicesSource.start(retryNoVideoCallback)

			mediaDevicesSource.setAudioAllowed(false)
			mediaDevicesSource.setAudioAllowed(false)

			expect(mediaDevicesManager.getUserMedia).toHaveBeenCalledTimes(1)
			expect(mediaDevicesSource.getOutputTrack('audio')).toBe(null)
			expect(getUserMediaAudioTrack.stop).toHaveBeenCalledTimes(1)
		})
	})

	describe('stop', () => {
		test('with audio and video tracks', async () => {
			getUserMediaAudioTrack = newMediaStreamTrackMock('audio', 'audio')
			getUserMediaVideoTrack = newMediaStreamTrackMock('video', 'video')

			await mediaDevicesSource.start(retryNoVideoCallback)

			mediaDevicesSource.stop()

			expect(mediaDevicesSource.getOutputTrack('audio')).toBe(null)
			expect(mediaDevicesSource.getOutputTrack('video')).toBe(null)
			expect(getUserMediaAudioTrack.stop).toHaveBeenCalledTimes(1)
			expect(getUserMediaVideoTrack.stop).toHaveBeenCalledTimes(1)
			expect(mediaDevicesManager.off).toHaveBeenCalledTimes(2)
			expect(mediaDevicesManager.off).toHaveBeenNthCalledWith(1, 'change:audioInputId', mediaDevicesSource._handleAudioInputIdChangedBound)
			expect(mediaDevicesManager.off).toHaveBeenNthCalledWith(2, 'change:videoInputId', mediaDevicesSource._handleVideoInputIdChangedBound)
		})

		test('with audio track', async () => {
			getUserMediaAudioTrack = newMediaStreamTrackMock('audio', 'audio')

			await mediaDevicesSource.start(retryNoVideoCallback)

			mediaDevicesSource.stop()

			expect(mediaDevicesSource.getOutputTrack('audio')).toBe(null)
			expect(getUserMediaAudioTrack.stop).toHaveBeenCalledTimes(1)
			expect(mediaDevicesManager.off).toHaveBeenCalledTimes(2)
			expect(mediaDevicesManager.off).toHaveBeenNthCalledWith(1, 'change:audioInputId', mediaDevicesSource._handleAudioInputIdChangedBound)
			expect(mediaDevicesManager.off).toHaveBeenNthCalledWith(2, 'change:videoInputId', mediaDevicesSource._handleVideoInputIdChangedBound)
		})

		test('with video track', async () => {
			getUserMediaVideoTrack = newMediaStreamTrackMock('video', 'video')

			await mediaDevicesSource.start(retryNoVideoCallback)

			mediaDevicesSource.stop()

			expect(mediaDevicesSource.getOutputTrack('video')).toBe(null)
			expect(getUserMediaVideoTrack.stop).toHaveBeenCalledTimes(1)
			expect(mediaDevicesManager.off).toHaveBeenCalledTimes(2)
			expect(mediaDevicesManager.off).toHaveBeenNthCalledWith(1, 'change:audioInputId', mediaDevicesSource._handleAudioInputIdChangedBound)
			expect(mediaDevicesManager.off).toHaveBeenNthCalledWith(2, 'change:videoInputId', mediaDevicesSource._handleVideoInputIdChangedBound)
		})

		test('with no tracks', async () => {
			try {
				await mediaDevicesSource.start(retryNoVideoCallback)
			} catch (exception) {
				// expected Error: Audio and/or video is required
			}

			mediaDevicesSource.stop()

			expect(mediaDevicesManager.off).toHaveBeenCalledTimes(2)
			expect(mediaDevicesManager.off).toHaveBeenNthCalledWith(1, 'change:audioInputId', mediaDevicesSource._handleAudioInputIdChangedBound)
			expect(mediaDevicesManager.off).toHaveBeenNthCalledWith(2, 'change:videoInputId', mediaDevicesSource._handleVideoInputIdChangedBound)
		})
	})
})
