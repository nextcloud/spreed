/**
 * @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@icloud.com>
 *
 * @author Marco Ambrosini <marcoambrosini@icloud.com>
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

const userStatus = {
	methods: {
		getStatusMessage(userData) {
			let status = ''
			if (userData.statusIcon) {
				status = userData.statusIcon + ' '
			}

			if (userData.statusMessage) {
				status += userData.statusMessage
			} else if (userData.status === 'dnd') {
				status += t('spreed', 'Do not disturb')
			} else if (userData.status === 'away') {
				status += t('spreed', 'Away')
			}

			return status
		},

		isNotAvailable(userData) {
			if (!userData.status) {
				return false
			}

			return userData.status === 'dnd'
		},
	},
}

export default userStatus
