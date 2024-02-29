/**
 * @copyright Copyright (c) 2023 Maksim Sukharev <antreesy.web@gmail.com>
 *
 * @author Maksim Sukharev <antreesy.web@gmail.com>
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

import type { Bot, getBotsResponse, getBotsAdminResponse, enableBotResponse, disableBotResponse } from '../types'

/**
 * Get information about available bots for this instance
 */
const getAllBots = async function(): getBotsAdminResponse {
	return axios.get(generateOcsUrl('/apps/spreed/api/v1/bot/admin'))
}

/**
 * Get information about available bots for this conversation
 *
 * @param token The conversation token
 */
const getConversationBots = async function(token: string): getBotsResponse {
	return axios.get(generateOcsUrl('/apps/spreed/api/v1/bot/{token}', { token }))
}

/**
 * Enable bot for conversation
 *
 * @param token The conversation token
 * @param id The bot id
 */
const enableBotForConversation = async function(token: string, id: Bot['id']): enableBotResponse {
	return axios.post(generateOcsUrl('/apps/spreed/api/v1/bot/{token}/{id}', { token, id }))
}

/**
 * Disable bot for conversation
 *
 * @param token The conversation token
 * @param id The bot id
 */
const disableBotForConversation = async function(token: string, id: Bot['id']): disableBotResponse {
	return axios.delete(generateOcsUrl('/apps/spreed/api/v1/bot/{token}/{id}', { token, id }))
}

export {
	getAllBots,
	getConversationBots,
	enableBotForConversation,
	disableBotForConversation,
}
