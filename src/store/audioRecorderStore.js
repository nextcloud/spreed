/**
 * @copyright Copyright (c) 2021 Marco Ambrosini <marcoambrosini@icloud.com>
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

import { register } from 'extendable-media-recorder'
import { connect } from 'extendable-media-recorder-wav-encoder'

const state = () => ({
	encoderReady: false,
})

const getters = {
	encoderReady: state => {
		return state.encoderReady
	},
}

const mutations = {
	encoderReady: (state) => {
		state.encoderReady = true
	},
}

const actions = {
	async initializeAudioEncoder({ commit, state }) {
		if (!state.encoderReady) {
			register(await connect())
			commit('encoderReady')
		}
	},
}

export default { state, mutations, getters, actions }
