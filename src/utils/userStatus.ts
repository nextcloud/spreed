import type {
	Conversation,
	Participant,
	ParticipantSearchResult,
	ParticipantStatus,
} from '../types/index.ts'

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { t } from '@nextcloud/l10n'

/**
 * Generate user status object to use as preloaded status with NcAvatar
 *
 * @param userData user data (from conversation, participant, search result)
 */
export function getPreloadedUserStatus(userData?: Conversation | Participant | ParticipantSearchResult): ParticipantStatus | undefined {
	if (!userData || typeof userData !== 'object') {
		return undefined
	}

	if ('statusMessage' in userData) {
		// We preloaded the status when via participants API
		return {
			status: userData.status || null,
			message: userData.statusMessage || null,
			icon: userData.statusIcon || null,
		}
	}
	if ('status' in userData) {
		// We preloaded the status when via search API
		if (typeof userData.status === 'object') {
			return {
				status: userData.status.status || null,
				message: userData.status.message || null,
				icon: userData.status.icon || null,
			}
		} else if (typeof userData.status === 'string' && userData.status === '') {
			// No status is set, provide empty status object to not make a request
			return {
				status: null,
				message: null,
				icon: null,
			}
		}
	}
	return undefined
}

/**
 * Generate full status message for user according to its status data
 *
 * @param userData user data
 */
export function getStatusMessage(userData?: Conversation | Participant | ParticipantSearchResult | ''): string {
	if (!userData) {
		return ''
	}

	const userStatus = getPreloadedUserStatus(userData)

	if (!userStatus) {
		return ''
	}

	let status = userStatus.icon ?? ''

	if (userStatus.message) {
		status += ' ' + userStatus.message
	} else if (userStatus.status === 'dnd') {
		status += ' ' + t('spreed', 'Do not disturb')
	} else if (userStatus.status === 'away') {
		status += ' ' + t('spreed', 'Away')
	}

	return status
}

/**
 * Check if current status is "Do not disturb"
 *
 * @param userData user data
 */
export function isDoNotDisturb(userData: Conversation | Participant | ParticipantSearchResult): boolean {
	return userData?.status === 'dnd'
}
