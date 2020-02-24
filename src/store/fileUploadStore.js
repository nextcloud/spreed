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
import client from '../services/DavClient'
import { showError } from '@nextcloud/dialogs'

const state = {
	files: {
	},
}

const getters = {
	// Returns all the files that have been marked for upload in a given conversation,
	// regardless of their upload state
	getFiles: (state) => (token) => {
		return state.files[token]
	},
	// Returns all the files that have been successfully uploaded
	getShareableFiles: (state) => (token) => {
		if (state.files[token]) {
			const shareableFiles = []
			for (const index in state.files[token]) {
				const currentFile = state.files[token][index]
				if (currentFile[1] === 'successUpload') {
					shareableFiles.push(currentFile[0])
				}
			}
			return shareableFiles
		} return undefined
	},
}

const mutations = {
	/**
     * Adds a "file to be shared to the store"
     * @param {*} state the state object
     * @param {*} file the file to be added to the store
     * @param {*} token the conversation's token
     */
	addFileToBeUploaded(state, { token, file }) {
		if (!state.files[token]) {
			Vue.set(state.files, token, {})
		}
		Vue.set(state.files[token], Object.keys(state.files[token]).length, [file, 'toBeUploaded'])
	},

	// Marks a given file as failed uplosd
	markFileAsFailedUpload(state, { token, index }) {
		state.files[token][index][1] = 'failedUpload'
	},

	// Marks a given file as uploaded
	markFileAsSuccessUpload(state, { token, index }) {
		state.files[token][index][1] = 'successUpload'
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
		files.forEach(file => {
			context.commit('addFileToBeUploaded', { token, file })
		})
	},

	/**
	 * Uploads the files to the root directory of the user
	 * @param {object} param0 Commit, state and getters
	 * @param {*} token The conversation's token
	 */
	async uploadFiles({ commit, state, getters }, token) {
		// Iterate through the previously indexed files for a given conversation (token)
		for (const index in state.files[token]) {
			// Get the current user id
			const userId = getters.getUserId()
			// currentFile to be uploaded
			const currentFile = state.files[token][index][0]
			// Destination path on the server
			const path = `/files/${userId}/` + currentFile.name
			try {
				// Upload the file
				await client.putFileContents(path, currentFile)
				// Mark the file as uploaded in the store
				commit('markFileAsSuccessUpload', { token, index })
			} catch (exception) {
				console.debug('Error while uploading file:' + exception)
				showError(t('spreed', 'Error while uploading file'))
				// Mark the upload as failed in the store
				commit('markFileAsFailedUpload', { token, index })
			}
		}
	},
}

export default { state, mutations, getters, actions }
