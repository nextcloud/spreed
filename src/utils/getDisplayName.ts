/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { t } from '@nextcloud/l10n'

import { ATTENDEE } from '../constants.js'

export const getDisplayNameWithFallback = function(displayName: string, source: string): string {
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
