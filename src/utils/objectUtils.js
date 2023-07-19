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

import Vue from 'vue'

/**
 * Check if value is a plain object
 *
 * @param {any} value value to check
 * @return {boolean} true if value is a plain object
 */
export const isObject = (value) => value !== null && typeof value === 'object' && !Array.isArray(value)

/**
 * Apply mutations to object based on new object
 *
 * @param {object} target target object
 * @param {object} newObject new object to get changes from
 */
export function patchObject(target, newObject) {
	// Delete removed properties
	for (const key of Object.keys(target)) {
		if (newObject[key] === undefined) {
			Vue.delete(target, key)
		}
	}

	// Add new properties and update existing ones
	for (const [key, newValue] of Object.entries(newObject)) {
		const oldValue = target[key]

		if (oldValue === undefined) {
			// Add new property
			Vue.set(target, key, newValue)
		} else if (isObject(oldValue) && isObject(newValue)) {
			// This property is an object in both - update recursively
			patchObject(oldValue, newValue)
		} else {
			// Update the property
			Vue.set(target, key, newValue)
		}
	}
}
