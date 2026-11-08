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
	it('imports older drafts with live voters disabled', () => {
		expect(validatePollForm(form).showVotersBeforeClosing).toBe(false)
		expect(validatePollForm(form).showResultsBeforeVoting).toBe(false)
	})

	it.each([0, 1])('preserves result previews independently of name visibility in mode %i', (resultMode) => {
		expect(validatePollForm({ ...form, resultMode, showResultsBeforeVoting: true }).showResultsBeforeVoting).toBe(true)
	})

	it.each(['true', 1, null, {}, []])('rejects an invalid result preview setting: %j', (value) => {
		expect(() => validatePollForm({ ...form, showResultsBeforeVoting: value })).toThrow('Invalid parsed value: showResultsBeforeVoting')
	})

	it('preserves the live voter setting when importing a public poll', () => {
		expect(validatePollForm({ ...form, showVotersBeforeClosing: true }).showVotersBeforeClosing).toBe(true)
	})

	it('does not enable live voters for a hidden poll', () => {
		expect(validatePollForm({ ...form, resultMode: 1, showVotersBeforeClosing: true }).showVotersBeforeClosing).toBe(false)
	})

	it.each(['true', 1, null, {}, []])('rejects an invalid live voter setting: %j', (value) => {
		expect(() => validatePollForm({ ...form, showVotersBeforeClosing: value })).toThrow('Invalid parsed value: showVotersBeforeClosing')
	})
})
