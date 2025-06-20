/*
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Clone a plain JS object deeply via JSON serialization
 *
 * @param obj - Object to clone
 */
export function cloneDeepJson<T>(obj: T): T {
	return JSON.parse(JSON.stringify(obj)) as T
}

/**
 * Compare two plain JS objects deeply via JSON serialization
 *
 * @param a - First object
 * @param b - Second object
 */
export function isEqualJson<T>(a: T, b: T): boolean {
	return JSON.stringify(a) === JSON.stringify(b)
}
