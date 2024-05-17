/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { t } from '@nextcloud/l10n'
/**
 * Generate full status message for user according to its status data
 *
 * @param {object} userData user data
 * @param {string} [userData.status] status of user
 * @param {string} [userData.statusIcon] status icon of user
 * @param {string} [userData.statusMessage] status message of user
 * @return {string}
 */
export function getStatusMessage(userData) {
	let status = userData.statusIcon
		? userData.statusIcon + ' '
		: ''

	if (userData.statusMessage) {
		status += userData.statusMessage
	} else if (userData.status === 'dnd') {
		status += t('spreed', 'Do not disturb')
	} else if (userData.status === 'away') {
		status += t('spreed', 'Away')
	} else {
		status += ''
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
