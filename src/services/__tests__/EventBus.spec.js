/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { EventBus } from '../EventBus.ts'

describe('EventBus', () => {
	const customEvent1 = jest.fn()
	const customEvent2 = jest.fn()
	const customEvent3 = jest.fn()
	const customEventOnce1 = jest.fn()
	const customEventOnce2 = jest.fn()
	const customEventOnce3 = jest.fn()

	afterEach(() => {
		EventBus.all.clear()
		jest.clearAllMocks()
	})

	describe('on and off', () => {
		it('should emit and listen to custom events', () => {
			// Arrange
			EventBus.on('custom-event', customEvent1)
			EventBus.on('custom-event', customEvent2)

			// Act
			EventBus.emit('custom-event')

			// Assert
			expect(EventBus.all.get('custom-event')).toHaveLength(2)
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
			expect(EventBus.all.get('*')).toHaveLength(2)
			expect(customEvent1).toHaveBeenCalledTimes(1)
			expect(customEvent2).toHaveBeenCalledTimes(1)
		})

		it('should remove listeners by given type and handler', () => {
			// Arrange
			EventBus.on('custom-event', customEvent1)
			EventBus.on('custom-event', customEvent2)
			EventBus.emit('custom-event')
			expect(EventBus.all.get('custom-event')).toHaveLength(2)

			// Act
			EventBus.off('custom-event', customEvent1)
			EventBus.emit('custom-event')

			// Assert
			expect(EventBus.all.get('custom-event')).toHaveLength(1)
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
			expect(EventBus.all.get('custom-event-1')).toHaveLength(1)
			expect(EventBus.all.get('custom-event-2')).toHaveLength(1)
			expect(EventBus.all.get('*')).toHaveLength(1)
			expect(customEvent3).toHaveBeenCalledTimes(2)

			// Act
			EventBus.off('*', customEvent3)
			EventBus.emit('custom-event-1')
			EventBus.emit('custom-event-2')

			// Assert
			expect(EventBus.all.get('custom-event-1')).toHaveLength(1)
			expect(EventBus.all.get('custom-event-2')).toHaveLength(1)
			expect(EventBus.all.get('*')).toHaveLength(0)
			expect(customEvent1).toHaveBeenCalledTimes(2)
			expect(customEvent2).toHaveBeenCalledTimes(2)
			expect(customEvent3).toHaveBeenCalledTimes(2)
		})

		it('should remove listeners by given type only', () => {
			// Arrange
			EventBus.on('custom-event', customEvent1)
			EventBus.on('custom-event', customEvent2)
			EventBus.emit('custom-event')
			expect(EventBus.all.get('custom-event')).toHaveLength(2)

			// Act
			EventBus.off('custom-event')
			EventBus.emit('custom-event')

			// Assert
			expect(EventBus.all.get('custom-event')).toHaveLength(0)
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
			expect(EventBus.all.get('custom-event-1')).toHaveLength(1)
			expect(EventBus.all.get('custom-event-2')).toHaveLength(1)
			expect(EventBus.all.get('*')).toHaveLength(1)
			expect(customEvent3).toHaveBeenCalledTimes(2)

			// Act
			EventBus.off('*')
			EventBus.emit('custom-event-1')
			EventBus.emit('custom-event-2')

			// Assert
			expect(EventBus.all.get('custom-event-1')).toHaveLength(1)
			expect(EventBus.all.get('custom-event-2')).toHaveLength(1)
			expect(EventBus.all.get('*')).toHaveLength(0)
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
			expect(EventBus.all.get('custom-event')).toHaveLength(3)

			// Act
			EventBus.emit('custom-event')
			EventBus.emit('custom-event')

			// Assert
			expect(customEvent1).toHaveBeenCalledTimes(2)
			expect(customEventOnce1).toHaveBeenCalledTimes(1)
			expect(customEventOnce2).toHaveBeenCalledTimes(1)
			expect(EventBus.all.get('custom-event')).toHaveLength(1)
		})

		it('should emit and listen to custom events with wildcard * ', () => {
			// Arrange
			EventBus.on('*', customEvent1)
			EventBus.once('*', customEventOnce1)
			EventBus.once('*', customEventOnce2)
			expect(EventBus.all.get('*')).toHaveLength(3)

			// Act
			EventBus.emit('custom-event-1')
			EventBus.emit('custom-event-2')

			// Assert
			expect(customEvent1).toHaveBeenCalledTimes(2)
			expect(customEventOnce1).toHaveBeenCalledTimes(1)
			expect(customEventOnce2).toHaveBeenCalledTimes(1)
			expect(EventBus.all.get('*')).toHaveLength(1)
		})

		it('should remove listeners by given type and handler', () => {
			// Arrange
			EventBus.on('custom-event', customEvent1)
			EventBus.once('custom-event', customEventOnce1)
			EventBus.once('custom-event', customEventOnce2)
			expect(EventBus.all.get('custom-event')).toHaveLength(3)

			// Act
			EventBus.off('custom-event', customEventOnce1)
			expect(EventBus.all.get('custom-event')).toHaveLength(2)
			EventBus.emit('custom-event')
			EventBus.emit('custom-event')

			// Assert
			expect(customEvent1).toHaveBeenCalledTimes(2)
			expect(customEventOnce1).toHaveBeenCalledTimes(0)
			expect(customEventOnce2).toHaveBeenCalledTimes(1)
			expect(EventBus.all.get('custom-event')).toHaveLength(1)
		})

		it('should remove listeners by wildcard * and handler', () => {
			// Arrange
			EventBus.once('custom-event-1', customEventOnce1)
			EventBus.on('custom-event-2', customEvent2)
			EventBus.once('custom-event-2', customEventOnce2)
			EventBus.on('*', customEvent3)
			EventBus.once('*', customEventOnce3)
			expect(EventBus.all.get('custom-event-1')).toHaveLength(1)
			expect(EventBus.all.get('custom-event-2')).toHaveLength(2)
			expect(EventBus.all.get('*')).toHaveLength(2)

			// Act
			EventBus.off('*', customEventOnce3)
			expect(EventBus.all.get('custom-event-1')).toHaveLength(1)
			expect(EventBus.all.get('custom-event-2')).toHaveLength(2)
			expect(EventBus.all.get('*')).toHaveLength(1)
			EventBus.emit('custom-event-1')
			EventBus.emit('custom-event-2')
			EventBus.emit('custom-event-3')

			// Assert
			expect(customEventOnce1).toHaveBeenCalledTimes(1)
			expect(customEvent2).toHaveBeenCalledTimes(1)
			expect(customEventOnce2).toHaveBeenCalledTimes(1)
			expect(customEvent3).toHaveBeenCalledTimes(3)
			expect(customEventOnce3).toHaveBeenCalledTimes(0)
			expect(EventBus.all.get('custom-event-1')).toHaveLength(0)
			expect(EventBus.all.get('custom-event-2')).toHaveLength(1)
			expect(EventBus.all.get('*')).toHaveLength(1)
		})

		it('should remove listeners by given type only', () => {
			// Arrange
			EventBus.on('custom-event', customEvent1)
			EventBus.once('custom-event', customEventOnce1)
			EventBus.once('custom-event', customEventOnce2)
			expect(EventBus.all.get('custom-event')).toHaveLength(3)

			// Act
			EventBus.off('custom-event')
			expect(EventBus.all.get('custom-event')).toHaveLength(0)
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
			expect(EventBus.all.get('custom-event-1')).toHaveLength(1)
			expect(EventBus.all.get('custom-event-2')).toHaveLength(2)
			expect(EventBus.all.get('*')).toHaveLength(2)

			// Act
			EventBus.off('*')
			expect(EventBus.all.get('custom-event-1')).toHaveLength(1)
			expect(EventBus.all.get('custom-event-2')).toHaveLength(2)
			expect(EventBus.all.get('*')).toHaveLength(0)
			EventBus.emit('custom-event-1')
			EventBus.emit('custom-event-2')
			EventBus.emit('custom-event-3')

			// Assert
			expect(customEventOnce1).toHaveBeenCalledTimes(1)
			expect(customEvent2).toHaveBeenCalledTimes(1)
			expect(customEventOnce2).toHaveBeenCalledTimes(1)
			expect(customEvent3).toHaveBeenCalledTimes(0)
			expect(customEventOnce3).toHaveBeenCalledTimes(0)
			expect(EventBus.all.get('custom-event-1')).toHaveLength(0)
			expect(EventBus.all.get('custom-event-2')).toHaveLength(1)
			expect(EventBus.all.get('*')).toHaveLength(0)
		})
	})
})
