/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { validatePollForm } from '../validatePollForm.ts'

const form = {
	question: 'Lunch?',
	options: ['Pizza', 'Pasta'],
	resultMode: 0,
	maxVotes: 1,
}

describe('validatePollForm', () => {
	it.each(Array.from({ length: 16 }, (_, mode) => mode))('preserves resultMode %i, including legacy values', (resultMode) => {
		expect(validatePollForm({ ...form, resultMode })).toEqual({ ...form, resultMode })
	})

	it.each([-1, 16, 1.5, NaN, Infinity, '0', null, {}, []])('rejects invalid resultMode %j', (resultMode) => {
		expect(() => validatePollForm({ ...form, resultMode })).toThrow('Invalid parsed value: resultMode')
	})
})
