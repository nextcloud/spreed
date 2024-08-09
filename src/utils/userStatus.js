/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { t } from '@nextcloud/l10n'
/**
 * Generate full status message for user according to its status data
 *
 * @param {object} userData current user data (conversation, participant, search result)
 * @param {string} [userData.status] status of user
 * @param {string|null} [userData.icon] status icon of user
 * @param {string|null} [userData.statusIcon] status icon of user
 * @param {string|null} [userData.message] status message of user
 * @param {string|null} [userData.statusMessage] status message of user
 * @param {number|null} [userData.clearAt] status clear timestamp of user
 * @param {number|null} [userData.statusClearAt] status clear timestamp of user
 * @return {string}
 */
export function getStatusMessage(userData) {
	if (!userData) {
		return ''
	}
	const userStatus = {
		status: userData.status,
		statusMessage: userData.statusMessage ?? userData.message,
		statusIcon: userData.statusIcon ?? userData.icon,
		statusClearAt: userData.statusClearAt ?? userData.clearAt,
	}

	let status = userStatus.statusIcon
		? userStatus.statusIcon + ' '
		: ''

	if (userStatus.statusMessage) {
		status += userStatus.statusMessage
	} else if (userStatus.status === 'dnd') {
		status += t('spreed', 'Do not disturb')
	} else if (userStatus.status === 'away') {
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
