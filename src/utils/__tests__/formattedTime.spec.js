import { formattedTime } from '../formattedTime.ts'

const TIME = (61 * 60 + 5) * 1000 // 1 hour, 1 minute, 5 seconds in ms

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
