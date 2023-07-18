/*
 * @copyright Copyright (c) 2023 Grigorii Shartsev <me@shgk.me>
 *
 * @author Grigorii Shartsev <me@shgk.me>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

import { isObject, patchObject } from '../objectUtils.js'

describe('objectUtils', () => {
	describe('isObject', () => {
		it('should return true for plain object', () => {
			expect(isObject({})).toBeTruthy()
		})

		it('should return false for null', () => {
			expect(isObject(null)).toBeFalsy()
		})

		it('should return false for Array', () => {
			expect(isObject([])).toBeFalsy()
		})
	})

	describe('patchObject', () => {
		it('should delete removed properties', () => {
			const target = {
				a: 1,
				b: 2,
				toRemove1: 3,
				toRemove2: 4,
			}
			const newObject = {
				a: 1,
				b: 2,
			}
			patchObject(target, newObject)
			expect(target).toEqual({ a: 1, b: 2 })
		})

		it('should add new properties', () => {
			const target = {
				a: 1,
				b: 2,
			}
			const newObject = {
				a: 1,
				b: 2,
				newKey1: 3,
				newKey2: 4,
			}
			patchObject(target, newObject)
			expect(target).toEqual({ a: 1, b: 2, newKey1: 3, newKey2: 4 })
		})

		it('should update existing primitive properties', () => {
			const target = {
				a: 1,
				b: 2,
			}
			const newObject = {
				a: 3,
				b: 4,
			}
			patchObject(target, newObject)
			expect(target).toEqual({ a: 3, b: 4 })
		})

		it('should update existing array properties as primitive properties', () => {
			const targetSubArray = [1, 2]
			const target = {
				a: targetSubArray,
			}
			const newObjectSubArray = [3, 4]
			const newObject = {
				a: newObjectSubArray,
			}
			patchObject(target, newObject)
			expect(target).toEqual({ a: [3, 4] })
			expect(target.a).toBe(newObjectSubArray) // Re-assigned, not mutated
		})

		it('should update existing object properties recursively', () => {
			const targetSubObject = {
				c: 2,
				d: 3,
			}
			const target = {
				a: 1,
				b: targetSubObject,
			}
			const newObject = {
				a: 1,
				b: {
					c: 4,
					d: 5,
				},
			}
			patchObject(target, newObject)
			expect(target).toEqual({ a: 1, b: { c: 4, d: 5 } })
			expect(target.b).toBe(targetSubObject) // Mutated, not re-assigned
		})
	})
})
