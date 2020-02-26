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
	attachmentFolder: '/Talk',
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
			const shareableFiles = {}
			for (const index in state.files[token]) {
				const currentFile = state.files[token][index]
				if (currentFile.status === 'successUpload') {
					shareableFiles[index] = (currentFile.file)
				}
			}
			return shareableFiles
		} else {
			return {}
		}
	},

	getAttachmentFolder: (state) => () => {
		return state.attachmentFolder
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
		Vue.set(state.files[token], Object.keys(state.files[token]).length, { file, status: 'toBeUploaded' })
	},

	// Marks a given file as failed uplosd
	markFileAsFailedUpload(state, { token, index }) {
		state.files[token][index].status = 'failedUpload'
	},

	// Marks a given file as uploaded
	markFileAsSuccessUpload(state, { token, index }) {
		state.files[token][index].status = 'successUpload'
	},

	// Marks a given file as uploading
	markFileAsUploading(state, { token, index }) {
		state.files[token][index].status = 'uploading'
	},

	// Marks a given file as sharing
	markFileAsSharing(state, { token, index }) {
		state.files[token][index].status = 'sharing'
	},

	// Marks a given file as shared
	markFileAsShared(state, { token, index }) {
		state.files[token][index].status = 'shared'
	},

	/**
	 * Set the attachmentFolder
	 *
	 * @param {object} state current store state;
	 * @param {string} attachmentFolder The new target location for attachments
	 */
	setAttachmentFolder(state, attachmentFolder) {
		state.attachmentFolder = attachmentFolder
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
			if (state.files[token][index].status !== 'toBeUploaded') {
				continue
			}
			// Mark file as uploading to prevent a second function call to start a
			// second upload for the same file
			commit('markFileAsUploading', { token, index })
			// Get the current user id
			const userId = getters.getUserId()
			// currentFile to be uploaded
			const currentFile = state.files[token][index].file
			// Destination path on the server
			const path = '/files/' + userId + getters.getAttachmentFolder() + '/' + currentFile.name
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

	/**
	 * Set the folder to store new attachments in
	 *
	 * @param {object} context default store context;
	 * @param {string} attachmentFolder Folder to store new attachments in
	 */
	setAttachmentFolder(context, attachmentFolder) {
		context.commit('setAttachmentFolder', attachmentFolder)
	},

	/**
	 * Mark a file as shared
	 * @param {object} context default store context;
	 * @param {object} param1 conversation token and original file index
	 * @throws {Error} when the item is already being shared by another async call
	 */
	markFileAsSharing({ commit, state }, { token, index }) {
		if (state.files[token][index].status !== 'successUpload') {
			throw new Error('Item is already being shared')
		}
		commit('markFileAsSharing', { token, index })
	},

	/**
	 * Mark a file as shared
	 * @param {object} context default store context;
	 * @param {object} param1 conversation token and original file index
	 */
	markFileAsShared(context, { token, index }) {
		context.commit('markFileAsShared', { token, index })
	},

}

export default { state, mutations, getters, actions }
