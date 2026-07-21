/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, describe, expect, test, vi } from 'vitest'
import { randomUuid } from './randomUuid.ts'

// Canonical RFC 4122 version 4 UUID: version nibble is 4, variant nibble is 8/9/a/b
const UUID_V4_REGEX = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/

describe('randomUuid', () => {
	afterEach(() => {
		vi.restoreAllMocks()
	})

	test('returns a valid v4 UUID when crypto.randomUUID is available', () => {
		expect(typeof crypto.randomUUID).toBe('function')
		expect(randomUuid()).toMatch(UUID_V4_REGEX)
	})

	test('falls back to getRandomValues when crypto.randomUUID is undefined', () => {
		const originalRandomUUID = crypto.randomUUID
		try {
			// Simulate an insecure context (plain HTTP), where randomUUID is missing
			// @ts-expect-error - emulate the insecure-context runtime where the method is absent
			crypto.randomUUID = undefined

			const getRandomValuesSpy = vi.spyOn(crypto, 'getRandomValues')

			const uuid = randomUuid()

			expect(getRandomValuesSpy).toHaveBeenCalled()
			expect(uuid).toMatch(UUID_V4_REGEX)
		} finally {
			crypto.randomUUID = originalRandomUUID
		}
	})

	test('generates unique values on repeated calls', () => {
		const values = new Set(Array.from({ length: 100 }, () => randomUuid()))
		expect(values.size).toBe(100)
	})
})
