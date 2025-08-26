/*
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { vi } from 'vitest'
import RemoteVideoBlocker from './RemoteVideoBlocker.js'

describe('RemoteVideoBlocker', () => {
	let callParticipantModel
	let remoteVideoBlocker

	beforeEach(() => {
		vi.useFakeTimers()
		console.error = vi.fn()

		callParticipantModel = {
			setVideoBlocked: vi.fn(),
		}

		remoteVideoBlocker = new RemoteVideoBlocker(callParticipantModel)
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	test('blocks the video by default if not shown in some seconds', () => {
		vi.advanceTimersByTime(4000)

		expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(0)

		vi.advanceTimersByTime(1000)

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

			vi.runAllTimers()

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(0)
			expect(remoteVideoBlocker._visibleCounter).toBe(1)

			expect(remoteVideoBlocker.isVideoEnabled()).toBe(true)
		})

		test('does nothing if hidden without showing first', () => {
			vi.runAllTimers()

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)
			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledWith(true)

			remoteVideoBlocker.decreaseVisibleCounter()

			vi.runAllTimers()

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)
			expect(remoteVideoBlocker._visibleCounter).toBe(0)

			expect(remoteVideoBlocker.isVideoEnabled()).toBe(true)
		})

		test('blocks the video after some seconds when hidden', () => {
			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.decreaseVisibleCounter()

			vi.advanceTimersByTime(4000)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(0)

			vi.advanceTimersByTime(1000)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)
			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledWith(true)
			expect(remoteVideoBlocker._visibleCounter).toBe(0)

			expect(remoteVideoBlocker.isVideoEnabled()).toBe(true)
		})

		test('does nothing if shown again before blocking', () => {
			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.decreaseVisibleCounter()

			vi.advanceTimersByTime(4000)

			remoteVideoBlocker.increaseVisibleCounter()

			vi.runAllTimers()

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(0)
			expect(remoteVideoBlocker._visibleCounter).toBe(1)

			expect(remoteVideoBlocker.isVideoEnabled()).toBe(true)
		})

		test('immediately unblocks the video after showing', () => {
			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.decreaseVisibleCounter()

			vi.runAllTimers()

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

			vi.runAllTimers()

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

			vi.runAllTimers()

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)

			expect(remoteVideoBlocker.isVideoEnabled()).toBe(false)
		})

		test('immediately blocks the video if disabled before blocking after hidden', () => {
			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.decreaseVisibleCounter()

			vi.advanceTimersByTime(4000)

			remoteVideoBlocker.setVideoEnabled(false)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)
			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledWith(true)

			vi.runAllTimers()

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)

			expect(remoteVideoBlocker.isVideoEnabled()).toBe(false)
		})

		test('blocks the video after some seconds if hidden when enabled', () => {
			remoteVideoBlocker.setVideoEnabled(true)

			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.decreaseVisibleCounter()

			vi.advanceTimersByTime(4000)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(0)

			vi.advanceTimersByTime(1000)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)
			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledWith(true)

			expect(remoteVideoBlocker.isVideoEnabled()).toBe(true)
		})

		test('does nothing if disabled when hidden', () => {
			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.decreaseVisibleCounter()

			vi.runAllTimers()

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)
			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledWith(true)

			remoteVideoBlocker.setVideoEnabled(false)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)

			expect(remoteVideoBlocker.isVideoEnabled()).toBe(false)
		})

		test('does nothing if enabled when hidden', () => {
			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.decreaseVisibleCounter()

			vi.runAllTimers()

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

			vi.runAllTimers()

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

			vi.runAllTimers()

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

			vi.runAllTimers()

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(1)

			remoteVideoBlocker.setVideoEnabled(true)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(2)
			expect(callParticipantModel.setVideoBlocked).toHaveBeenNthCalledWith(2, false)

			expect(remoteVideoBlocker.isVideoEnabled()).toBe(true)
		})

		test('immediately unblocks the video if shown after enabled', () => {
			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.decreaseVisibleCounter()

			vi.runAllTimers()

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
			vi.advanceTimersByTime(4000)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(0)

			remoteVideoBlocker.destroy()

			vi.advanceTimersByTime(1000)

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

			vi.advanceTimersByTime(4000)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(0)

			remoteVideoBlocker.destroy()

			vi.advanceTimersByTime(1000)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(0)
		})

		test('prevents the video from being blocked after some seconds if hidden after destroying', () => {
			remoteVideoBlocker.destroy()

			remoteVideoBlocker.increaseVisibleCounter()
			remoteVideoBlocker.decreaseVisibleCounter()

			vi.advanceTimersByTime(4000)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(0)

			vi.advanceTimersByTime(1000)

			expect(callParticipantModel.setVideoBlocked).toHaveBeenCalledTimes(0)
		})
	})
})
