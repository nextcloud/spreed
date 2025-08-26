/*
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { vi } from 'vitest'
import {
	convertToUnix,
	formattedTime,
	futureRelativeTime,
} from '../formattedTime.ts'

const TIME = (61 * 60 + 5) * 1000 // 1 hour, 1 minute, 5 seconds in ms

describe('convertToUnix', () => {
	it('should return the correct UNIX timestamp for given time in ms', () => {
		expect(convertToUnix(1704067200269)).toBe(1704067200)
	})
	it('should return the correct UNIX timestamp for given Date object', () => {
		expect(convertToUnix(new Date('2024-01-01T00:00:00Z'))).toBe(1704067200)
	})
})

describe('formattedTime', () => {
	it('should return the formatted time with optional spacing and padded minutes / seconds', () => {
		const result = formattedTime(TIME)
		expect(result).toBe('1 : 01 : 05')
		const resultCondensed = formattedTime(TIME, true)
		expect(resultCondensed).toBe('1:01:05')
	})

	it('should return fallback string when time value is falsy', () => {
		const result = formattedTime(0)
		expect(result).toBe('-- : --')
		const resultCondensed = formattedTime(0, true)
		expect(resultCondensed).toBe('--:--')
	})
})

describe('futureRelativeTime', () => {
	beforeEach(() => {
		vi.useFakeTimers().setSystemTime(new Date('2024-01-01T00:00:00Z'))
	})

	afterEach(() => {
		vi.useRealTimers()
	})

	it('should return the correct string for time in hours', () => {
		const timeInFuture = Date.now() + (2 * 60 * 60 * 1000) // 2 hours from now
		const result = futureRelativeTime(timeInFuture)
		expect(result).toBe('In 2 hours')
	})

	it('should return the correct string for time in minutes', () => {
		const timeInFuture = Date.now() + (30 * 60 * 1000) // 30 minutes from now
		const result = futureRelativeTime(timeInFuture)
		expect(result).toBe('In 30 minutes')
	})

	it('should return the correct string for time in hours and minutes', () => {
		const timeInFuture = Date.now() + (2 * 60 * 60 * 1000) + (15 * 60 * 1000) // 2 hours and 15 minutes from now
		const result = futureRelativeTime(timeInFuture)
		expect(result).toBe('In 2 hours and 15 minutes')
	})

	it('should return the correct string for 1 hour and minutes', () => {
		const timeInFuture = Date.now() + (60 * 60 * 1000) + (15 * 60 * 1000) // 1 hour and 15 minutes from now
		const result = futureRelativeTime(timeInFuture)
		expect(result).toBe('In 1 hour and 15 minutes')
	})
})
