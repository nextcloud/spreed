/**
 * @copyright Copyright (c) 2023 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

/**
 * Start call recording
 *
 * @param {string} token conversation token
 * @param {number} callRecording the type of the recording being started (@see constants CALL.RECORDING.*)
 */
const startCallRecording = async (token, callRecording) => {
	await axios.post(generateOcsUrl('apps/spreed/api/v1/recording/{token}', { token }),
		{
			status: callRecording,
		})
}

/**
 * Stop call recording
 *
 * @param {string} token conversation token
 */
const stopCallRecording = async (token) => {
	await axios.delete(generateOcsUrl('apps/spreed/api/v1/recording/{token}', { token }))
}

export {
	startCallRecording,
	stopCallRecording,
}
