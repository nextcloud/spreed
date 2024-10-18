/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { satisfyVersion } from '../satisfyVersion.ts'

describe('satisfyVersion', () => {
	const testCases = [
		// Before required
		['29.1.4.3', '28.2.5.4', true],
		['29.1.4.3', '29.0.15.3', true],
		['29.1.4.3', '29.1.1.3', true],
		['29.1.4.3', '29.1.4.0', true],
		['29.1.4.3', '29.1.4', true],
		['29.1.4.3', '29.1', true],
		['29.1.4.3', '29', true],
		['29.1.4', '29.1.3.9', true],
		['29.1', '29.0.9.1', true],
		['29', '28.1.5.6', true],
		// Exact match
		['29.1.4.3', '29.1.4.3', true],
		['29', '29.0.0.0', true],
		// After required
		['29.1.4.3', '29.1.4.4', false],
		['29.1.4.3', '29.1.5.0', false],
		['29.1.4.3', '29.2.0.0', false],
		['29.1.4.3', '30.0.0.0', false],
		['29.1.4.3', '29.1.5', false],
		['29.1.4.3', '29.2', false],
		['29.1.4.3', '30', false],
		['29.1.4', '29.1.4.3', false],
		['29.1', '29.1.4.3', false],
		['29', '29.1.4.3', false],
	]

	it.each(testCases)('check if %s should satisfy requirement %s returns %s', (a, b, c) => {
		expect(satisfyVersion(a, b)).toBe(c)
	})
})
