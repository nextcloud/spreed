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
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

const configureBreakoutRooms = async function(token, mode, amount, attendeeMap) {
	return await axios.post(generateOcsUrl('/apps/spreed/api/v1/breakout-rooms/{token}', { token }), {
		mode,
		amount,
		attendeeMap,
	})
}

const deleteBreakoutRooms = async function(token) {
	return await axios.delete(generateOcsUrl('/apps/spreed/api/v1/breakout-rooms/{token}', { token }))
}

const getBreakoutRooms = async function(token) {
	return await axios.get(generateOcsUrl('/apps/spreed/api/v4/room/{token}/breakout-rooms', { token }))
}

export {
	configureBreakoutRooms,
	deleteBreakoutRooms,
	getBreakoutRooms,
}
