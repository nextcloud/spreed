/**
 * @copyright Copyright (c) 2022 Marco Ambrosini <marcoambrosini@icloud.com>
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

/**
 * @copyright Copyright (c) 2022 Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @author Marco Ambrosini <marcoambrosini@pm.me>
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
import { configureBreakoutRooms, deleteBreakoutRooms } from '../services/breakoutRoomsService.js'
import { showError } from '@nextcloud/dialogs'

const actions = {
	async configureBreakoutRoomsAction(context, { token, mode, amount, attendeeMap }) {
		try {
			 await configureBreakoutRooms(token, mode, amount, attendeeMap)
		} catch (error) {
			console.error(error)
			showError(t('spreed', 'An error occurred while creating breakout rooms'))
		}
	},

	async deleteBreakoutRoomsAction(context, { token }) {
		try {
			await deleteBreakoutRooms(token)
		} catch (error) {
			console.error(error)
			showError(t('spreed', 'An error occurred while deleting breakout rooms'))
		}
	},
}

export default { actions }
