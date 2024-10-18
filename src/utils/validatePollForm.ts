/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { createPollParams } from '../types/index.ts'

type requiredPollParams = Omit<createPollParams, 'draft'>
const pollFormExample = {
	question: '',
	options: ['', ''],
	resultMode: 0,
	maxVotes: 0,
}
const REQUIRED_KEYS: Array<keyof requiredPollParams> = Object.keys(pollFormExample) as Array<keyof requiredPollParams>

/**
 * Parses a given JSON object and validates with required poll form object.
 * Throws an error if parsed object doesn't match
 * @param jsonObject The object to validate
 */
function validatePollForm(jsonObject: requiredPollParams): requiredPollParams {
	if (typeof jsonObject !== 'object') {
		throw new Error('Invalid parsed object')
	}

	for (const key of REQUIRED_KEYS) {
		if (jsonObject[key] === undefined) {
			throw new Error('Missing required key')
		}

		if (typeof pollFormExample[key] !== typeof jsonObject[key]) {
			throw new Error('Invalid parsed value')
		}

		if (key === 'options' && jsonObject[key]?.some((opt: unknown) => typeof opt !== 'string')) {
			throw new Error('Invalid parsed option values')
		}
	}

	return jsonObject
}

export {
	validatePollForm,
}
