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
import { disableBotForConversation, enableBotForConversation, getConversationBots } from '../services/botsService.js'

export const useBotsStore = defineStore('bots', {
	state: () => ({
		bots: {},
	}),

	actions: {
		getConversationBots(token) {
			return this.bots[token] ? Object.values(this.bots[token]) : []
		},

		/**
		 * Fetch a list of available bots for conversation and save them to store
		 *
		 * @param {string} token The conversation token
		 * @return {Array} An array of bots ids
		 */
		async loadConversationBots(token) {
			if (!this.bots[token]) {
				Vue.set(this.bots, token, {})
			}

			const response = await getConversationBots(token)

			return response.data.ocs.data.map((bot) => {
				Vue.set(this.bots[token], bot.id, bot)
				return bot.id
			})
		},

		/**
		 * Enable or disable a bot for conversation
		 *
		 * @param {string} token The conversation token
		 * @param {object} bot The bot to toggle state
		 */
		async toggleBotState(token, bot) {
			const response = bot.state === BOT.STATE.ENABLED
				? await disableBotForConversation(token, bot.id)
				: await enableBotForConversation(token, bot.id)

			Vue.set(this.bots[token], bot.id, response.data.ocs.data)
		},

	},
})
