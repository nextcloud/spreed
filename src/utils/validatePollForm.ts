/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { createPollParams } from '../types/index.ts'

const pollFormExample: createPollParams = {
	question: '',
	options: ['', ''],
	resultMode: 0,
	maxVotes: 0,
}
const REQUIRED_KEYS: Array<keyof createPollParams> = Object.keys(pollFormExample) as Array<keyof createPollParams>

/**
 * Parses a given JSON string and validates with required poll form object.
 * Throws an error if parsed object doesn't match
 * @param jsonString The string to evaluate
 */
function validatePollForm(jsonString: string): createPollParams {
	const parsedObject = JSON.parse(jsonString)

	if (REQUIRED_KEYS.some(key => parsedObject[key] === undefined)) {
		throw new Error('Missing required key')
	}

	if (REQUIRED_KEYS.some(key => typeof pollFormExample[key] !== typeof parsedObject[key])
		|| Object.values(parsedObject.options).some(opt => typeof opt !== 'string')) {
		throw new Error('Invalid parsed value')
	}

	return parsedObject
}

export {
	validatePollForm,
}
