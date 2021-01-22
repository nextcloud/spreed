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
import { loadState } from '@nextcloud/initial-state'
import { findUniquePath } from '../utils/fileUpload'
import createTemporaryMessage from '../utils/temporaryMessage'
import { EventBus } from '../services/EventBus'
import { shareFile } from '../services/filesSharingServices'

const state = {
	attachmentFolder: loadState('spreed', 'attachment_folder'),
	uploads: {
	},
	currentUploadId: undefined,
	showUploadEditor: false,
}

const getters = {

	getInitialisedUploads: (state) => (uploadId) => {
		if (state.uploads[uploadId]) {
			const initialisedUploads = {}
			for (const index in state.uploads[uploadId].files) {
				const currentFile = state.uploads[uploadId].files[index]
				if (currentFile.status === 'initialised') {
					initialisedUploads[index] = (currentFile)
				}
			}
			return initialisedUploads
		} else {
			return {}
		}
	},

	// Returns all the files that have been successfully uploaded provided an
	// upload id
	getShareableFiles: (state) => (uploadId) => {
		if (state.uploads[uploadId]) {
			const shareableFiles = {}
			for (const index in state.uploads[uploadId].files) {
				const currentFile = state.uploads[uploadId].files[index]
				if (currentFile.status === 'successUpload') {
					shareableFiles[index] = (currentFile)
				}
			}
			return shareableFiles
		} else {
			return {}
		}
	},

	// gets the current attachment folder
	getAttachmentFolder: (state) => () => {
		return state.attachmentFolder
	},

	uploadProgress: (state) => (uploadId, index) => {
		if (state.uploads[uploadId].files[index]) {
			return state.uploads[uploadId].files[index].uploadedSize / state.uploads[uploadId].files[index].totalSize * 100
		} else {
			return 0
		}
	},

	currentUploadId: (state) => {
		return state.currentUploadId
	},

	showUploadEditor: (state) => {
		return state.showUploadEditor
	},
}

const mutations = {

	// Adds a "file to be shared to the store"
	addFileToBeUploaded(state, { file, temporaryMessage }) {
		const uploadId = temporaryMessage.messageParameters.file.uploadId
		const token = temporaryMessage.messageParameters.file.token
		const index = temporaryMessage.messageParameters.file.index
		// Create upload id if not present
		if (!state.uploads[uploadId]) {
			Vue.set(state.uploads, uploadId, {
				token,
				files: {},
			})
		}
		Vue.set(state.uploads[uploadId].files, index, {
			file,
			status: 'initialised',
			totalSize: file.size,
			uploadedSize: 0,
			temporaryMessage,
		 })
	},

	// Marks a given file as failed upload
	markFileAsFailedUpload(state, { uploadId, index }) {
		state.uploads[uploadId].files[index].status = 'failedUpload'
	},

	// Marks a given file as uploaded
	markFileAsSuccessUpload(state, { uploadId, index, sharePath }) {
		state.uploads[uploadId].files[index].status = 'successUpload'
		Vue.set(state.uploads[uploadId].files[index], 'sharePath', sharePath)
	},

	// Marks a given file as uploading
	markFileAsUploading(state, { uploadId, index }) {
		state.uploads[uploadId].files[index].status = 'uploading'
	},

	// Marks a given file as sharing
	markFileAsSharing(state, { uploadId, index }) {
		state.uploads[uploadId].files[index].status = 'sharing'
	},

	// Marks a given file as shared
	markFileAsShared(state, { uploadId, index }) {
		state.uploads[uploadId].files[index].status = 'shared'
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

	// Sets uploaded amount of bytes
	setUploadedSize(state, { uploadId, index, uploadedSize }) {
		state.uploads[uploadId].files[index].uploadedSize = uploadedSize
	},

	// Set temporary message for each file
	setTemporaryMessageForFile(state, { uploadId, index, temporaryMessage }) {
		console.debug('uploadId: ' + uploadId + ' index: ' + index)
		Vue.set(state.uploads[uploadId].files[index], 'temporaryMessage', temporaryMessage)
	},

	// Sets the id of the current upload operation
	setCurrentUploadId(state, currentUploadId) {
		state.currentUploadId = currentUploadId
	},

	// Shows hides the upload editor
	showUploadEditor(state, show) {
		state.showUploadEditor = show
	},

	removeFileFromSelection(state, fileId) {
		const uploadId = state.currentUploadId
		for (const key in state.uploads[uploadId].files) {
			if (state.uploads[uploadId].files[key].temporaryMessage.id === fileId) {
				Vue.delete(state.uploads[uploadId].files, key)
			}
		}
	},
}

const actions = {

	initialiseUpload({ commit, dispatch }, { uploadId, token, files }) {
		// Set last upload id
		commit('setCurrentUploadId', uploadId)
		// Show upload editor
		commit('showUploadEditor', true)

		files.forEach(file => {
			// Get localurl for previews
			const localUrl = URL.createObjectURL(file)
			// Create a unique index for each file
			const date = new Date()
			const index = 'temp_' + date.getTime() + Math.random()
			// Create temporary message for the file and add it to the message list
			const temporaryMessage = createTemporaryMessage('{file}', token, uploadId, index, file, localUrl)
			console.debug('temporarymessage: ', temporaryMessage, 'uploadId', uploadId)
			commit('addFileToBeUploaded', { file, temporaryMessage })
		})
	},

	/**
	 * Uploads the files to the root directory of the user
	 * @param {object} param0 Commit, state and getters
	 * @param {object} uploadId The unique uploadId
	 */
	async uploadFiles({ commit, dispatch, state, getters }, uploadId) {
		EventBus.$emit('uploadStart')

		// Tag the previously indexed files and add the temporary messages to the
		// messages list
		for (const index in state.uploads[uploadId].files) {
			// mark all files as uploading
			commit('markFileAsUploading', { uploadId, index })
			// Store the previously created temporary message
			const temporaryMessage = state.uploads[uploadId].files[index].temporaryMessage
			// Add temporary messages (files) to the messages list
			dispatch('addTemporaryMessage', temporaryMessage)
			// Scroll the message list
			EventBus.$emit('scrollChatToBottom')
		}
		// Iterate again and perform the uploads
		for (const index in state.uploads[uploadId].files) {
			// currentFile to be uploaded
			const currentFile = state.uploads[uploadId].files[index].file
			// userRoot path
			const userRoot = '/files/' + getters.getUserId()
			// Candidate rest of the path
			const path = getters.getAttachmentFolder() + '/' + (currentFile.newName || currentFile.name)
			// Get a unique relative path based on the previous path variable
			const uniquePath = await findUniquePath(client, userRoot, path)
			try {
				// Upload the file
				await client.putFileContents(userRoot + uniquePath, currentFile, { onUploadProgress: progress => {
					const uploadedSize = progress.loaded
					commit('setUploadedSize', { state, uploadId, index, uploadedSize })
				} })
				// Path for the sharing request
				const sharePath = '/' + uniquePath
				// Mark the file as uploaded in the store
				commit('markFileAsSuccessUpload', { uploadId, index, sharePath })
			} catch (exception) {
				console.debug('Error while uploading file:' + exception)
				showError(t('spreed', 'Error while uploading file'))
				// Mark the upload as failed in the store
				commit('markFileAsFailedUpload', { uploadId, index })
			}

			// Get the files that have successfully been uploaded from the store
			const shareableFiles = getters.getShareableFiles(uploadId)
			// Share each of those files to the conversation
			for (const index in shareableFiles) {
				const path = shareableFiles[index].sharePath
				try {
					const temporaryMessage = shareableFiles[index].temporaryMessage
					const token = temporaryMessage.token
					dispatch('markFileAsSharing', { uploadId, index })
					await shareFile(path, token, temporaryMessage.referenceId)
					dispatch('markFileAsShared', { uploadId, index })
				} catch (exception) {
					console.debug('An error happened when trying to share your file: ', exception)
				}
			}
		}
		EventBus.$emit('uploadFinished')
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
	 * @param {object} param1 The unique upload id original file index
	 * @throws {Error} when the item is already being shared by another async call
	 */
	markFileAsSharing({ commit, state }, { uploadId, index }) {
		if (state.uploads[uploadId].files[index].status !== 'successUpload') {
			throw new Error('Item is already being shared')
		}
		commit('markFileAsSharing', { uploadId, index })
	},

	/**
	 * Mark a file as shared
	 * @param {object} context default store context;
	 * @param {object} param1 The unique upload id original file index
	 */
	markFileAsShared(context, { uploadId, index }) {
		context.commit('markFileAsShared', { uploadId, index })
	},

	removeFileFromSelection({ commit }, fileId) {
		commit('removeFileFromSelection', fileId)
	},

}

export default { state, mutations, getters, actions }
