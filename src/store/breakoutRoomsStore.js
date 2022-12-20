/**
 * @copyright Copyright (c) 2022 Marco Ambrosini <marcoambrosini@icloud.com>
 *
 * @author Marco Ambrosini <marcoambrosini@icloud.com>
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
import {
	configureBreakoutRooms,
	deleteBreakoutRooms,
	getBreakoutRooms,
	startBreakoutRooms,
	stopBreakoutRooms,
} from '../services/breakoutRoomsService.js'
import { showError } from '@nextcloud/dialogs'
import { set } from 'vue'

const state = {
	breakoutRoomsReferences: {},
}

const getters = {
	breakoutRoomsReferences: (state) => (token) => {
		return state[token]
	},
}

const mutations = {
	addBreakoutRoomsReferences(state, { token, breakoutRoomsReferences }) {
		if (!state[token]) {
			state[token] = []
		}
		set(state, token, breakoutRoomsReferences)
	},
}

const actions = {
	async configureBreakoutRoomsAction(context, { token, mode, amount, attendeeMap }) {
		try {
			 await configureBreakoutRooms(token, mode, amount, attendeeMap)
		} catch (error) {
			console.error(error)
			showError(t('spreed', 'An error occurred while creating breakout rooms'))
		}
	},

	async deleteBreakoutRoomsAction(context, { token }) {
		try {
			await deleteBreakoutRooms(token)
		} catch (error) {
			console.error(error)
			showError(t('spreed', 'An error occurred while deleting breakout rooms'))
		}
	},

	async getBreakoutRoomsAction(context, { token }) {
		try {
			const response = await getBreakoutRooms(token)
			context.commit('addBreakoutRoomsReferences', {
				token,
				breakoutRoomsReferences: response.data.ocs.data.map(conversation => conversation.token),
			})
			console.debug('response', response)
		} catch (error) {
			console.error(error)
		}
	},

	async startBreakoutRoomsAction(context, token) {
		try {
			await startBreakoutRooms(token)
		} catch (error) {
			console.error(error)
			showError(t('spreed', 'An error occurred while starting breakout rooms'))
		}
	},

	async stopBreakoutRoomsAction(context, token) {
		try {
			await stopBreakoutRooms(token)
		} catch (error) {
			console.error(error)
			showError(t('spreed', 'An error occurred while stopping breakout rooms'))
		}
	},
}

export default { state, getters, mutations, actions }
