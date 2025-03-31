/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { getLanguage, t } from '@nextcloud/l10n'

import { ATTENDEE } from '../constants.ts'

/**
 * Returns display name with 'Guest' or 'Deleted user' fallback if not provided
 * @param displayName possible name of participant
 * @param source actor type of participant
 */
export function getDisplayNameWithFallback(displayName: string, source: string): string {
	if (displayName?.trim()) {
		return displayName.trim()
	}

	if ([ATTENDEE.ACTOR_TYPE.GUESTS, ATTENDEE.ACTOR_TYPE.EMAILS].includes(source)) {
		return t('spreed', 'Guest')
	}

	// Fallback to 'Deleted user':
	// - for matching type: `source === ATTENDEE.ACTOR_TYPE.DELETED_USERS`
	// - in other cases: should not happen, but can not be empty either
	return t('spreed', 'Deleted user')
}

/**
 * Returns concatenated display names with comma divider
 * @param {Array} displayNames list of display name
 * @param {number} [maxLength] max allowed length
 */
export function getDisplayNamesList(displayNames: string[], maxLength?: number): string {
	const sanitizedList = displayNames.map((name) => name.trim()).filter(Boolean)

	if (!sanitizedList.length) {
		return ''
	}

	const joinedDisplayNames = new Intl.ListFormat(getLanguage(), {
		style: 'narrow',
		type: 'conjunction',
	}).format(sanitizedList)

	if (maxLength && joinedDisplayNames.length > maxLength) {
		return joinedDisplayNames.substring(0, maxLength - 1) + '…'
	}
	return joinedDisplayNames
}
