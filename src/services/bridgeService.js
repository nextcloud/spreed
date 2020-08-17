/**
 * @copyright Copyright (c) 2020 Julien Veyssier <eneiluj@posteo.net>
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 *
 * @license GNU AGPL version 3 or any later version
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
import {
	generateOcsUrl,
} from '@nextcloud/router'

/**
 * Edit the bridge of a room
 * @param {token} token the conversation token.
 * @param {string} enabled state of the bridge
 * @param {string} parts parts of the bridge, where it has to connect
 */
const editBridge = async function(token, enabled, parts) {
	const response = await axios.put(generateOcsUrl('apps/spreed/api/v1', 2) + `room/${token}/bridge`, {
		token,
		enabled,
		parts,
	})
	return response
}

/**
 * Get the bridge of a room
 * @param {token} token the conversation token.
 */
const getBridge = async function(token) {
	const response = await axios.get(generateOcsUrl('apps/spreed/api/v1', 2) + `room/${token}/bridge`)
	return response
}

export {
	editBridge,
	getBridge,
}
