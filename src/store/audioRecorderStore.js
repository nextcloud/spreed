/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
