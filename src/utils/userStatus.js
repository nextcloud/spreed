/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { t } from '@nextcloud/l10n'

/**
 * Generate user status object to use as preloaded status with NcAvatar
 *
 * @param {object} userData user data (from conversation, participant, search result)
 */
export function getPreloadedUserStatus(userData) {
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
	if ('status' in userData && typeof userData.status === 'object') {
		// We preloaded the status when via search API
		return {
			status: userData.status.status || null,
			message: userData.status.message || null,
			icon: userData.status.icon || null,
		}
	}
	return undefined
}

/**
 * Generate full status message for user according to its status data
 *
 * @param {object} userData user data
 * @return {string}
 */
export function getStatusMessage(userData) {
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
 * @param {object} userData user data
 * @param {string} [userData.status] status of user
 * @return {boolean}
 */
export function isDoNotDisturb(userData) {
	return userData?.status === 'dnd'
}
