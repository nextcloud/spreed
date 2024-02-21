/**
 *
 * @copyright Copyright (c) 2022, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

import RemoteVideoBlocker from './RemoteVideoBlocker.js'

describe('RemoteVideoBlocker', () => {
	let callParticipantModel
	let remoteVideoBlocker

	beforeEach(() => {
		jest.useFakeTimers()
		console.error = jest.fn()

		callParticipantModel = {
			setVideoBlocked: jest.fn(),
		}

		remoteVideoBlocker = new RemoteVideoBlocker(callParticipantModel)
	})

	afterEach(() => {
		jest.clearAllMocks()
	})

	test('blocks the video by default if not shown in some seconds', () => {
		jest.advanceTimersByTime(4000)

		expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(0)

		jest.advanceTimersByTime(1000)

		expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)
		expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledWith(true)

		expect(remoteVideoBlocker.isVideoEnabled()).toBe(true)
	})

	describe('set video enabled', () => {
		test('immediately blocks the video', () => {
			remoteVideoBlocker.increaseVisibleCounter()

			remoteVideoBlocker.setVideoEnabled(false)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)
			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledWith(true)

			expect(remoteVideoBlocker.isVideoEnabled()).toBe(false)
		})
		test('immediately unblocks the video', () => {
			remoteVideoBlocker.increaseVisibleCounter()

			remoteVideoBlocker.setVideoEnabled(false)
			remoteVideoBlocker.setVideoEnabled(true)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(2)
			expect(callParticipantModel.setVideoBlocked).toHaveBeenNthCalledWith(2, false)

			expect(remoteVideoBlocker.isVideoEnabled()).toBe(true)
		})
	})

	describe('set video visible', () => {
		test('does nothing if shown', () => {
			remoteVideoBlocker.increaseVisibleCounter()

			jest.runAllTimers()

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(0)
			expect(remoteVideoBlocker._visibleCounter).toBe(1)

			expect(remoteVideoBlocker.isVideoEnabled()).toBe(true)
		})

		test('does nothing if hidden without showing first', () => {
			jest.runAllTimers()

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)
			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledWith(true)

			remoteVideoBlocker.decreaseVisibleCounter()

			jest.runAllTimers()

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)
			expect(remoteVideoBlocker._visibleCounter).toBe(0)

			expect(remoteVideoBlocker.isVideoEnabled()).toBe(true)
		})

		test('blocks the video after some seconds when hidden', () => {
			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.decreaseVisibleCounter()

			jest.advanceTimersByTime(4000)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)
			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledWith(true)
			expect(remoteVideoBlocker._visibleCounter).toBe(0)

			expect(remoteVideoBlocker.isVideoEnabled()).toBe(true)
		})

		test('does nothing if shown again before blocking', () => {
			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.decreaseVisibleCounter()

			jest.advanceTimersByTime(4000)

			remoteVideoBlocker.increaseVisibleCounter()

			jest.runAllTimers()

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(0)
			expect(remoteVideoBlocker._visibleCounter).toBe(1)

			expect(remoteVideoBlocker.isVideoEnabled()).toBe(true)
		})

		test('immediately unblocks the video after showing', () => {
			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.decreaseVisibleCounter()

			jest.runAllTimers()

			remoteVideoBlocker.increaseVisibleCounter()

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(2)
			expect(callParticipantModel.setVideoBlocked).toHaveBeenNthCalledWith(2, false)
			expect(remoteVideoBlocker._visibleCounter).toBe(1)

			expect(remoteVideoBlocker.isVideoEnabled()).toBe(true)
		})

		test('does nothing if not fully hidden', () => {
			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.decreaseVisibleCounter()
			remoteVideoBlocker.decreaseVisibleCounter()
			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.decreaseVisibleCounter()

			jest.runAllTimers()

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(0)
			expect(remoteVideoBlocker._visibleCounter).toBe(2)

			expect(remoteVideoBlocker.isVideoEnabled()).toBe(true)
		})
	})

	describe('set video enabled and visible', () => {
		test('immediately blocks the video if disabled when visible', () => {
			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.increaseVisibleCounter()

			remoteVideoBlocker.setVideoEnabled(false)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)
			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledWith(true)

			jest.runAllTimers()

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)

			expect(remoteVideoBlocker.isVideoEnabled()).toBe(false)
		})

		test('immediately blocks the video if disabled before blocking after hidden', () => {
			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.decreaseVisibleCounter()

			jest.advanceTimersByTime(4000)

			remoteVideoBlocker.setVideoEnabled(false)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)
			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledWith(true)

			jest.runAllTimers()

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)

			expect(remoteVideoBlocker.isVideoEnabled()).toBe(false)
		})

		test('blocks the video after some seconds if hidden when enabled', () => {
			remoteVideoBlocker.setVideoEnabled(true)

			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.decreaseVisibleCounter()

			jest.advanceTimersByTime(4000)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)
			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledWith(true)

			expect(remoteVideoBlocker.isVideoEnabled()).toBe(true)
		})

		test('does nothing if disabled when hidden', () => {
			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.decreaseVisibleCounter()

			jest.runAllTimers()

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)
			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledWith(true)

			remoteVideoBlocker.setVideoEnabled(false)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)

			expect(remoteVideoBlocker.isVideoEnabled()).toBe(false)
		})

		test('does nothing if enabled when hidden', () => {
			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.decreaseVisibleCounter()

			jest.runAllTimers()

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)
			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledWith(true)

			remoteVideoBlocker.setVideoEnabled(false)
			remoteVideoBlocker.setVideoEnabled(true)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)

			expect(remoteVideoBlocker.isVideoEnabled()).toBe(true)
		})

		test('does nothing if hidden when disabled', () => {
			remoteVideoBlocker.setVideoEnabled(false)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)
			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledWith(true)

			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.decreaseVisibleCounter()

			jest.runAllTimers()

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)

			expect(remoteVideoBlocker.isVideoEnabled()).toBe(false)
		})

		test('does nothing if shown when disabled', () => {
			remoteVideoBlocker.setVideoEnabled(false)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)
			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledWith(true)

			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.decreaseVisibleCounter()
			remoteVideoBlocker.increaseVisibleCounter()

			jest.runAllTimers()

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)

			expect(remoteVideoBlocker.isVideoEnabled()).toBe(false)
		})

		test('immediately unblocks the video if enabled after showing', () => {
			remoteVideoBlocker.setVideoEnabled(false)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)
			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledWith(true)

			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.decreaseVisibleCounter()
			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.increaseVisibleCounter()

			jest.runAllTimers()

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)

			remoteVideoBlocker.setVideoEnabled(true)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(2)
			expect(callParticipantModel.setVideoBlocked).toHaveBeenNthCalledWith(2, false)

			expect(remoteVideoBlocker.isVideoEnabled()).toBe(true)
		})

		test('immediately unblocks the video if shown after enabled', () => {
			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.decreaseVisibleCounter()

			jest.runAllTimers()

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)
			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledWith(true)

			remoteVideoBlocker.setVideoEnabled(false)
			remoteVideoBlocker.setVideoEnabled(true)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)

			remoteVideoBlocker.increaseVisibleCounter()

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(2)
			expect(callParticipantModel.setVideoBlocked).toHaveBeenNthCalledWith(2, false)

			expect(remoteVideoBlocker.isVideoEnabled()).toBe(true)
		})
	})

	describe('destroy', () => {
		test('prevents the video from being blocked by default if not shown in some seconds', () => {
			jest.advanceTimersByTime(4000)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(0)

			remoteVideoBlocker.destroy()

			jest.advanceTimersByTime(1000)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(0)
		})

		test('prevents the video from being blocked or unblocked if enabled or disabled', () => {
			remoteVideoBlocker.increaseVisibleCounter()

			remoteVideoBlocker.destroy()

			remoteVideoBlocker.setVideoEnabled(false)
			remoteVideoBlocker.setVideoEnabled(true)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(0)
		})

		test('prevents the video from being blocked after some seconds if hidden before destroying', () => {
			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.decreaseVisibleCounter()

			jest.advanceTimersByTime(4000)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(0)

			remoteVideoBlocker.destroy()

			jest.advanceTimersByTime(1000)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(0)
		})

		test('prevents the video from being blocked after some seconds if hidden after destroying', () => {
			remoteVideoBlocker.destroy()

			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.decreaseVisibleCounter()

			jest.advanceTimersByTime(4000)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(0)
		})
	})
})
