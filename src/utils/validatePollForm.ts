/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { requiredPollParams } from '../types/index.ts'

const REQUIRED_KEYS = ['question', 'options', 'resultMode', 'maxVotes'] as const

/**
 * Type guard for options array
 * @param payload payload to check
 */
function isStringArray(payload: unknown): payload is string[] {
	return Array.isArray(payload) && payload.every((opt) => typeof opt === 'string')
}

/**
 * Parses a given JSON object and validates with required poll form object.
 * Throws an error if parsed object doesn't match
 * @param jsonObject The object to validate
 */
function validatePollForm(jsonObject: unknown): requiredPollParams {
	if (typeof jsonObject !== 'object' || !jsonObject) {
		throw new Error('Invalid parsed object')
	}

	const typedObject = jsonObject as Record<keyof requiredPollParams, unknown>

	for (const key of REQUIRED_KEYS) {
		if (typedObject[key] === undefined) {
			throw new Error('Missing required key')
		}
	}

	if (typeof typedObject.question !== 'string') {
		throw new Error('Invalid parsed value: question')
	}

	if (typeof typedObject.resultMode !== 'number' || !(typedObject.resultMode === 0 || typedObject.resultMode === 1)) {
		throw new Error('Invalid parsed value: resultMode')
	}

	if (typeof typedObject.maxVotes !== 'number') {
		throw new Error('Invalid parsed value: maxVotes')
	}

	if (!isStringArray(typedObject.options)) {
		throw new Error('Invalid parsed value: options')
	}

	return {
		question: typedObject.question,
		options: [...typedObject.options],
		resultMode: typedObject.resultMode,
		maxVotes: typedObject.maxVotes,
	}
}

export {
	validatePollForm,
}
