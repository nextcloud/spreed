/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
