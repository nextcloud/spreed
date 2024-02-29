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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { defineStore } from 'pinia'
import Vue from 'vue'

import { BOT } from '../constants.js'
import { disableBotForConversation, enableBotForConversation, getConversationBots } from '../services/botsService.ts'
import type { Bot } from '../types'

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
				Vue.set(this.bots, token, {})
			}

			const response = await getConversationBots(token)

			return response.data.ocs.data.map((bot: Bot) => {
				Vue.set(this.bots[token], bot.id, bot)
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

			Vue.set(this.bots[token], bot.id, response.data.ocs.data)
		},
	},
})
