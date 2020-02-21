/**
 * @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @author Marco Ambrosini <marcoambrosini@pm.me>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import Vue from 'vue'

const state = {
	files: {
	},
}

const getters = {
}

const mutations = {
	/**
     * Adds a "file to be shared to the store"
     * @param {*} state the state object
     * @param {*} file the file to be added to the store
     * @param {*} token the conversation's token
     */
	addFileToBeUploaded(state, { token, index, file }) {
		if (state[token] === undefined) {
			Vue.set(state.files, token, {})
		}
		Vue.set(state.files[token], index, [file, 'toBeUploaded'])
	},
}

const actions = {

	/**
     *
     * @param {object} context The context object
     * @param {string} token The conversation's token
     * @param {array} files The files to be processed and added to the store
     */
	addFilesToBeUploaded(context, { token, files }) {
		for (let index = 0; index < files.length; index++) {
			context.commit('addFileToBeUploaded', { token, index, file: files[index] })
		}
	},
	markFileAsFailedUpload(context, { token, file }) {
		context.commit('markFileAsFailedUpload', token, file)
	},
	markFileAsSuccessUpload(context, { token, file }) {
		context.commit('markFileAsFailedUpload', token, file)
	},
}

export default { state, mutations, getters, actions }
