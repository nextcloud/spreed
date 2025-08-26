/*
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { vi } from 'vitest'
import { EventBus } from '../EventBus.ts'

describe('EventBus', () => {
	const customEvent1 = vi.fn()
	const customEvent2 = vi.fn()
	const customEvent3 = vi.fn()
	const customEventOnce1 = vi.fn()
	const customEventOnce2 = vi.fn()
	const customEventOnce3 = vi.fn()

	const testEventBus = (type, handlers, onceHandlers) => {
		expect(EventBus.all.get(type).length).toBe(handlers)
		if (!onceHandlers) {
			expect(EventBus._onceHandlers.get(type)).toBeUndefined()
		} else {
			expect(EventBus._onceHandlers.get(type).size).toBe(onceHandlers)
		}
	}

	afterEach(() => {
		EventBus.all.clear()
		vi.clearAllMocks()
	})

	describe('on and off', () => {
		it('should emit and listen to custom events', () => {
			// Arrange
			EventBus.on('custom-event', customEvent1)
			EventBus.on('custom-event', customEvent2)

			// Act
			EventBus.emit('custom-event')

			// Assert
			testEventBus('custom-event', 2)
			expect(customEvent1).toHaveBeenCalledTimes(1)
			expect(customEvent2).toHaveBeenCalledTimes(1)
		})

		it('should emit and listen to custom events with wildcard * ', () => {
			// Arrange
			EventBus.on('*', customEvent1)
			EventBus.on('*', customEvent2)

			// Act
			EventBus.emit('custom-event-1')

			// Assert
			testEventBus('*', 2)
			expect(customEvent1).toHaveBeenCalledTimes(1)
			expect(customEvent2).toHaveBeenCalledTimes(1)
		})

		it('should remove listeners by given type and handler', () => {
			// Arrange
			EventBus.on('custom-event', customEvent1)
			EventBus.on('custom-event', customEvent2)
			EventBus.emit('custom-event')
			testEventBus('custom-event', 2)

			// Act
			EventBus.off('custom-event', customEvent1)
			EventBus.emit('custom-event')

			// Assert
			testEventBus('custom-event', 1)
			expect(customEvent1).toHaveBeenCalledTimes(1)
			expect(customEvent2).toHaveBeenCalledTimes(2)
		})

		it('should remove listeners by wildcard *  and handler', () => {
			// Arrange
			EventBus.on('custom-event-1', customEvent1)
			EventBus.on('custom-event-2', customEvent2)
			EventBus.on('*', customEvent3)
			EventBus.emit('custom-event-1')
			EventBus.emit('custom-event-2')
			testEventBus('custom-event-1', 1)
			testEventBus('custom-event-2', 1)
			testEventBus('*', 1)
			expect(customEvent3).toHaveBeenCalledTimes(2)

			// Act
			EventBus.off('*', customEvent3)
			EventBus.emit('custom-event-1')
			EventBus.emit('custom-event-2')

			// Assert
			testEventBus('custom-event-1', 1)
			testEventBus('custom-event-2', 1)
			testEventBus('*', 0)
			expect(customEvent1).toHaveBeenCalledTimes(2)
			expect(customEvent2).toHaveBeenCalledTimes(2)
			expect(customEvent3).toHaveBeenCalledTimes(2)
		})

		it('should remove listeners by given type only', () => {
			// Arrange
			EventBus.on('custom-event', customEvent1)
			EventBus.on('custom-event', customEvent2)
			EventBus.emit('custom-event')
			testEventBus('custom-event', 2)

			// Act
			EventBus.off('custom-event')
			EventBus.emit('custom-event')

			// Assert
			testEventBus('custom-event', 0)
			expect(customEvent1).toHaveBeenCalledTimes(1)
			expect(customEvent2).toHaveBeenCalledTimes(1)
		})

		it('should remove listeners by wildcard * only', () => {
			// Arrange
			EventBus.on('custom-event-1', customEvent1)
			EventBus.on('custom-event-2', customEvent2)
			EventBus.on('*', customEvent3)
			EventBus.emit('custom-event-1')
			EventBus.emit('custom-event-2')
			testEventBus('custom-event-1', 1)
			testEventBus('custom-event-2', 1)
			testEventBus('*', 1)
			expect(customEvent3).toHaveBeenCalledTimes(2)

			// Act
			EventBus.off('*')
			EventBus.emit('custom-event-1')
			EventBus.emit('custom-event-2')

			// Assert
			testEventBus('custom-event-1', 1)
			testEventBus('custom-event-2', 1)
			testEventBus('*', 0)
			expect(customEvent1).toHaveBeenCalledTimes(2)
			expect(customEvent2).toHaveBeenCalledTimes(2)
			expect(customEvent3).toHaveBeenCalledTimes(2)
		})
	})

	describe('once and off', () => {
		it('should emit and listen to custom events', () => {
			// Arrange
			EventBus.on('custom-event', customEvent1)
			EventBus.once('custom-event', customEventOnce1)
			EventBus.once('custom-event', customEventOnce2)
			testEventBus('custom-event', 3, 2)

			// Act
			EventBus.emit('custom-event')
			EventBus.emit('custom-event')

			// Assert
			expect(customEvent1).toHaveBeenCalledTimes(2)
			expect(customEventOnce1).toHaveBeenCalledTimes(1)
			expect(customEventOnce2).toHaveBeenCalledTimes(1)
			testEventBus('custom-event', 1, 0)
		})

		it('should emit and listen to custom events with wildcard * ', () => {
			// Arrange
			EventBus.on('*', customEvent1)
			EventBus.once('*', customEventOnce1)
			EventBus.once('*', customEventOnce2)
			testEventBus('*', 3, 2)

			// Act
			EventBus.emit('custom-event-1')
			EventBus.emit('custom-event-2')

			// Assert
			expect(customEvent1).toHaveBeenCalledTimes(2)
			expect(customEventOnce1).toHaveBeenCalledTimes(1)
			expect(customEventOnce2).toHaveBeenCalledTimes(1)
			testEventBus('*', 1, 0)
		})

		it('should remove listeners by given type and handler', () => {
			// Arrange
			EventBus.on('custom-event', customEvent1)
			EventBus.once('custom-event', customEventOnce1)
			EventBus.once('custom-event', customEventOnce2)
			testEventBus('custom-event', 3, 2)

			// Act
			EventBus.off('custom-event', customEventOnce1)
			testEventBus('custom-event', 2, 1)
			EventBus.emit('custom-event')
			EventBus.emit('custom-event')

			// Assert
			expect(customEvent1).toHaveBeenCalledTimes(2)
			expect(customEventOnce1).toHaveBeenCalledTimes(0)
			expect(customEventOnce2).toHaveBeenCalledTimes(1)
			testEventBus('custom-event', 1, 0)
		})

		it('should remove listeners by wildcard * and handler', () => {
			// Arrange
			EventBus.once('custom-event-1', customEventOnce1)
			EventBus.on('custom-event-2', customEvent2)
			EventBus.once('custom-event-2', customEventOnce2)
			EventBus.on('*', customEvent3)
			EventBus.once('*', customEventOnce3)
			testEventBus('custom-event-1', 1, 1)
			testEventBus('custom-event-2', 2, 1)
			testEventBus('*', 2, 1)

			// Act
			EventBus.off('*', customEventOnce3)
			testEventBus('custom-event-1', 1, 1)
			testEventBus('custom-event-2', 2, 1)
			testEventBus('*', 1, 0)
			EventBus.emit('custom-event-1')
			EventBus.emit('custom-event-2')
			EventBus.emit('custom-event-3')

			// Assert
			expect(customEventOnce1).toHaveBeenCalledTimes(1)
			expect(customEvent2).toHaveBeenCalledTimes(1)
			expect(customEventOnce2).toHaveBeenCalledTimes(1)
			expect(customEvent3).toHaveBeenCalledTimes(3)
			expect(customEventOnce3).toHaveBeenCalledTimes(0)
			testEventBus('custom-event-1', 0, 0)
			testEventBus('custom-event-2', 1, 0)
			testEventBus('*', 1, 0)
		})

		it('should remove listeners by given type only', () => {
			// Arrange
			EventBus.on('custom-event', customEvent1)
			EventBus.once('custom-event', customEventOnce1)
			EventBus.once('custom-event', customEventOnce2)
			testEventBus('custom-event', 3, 2)

			// Act
			EventBus.off('custom-event')
			testEventBus('custom-event', 0, 0)
			EventBus.emit('custom-event')
			EventBus.emit('custom-event')

			// Assert
			expect(customEvent1).toHaveBeenCalledTimes(0)
			expect(customEventOnce1).toHaveBeenCalledTimes(0)
			expect(customEventOnce2).toHaveBeenCalledTimes(0)
		})

		it('should remove listeners by wildcard * only', () => {
			// Arrange
			EventBus.once('custom-event-1', customEventOnce1)
			EventBus.on('custom-event-2', customEvent2)
			EventBus.once('custom-event-2', customEventOnce2)
			EventBus.on('*', customEvent3)
			EventBus.once('*', customEventOnce3)
			testEventBus('custom-event-1', 1, 1)
			testEventBus('custom-event-2', 2, 1)
			testEventBus('*', 2, 1)

			// Act
			EventBus.off('*')
			testEventBus('custom-event-1', 1, 1)
			testEventBus('custom-event-2', 2, 1)
			testEventBus('*', 0, 0)
			EventBus.emit('custom-event-1')
			EventBus.emit('custom-event-2')
			EventBus.emit('custom-event-3')

			// Assert
			expect(customEventOnce1).toHaveBeenCalledTimes(1)
			expect(customEvent2).toHaveBeenCalledTimes(1)
			expect(customEventOnce2).toHaveBeenCalledTimes(1)
			expect(customEvent3).toHaveBeenCalledTimes(0)
			expect(customEventOnce3).toHaveBeenCalledTimes(0)
			testEventBus('custom-event-1', 0, 0)
			testEventBus('custom-event-2', 1, 0)
			testEventBus('*', 0, 0)
		})
	})
})
