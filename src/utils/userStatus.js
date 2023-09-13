/**
 * @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@icloud.com>
 * @copyright Copyright (c) 2023 Maksim Sukharev <antreesy.web@gmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

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
