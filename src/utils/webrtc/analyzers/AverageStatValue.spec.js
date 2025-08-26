/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { beforeEach, describe, expect, test } from 'vitest'
import { AverageStatValue, STAT_VALUE_TYPE } from './AverageStatValue.js'

describe('AverageStatValue', () => {
	beforeEach(() => {
	})

	test('returns the last raw value', () => {
		const testValues = [100, 200, 150, 123, 30, 50, 22, 33]
		const stat = new AverageStatValue(3, STAT_VALUE_TYPE.CUMULATIVE, 3)
		const stat2 = new AverageStatValue(3, STAT_VALUE_TYPE.RELATIVE, 3)

		expect(stat.getLastRawValue()).toBe(NaN)
		expect(stat2.getLastRawValue()).toBe(NaN)

		testValues.forEach((val) => {
			stat.add(val)
			expect(stat.getLastRawValue()).toBe(val)

			stat2.add(val)
			expect(stat2.getLastRawValue()).toBe(val)
		})
	})

	describe('returns whether there are enough values for a meaningful calculation', () => {
		test('after creating', () => {
			const testValues = [100, 200, 150, 123, 30, 50, 22, 33]
			const stat = new AverageStatValue(3, STAT_VALUE_TYPE.CUMULATIVE, 3)
			const stat2 = new AverageStatValue(3, STAT_VALUE_TYPE.RELATIVE, 3)

			testValues.forEach((val, index) => {
				stat.add(val)
				expect(stat.hasEnoughData()).toBe(index >= 3)

				stat2.add(val)
				expect(stat2.hasEnoughData()).toBe(index >= 2)
			})
		})

		describe('resetting', () => {
			let stat
			let stat2

			const addValues = (values) => {
				values.forEach((val) => {
					stat.add(val)
					stat2.add(val)
				})
			}

			beforeEach(() => {
				stat = new AverageStatValue(3, STAT_VALUE_TYPE.CUMULATIVE, 3)
				stat2 = new AverageStatValue(3, STAT_VALUE_TYPE.RELATIVE, 3)
			})

			test('before having enough values', () => {
				addValues([100, 200])

				expect(stat.hasEnoughData()).toBe(false)
				expect(stat2.hasEnoughData()).toBe(false)

				stat.reset()
				stat2.reset()

				expect(stat.hasEnoughData()).toBe(false)
				expect(stat2.hasEnoughData()).toBe(false)

				addValues([150, 123])

				expect(stat.hasEnoughData()).toBe(false)
				expect(stat2.hasEnoughData()).toBe(false)

				addValues([30])

				expect(stat.hasEnoughData()).toBe(false)
				expect(stat2.hasEnoughData()).toBe(true)

				addValues([50])

				expect(stat.hasEnoughData()).toBe(true)
				expect(stat2.hasEnoughData()).toBe(true)
			})

			test('after having enough values', () => {
				addValues([100, 200, 150, 123])

				expect(stat.hasEnoughData()).toBe(true)
				expect(stat2.hasEnoughData()).toBe(true)

				stat.reset()
				stat2.reset()

				expect(stat.hasEnoughData()).toBe(false)
				expect(stat2.hasEnoughData()).toBe(false)

				addValues([30, 50])

				expect(stat.hasEnoughData()).toBe(false)
				expect(stat2.hasEnoughData()).toBe(false)

				addValues([22])

				expect(stat.hasEnoughData()).toBe(false)
				expect(stat2.hasEnoughData()).toBe(true)

				addValues([33])

				expect(stat.hasEnoughData()).toBe(true)
				expect(stat2.hasEnoughData()).toBe(true)
			})
		})
	})

	describe('to string', () => {
		test('no values', () => {
			const stat = new AverageStatValue(3, STAT_VALUE_TYPE.CUMULATIVE, 3)
			const stat2 = new AverageStatValue(3, STAT_VALUE_TYPE.RELATIVE, 3)

			expect(stat.toString()).toBe('[]')
			expect(stat2.toString()).toBe('[]')
		})

		test('single value', () => {
			const stat = new AverageStatValue(3, STAT_VALUE_TYPE.CUMULATIVE, 3)
			const stat2 = new AverageStatValue(3, STAT_VALUE_TYPE.RELATIVE, 3)

			stat.add(42)
			stat2.add(42)

			// The first cumulative value is treated as 0 as it is the base from
			// which the rest of the values will be calculated.
			expect(stat.toString()).toBe('[0]')
			expect(stat2.toString()).toBe('[42]')
		})

		test('several values', () => {
			const testValues = [100, 200, 150, 123, 30, 50, 22, 33]
			const stat = new AverageStatValue(3, STAT_VALUE_TYPE.CUMULATIVE, 3)
			const stat2 = new AverageStatValue(3, STAT_VALUE_TYPE.RELATIVE, 3)

			testValues.forEach((val, index) => {
				stat.add(val)
				stat2.add(val)
			})

			expect(stat.toString()).toBe('[20, -28, 11]')
			expect(stat2.toString()).toBe('[50, 22, 33]')
		})
	})

	describe('cumulative average', () => {
		test('returns the last relative value', () => {
			const testValues = [
				[100, 0],
				[130, 30],
				[160, 30],
				[180, 20],
				[185, 5],
				[100, -85],
				[90, -10],
				[90, 0],
				[90, 0],
				[90, 0],
				[90, 0],
				[100, 10],
				[200, 100],
			]
			const stat = new AverageStatValue(3, STAT_VALUE_TYPE.CUMULATIVE, 3)

			testValues.forEach((tuple) => {
				stat.add(tuple[0])
				expect(stat.getLastRelativeValue()).toBe(tuple[1])
			})
		})

		test('calculates rolling cumulative average', () => {
			// first value is input, second is expected weighted average at the time
			const testValues = [
				[100, 0],
				[130, 15],
				[160, 20],
				[180, 26.666],
				[185, 18.333],
				[100, -20],
				[90, -30],
				[90, -31.666],
				[90, -3.333],
				[90, 0],
				[90, 0],
				[100, 3.333],
				[200, 36.666],
			]
			const stat = new AverageStatValue(3, STAT_VALUE_TYPE.CUMULATIVE, 1)

			testValues.forEach((tuple, index) => {
				stat.add(tuple[0])
				expect(stat.getWeightedAverage()).toBeCloseTo(tuple[1])
			})
		})

		test('calculates rolling cumulative average with last weight', () => {
			// first value is input, second is expected weighted average at the time
			const testValues = [
				[100, 0],
				[130, 20],
				[160, 25],
				[180, 25],
				[185, 14.166],
				[100, -37.5],
				[90, -32.5],
				[90, -17.5],
				[90, -1.666],
				[90, 0],
				[90, 0],
				[100, 5],
				[200, 53.333],
			]
			const stat = new AverageStatValue(3, STAT_VALUE_TYPE.CUMULATIVE, 3)

			testValues.forEach((tuple, index) => {
				stat.add(tuple[0])
				expect(stat.getWeightedAverage()).toBeCloseTo(tuple[1])
			})
		})

		test('calculates rolling cumulative average with last weight and more values', () => {
			// first value is input, second is expected weighted average at the time
			const testValues = [
				[100, 0],
				[130, 18.75],
				[160, 23.999],
				[180, 22.499],
				[185, 17.708],
				[100, -22.5],
				[90, -24.999],
				[90, -20],
				[90, -12.708],
				[90, -1.25],
				[90, 0],
				[100, 3.75],
				[200, 40.416],
			]
			const stat = new AverageStatValue(4, STAT_VALUE_TYPE.CUMULATIVE, 3)

			testValues.forEach((tuple, index) => {
				stat.add(tuple[0])
				expect(stat.getWeightedAverage()).toBeCloseTo(tuple[1])
			})
		})
	})

	describe('relative average', () => {
		test('returns the last relative value', () => {
			const testValues = [
				[100, 100],
				[130, 130],
				[160, 160],
				[180, 180],
				[185, 185],
				[100, 100],
				[90, 90],
				[90, 90],
				[90, 90],
				[90, 90],
				[90, 90],
				[100, 100],
				[200, 200],
			]
			const stat = new AverageStatValue(3, STAT_VALUE_TYPE.RELATIVE, 3)

			testValues.forEach((tuple) => {
				stat.add(tuple[0])
				expect(stat.getLastRelativeValue()).toBe(tuple[1])
			})
		})
		test('calculates rolling relative average', () => {
			// first value is input, second is expected weighted average at the time
			const testValues = [
				[100, 100],
				[130, 115],
				[160, 130],
				[180, 156.666],
				[185, 175],
				[100, 155],
				[90, 125],
				[90, 93.333],
				[90, 90],
				[90, 90],
				[90, 90],
				[100, 93.333],
				[200, 130],
			]
			const stat = new AverageStatValue(3, STAT_VALUE_TYPE.RELATIVE, 1)

			testValues.forEach((tuple, index) => {
				stat.add(tuple[0])
				expect(stat.getWeightedAverage()).toBeCloseTo(tuple[1])
			})
		})

		test('calculates rolling relative average with last weight', () => {
			// first value is input, second is expected weighted average at the time
			const testValues = [
				[100, 100],
				[130, 120],
				[160, 140],
				[180, 165],
				[185, 179.166],
				[100, 141.666],
				[90, 109.166],
				[90, 91.666],
				[90, 90],
				[90, 90],
				[90, 90],
				[100, 95],
				[200, 148.333],
			]
			const stat = new AverageStatValue(3, STAT_VALUE_TYPE.RELATIVE, 3)

			testValues.forEach((tuple, index) => {
				stat.add(tuple[0])
				expect(stat.getWeightedAverage()).toBeCloseTo(tuple[1])
			})
		})

		test('calculates rolling relative average with last weight and more values', () => {
			// first value is input, second is expected weighted average at the time
			const testValues = [
				[100, 100],
				[130, 118.749],
				[160, 137.999],
				[180, 153.75],
				[185, 171.458],
				[100, 148.958],
				[90, 123.958],
				[90, 103.958],
				[90, 91.25],
				[90, 90],
				[90, 90],
				[100, 93.75],
				[200, 134.166],
			]
			const stat = new AverageStatValue(4, STAT_VALUE_TYPE.RELATIVE, 3)

			testValues.forEach((tuple, index) => {
				stat.add(tuple[0])
				expect(stat.getWeightedAverage()).toBeCloseTo(tuple[1])
			})
		})
	})
})
