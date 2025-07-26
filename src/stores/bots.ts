/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Bot } from '../types/index.ts'

import { defineStore } from 'pinia'
import { BOT } from '../constants.ts'
import { disableBotForConversation, enableBotForConversation, getConversationBots } from '../services/botsService.ts'

type State = {
	bots: Record<string, Record<number, Bot>>
}
export const useBotsStore = defineStore('bots', {
	state: (): State => ({
		bots: {},
	}),

	actions: {
		getConversationBots(token: string): Bot[] {
			return this.bots[token] ? Object.values(this.bots[token]) : []
		},

		/**
		 * Fetch a list of available bots for conversation and save them to store
		 *
		 * @param token The conversation token
		 */
		async loadConversationBots(token: string): Promise<Bot['id'][]> {
			if (!this.bots[token]) {
				this.bots[token] = {}
			}

			const response = await getConversationBots(token)

			return response.data.ocs.data.map((bot: Bot) => {
				this.bots[token][bot.id] = bot
				return bot.id
			})
		},

		/**
		 * Enable or disable a bot for conversation
		 *
		 * @param token The conversation token
		 * @param bot The bot to toggle state
		 */
		async toggleBotState(token: string, bot: Bot): Promise<void> {
			const response = bot.state === BOT.STATE.ENABLED
				? await disableBotForConversation(token, bot.id)
				: await enableBotForConversation(token, bot.id)

			this.bots[token][bot.id] = response.data.ocs.data!
		},
	},
})
