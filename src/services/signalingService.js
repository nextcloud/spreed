/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Fetches the signaling settings for a conversation
 * @param {string} token The token of the conversation to be signaled.
 */
const fetchSignalingSettings = async(token) => {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/signaling', 2) + 'settings', {
		params: {
			token,
		},
	})
}

const pullSignalingMessages = async(token, options) => {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/signaling', 2) + token, options)
}

const getWelcomeMessage = async(serverId) => {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/signaling', 2) + 'welcome/' + serverId)
}

export {
	fetchSignalingSettings,
	pullSignalingMessages,
	getWelcomeMessage,
}
