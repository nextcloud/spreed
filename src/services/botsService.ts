/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Bot, disableBotResponse, enableBotResponse, getBotsAdminResponse, getBotsResponse } from '../types/index.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Get information about available bots for this instance
 */
async function getAllBots(): getBotsAdminResponse {
	return axios.get(generateOcsUrl('/apps/spreed/api/v1/bot/admin'))
}

/**
 * Get information about available bots for this conversation
 *
 * @param token The conversation token
 */
async function getConversationBots(token: string): getBotsResponse {
	return axios.get(generateOcsUrl('/apps/spreed/api/v1/bot/{token}', { token }))
}

/**
 * Enable bot for conversation
 *
 * @param token The conversation token
 * @param id The bot id
 */
async function enableBotForConversation(token: string, id: Bot['id']): enableBotResponse {
	return axios.post(generateOcsUrl('/apps/spreed/api/v1/bot/{token}/{id}', { token, id }))
}

/**
 * Disable bot for conversation
 *
 * @param token The conversation token
 * @param id The bot id
 */
async function disableBotForConversation(token: string, id: Bot['id']): disableBotResponse {
	return axios.delete(generateOcsUrl('/apps/spreed/api/v1/bot/{token}/{id}', { token, id }))
}

/**
 * Submit an Adaptive Card response to the bot
 *
 * @param token The conversation token
 * @param cardId The adaptive card ID
 * @param values The collected input values from the card
 */
async function submitAdaptiveCardResponse(token: string, cardId: string, values: Record<string, unknown>) {
	return axios.post(
		generateOcsUrl('/apps/spreed/api/v1/adaptivecard/{token}/{cardId}/respond', { token, cardId }),
		{ values }
	)
}

export {
	disableBotForConversation,
	enableBotForConversation,
	getAllBots,
	getConversationBots,
	submitAdaptiveCardResponse,
}
