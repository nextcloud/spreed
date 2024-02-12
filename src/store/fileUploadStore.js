/**
 * @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@icloud.com>
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

import Vue from 'vue'

import { showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import moment from '@nextcloud/moment'

import { getDavClient } from '../services/DavClient.js'
import { EventBus } from '../services/EventBus.js'
import {
	getFileTemplates,
	shareFile,
	// shareMultipleFiles,
} from '../services/filesSharingServices.js'
import { setAttachmentFolder } from '../services/settingsService.js'
import { useChatExtrasStore } from '../stores/chatExtras.js'
import {
	hasDuplicateUploadNames,
	findUniquePath,
	getFileExtension,
	getFileNamePrompt,
	separateDuplicateUploads,
} from '../utils/fileUpload.js'

const state = {
	attachmentFolder: loadState('spreed', 'attachment_folder', ''),
	attachmentFolderFreeSpace: loadState('spreed', 'attachment_folder_free_space', 0),
	uploads: {},
	currentUploadId: undefined,
	localUrls: {},
	fileTemplatesInitialised: false,
	fileTemplates: [],
}

const getters = {

	getUploadsArray: (state) => (uploadId) => {
		if (state.uploads[uploadId]) {
			return Object.entries(state.uploads[uploadId].files)
		} else {
			return []
		}
	},

	getInitialisedUploads: (state, getters) => (uploadId) => {
		return getters.getUploadsArray(uploadId)
			.filter(([_index, uploadedFile]) => uploadedFile.status === 'initialised')
	},

	getFailedUploads: (state, getters) => (uploadId) => {
		return getters.getUploadsArray(uploadId)
			.filter(([_index, uploadedFile]) => uploadedFile.status === 'failedUpload')
	},

	getUploadingFiles: (state, getters) => (uploadId) => {
		return getters.getUploadsArray(uploadId)
			.filter(([_index, uploadedFile]) => uploadedFile.status === 'uploading')
	},

	// Returns all the files that have been successfully uploaded provided an
	// upload id
	getShareableFiles: (state, getters) => (uploadId) => {
		return getters.getUploadsArray(uploadId)
			.filter(([_index, uploadedFile]) => uploadedFile.status === 'successUpload')
	},

	// gets the current attachment folder
	getAttachmentFolder: (state) => () => {
		return state.attachmentFolder
	},

	// gets the current attachment folder
	getAttachmentFolderFreeSpace: (state) => () => {
		return state.attachmentFolderFreeSpace
	},

	// returns the local Url of uploaded image
	getLocalUrl: (state) => (referenceId) => {
		return state.localUrls[referenceId]
	},

	uploadProgress: (state) => (uploadId, index) => {
		if (state.uploads[uploadId]?.files[index]) {
			return state.uploads[uploadId].files[index].uploadedSize / state.uploads[uploadId].files[index].totalSize * 100
		} else {
			return 0
		}
	},

	currentUploadId: (state) => {
		return state.currentUploadId
	},

	areFileTemplatesInitialised: (state) => {
		return state.fileTemplatesInitialised
	},

	getFileTemplates: (state) => () => {
		return state.fileTemplates
	},
}

const mutations = {

	// Adds a "file to be shared to the store"
	addFileToBeUploaded(state, { file, temporaryMessage, localUrl, token }) {
		const uploadId = temporaryMessage.messageParameters.file.uploadId
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
		Vue.set(state.localUrls, temporaryMessage.referenceId, localUrl)
	},

	// Marks a given file as initialized (for retry)
	markFileAsInitializedUpload(state, { uploadId, index }) {
		state.uploads[uploadId].files[index].status = 'initialised'
	},

	// Marks a given file as failed upload
	markFileAsFailedUpload(state, { uploadId, index, status }) {
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

	removeFileFromSelection(state, temporaryMessageId) {
		const uploadId = state.currentUploadId
		for (const key in state.uploads[uploadId].files) {
			if (state.uploads[uploadId].files[key].temporaryMessage.id === temporaryMessageId) {
				Vue.delete(state.uploads[uploadId].files, key)
			}
		}
	},

	discardUpload(state, { uploadId }) {
		Vue.delete(state.uploads, uploadId)
	},

	storeFilesTemplates(state, { template }) {
		state.fileTemplates.push(template)
		state.fileTemplatesInitialised = true
	},

	markFileTemplatesInitialisedForGuests(state) {
		state.fileTemplatesInitialised = true
	},
}

const actions = {

	/**
	 * Initialises uploads and shares files to a conversation
	 *
	 * @param {object} context the wrapping object.
	 * @param {Function} context.commit the contexts commit function.
	 * @param {Function} context.dispatch the contexts dispatch function.
	 * @param {object} data the wrapping object;
	 * @param {object} data.files the files to be processed
	 * @param {string} data.token the conversation's token where to share the files
	 * @param {number} data.uploadId a unique id for the upload operation indexing
	 * @param {boolean} data.rename whether to rename the files (usually after pasting)
	 * @param {boolean} data.isVoiceMessage whether the file is a voice recording
	 */
	async initialiseUpload({ commit, dispatch }, { uploadId, token, files, rename = false, isVoiceMessage }) {
		// Set last upload id
		commit('setCurrentUploadId', uploadId)

		for (let i = 0; i < files.length; i++) {
			const file = files[i]

			if (rename) {
				// note: can't overwrite the original read-only name attribute
				file.newName = moment(file.lastModified || file.lastModifiedDate).format('YYYYMMDD_HHmmss')
					+ getFileExtension(file.name)
			}

			// Get localurl for some image previews
			let localUrl = ''
			if (file.type === 'image/png' || file.type === 'image/gif' || file.type === 'image/jpeg') {
				localUrl = URL.createObjectURL(file)
			} else if (isVoiceMessage) {
				localUrl = file.localUrl
			} else {
				localUrl = OC.MimeType.getIconUrl(file.type)
			}
			// Create a unique index for each file
			const date = new Date()
			const index = 'temp_' + date.getTime() + Math.random()
			// Create temporary message for the file and add it to the message list
			const temporaryMessage = await dispatch('createTemporaryMessage', {
				text: '{file}', token, uploadId, index, file, localUrl, isVoiceMessage,
			})
			console.debug('temporarymessage: ', temporaryMessage, 'uploadId', uploadId)
			commit('addFileToBeUploaded', { file, temporaryMessage, localUrl, token })
		}
	},

	/**
	 * Discards an upload
	 *
	 * @param {object} context the wrapping object.
	 * @param {Function} context.commit the contexts commit function.
	 * @param {object} context.state the contexts state object.
	 * @param {object} uploadId The unique uploadId
	 */
	discardUpload({ commit, state }, uploadId) {
		if (state.currentUploadId === uploadId) {
			commit('setCurrentUploadId', undefined)
		}
		EventBus.$emit('upload-discard')

		commit('discardUpload', { uploadId })
	},

	/**
	 * Uploads the files to the root directory of the user
	 *
	 * @param {object} context the wrapping object.
	 * @param {Function} context.commit the contexts commit function.
	 * @param {Function} context.dispatch the contexts dispatch function.
	 * @param {object} context.getters the contexts getters object.
	 * @param {object} context.state the contexts state object.
	 * @param {object} data the wrapping object
	 * @param {string} data.token The conversation token
	 * @param {string} data.uploadId The unique uploadId
	 * @param {string|null} data.caption The text caption to the media
	 * @param {object|null} data.options The share options
	 */
	async uploadFiles({ commit, dispatch, state, getters }, { token, uploadId, caption, options }) {
		if (state.currentUploadId === uploadId) {
			commit('setCurrentUploadId', undefined)
		}

		EventBus.$emit('upload-start')

		// Check for quantity of files to upload
		// const isMultipleFilesUpload = getters.getUploadsArray(uploadId).length !== 1

		// Tag previously indexed files and add temporary messages to the MessagesList
		// If caption is provided, attach to the last temporary message
		const lastIndex = getters.getInitialisedUploads(uploadId).at(-1).at(0)
		for (const [index, uploadedFile] of getters.getInitialisedUploads(uploadId)) {
			// mark all files as uploading
			commit('markFileAsUploading', { uploadId, index })
			// Store the previously created temporary message
			const message = {
				...uploadedFile.temporaryMessage,
				message: index === lastIndex && caption ? caption : '{file}',
			}
			// Add temporary messages (files) to the messages list
			dispatch('addTemporaryMessage', { token, message })
			// Scroll the message list
			EventBus.$emit('scroll-chat-to-bottom', { force: true })
		}

		// Store propfind attempts within one action to reduce amount of requests for duplicates
		const knownPaths = {}

		const performUpload = async ([index, uploadedFile]) => {
			// currentFile to be uploaded
			const currentFile = uploadedFile.file
			// userRoot path
			const fileName = (currentFile.newName || currentFile.name)
			// Candidate rest of the path
			const path = getters.getAttachmentFolder() + '/' + fileName

			try {
				// Check if previous propfind attempt was stored
				const promptPath = getFileNamePrompt(path)
				const knownSuffix = knownPaths[promptPath]
				// Get a unique relative path based on the previous path variable
				const { uniquePath, suffix } = await findUniquePath(client, userRoot, path, knownSuffix)
				knownPaths[promptPath] = suffix

				// Upload the file
				const currentFileBuffer = await new Blob([currentFile]).arrayBuffer()
				await client.putFileContents(userRoot + uniquePath, currentFileBuffer, {
					onUploadProgress: progress => {
						const uploadedSize = progress.loaded
						commit('setUploadedSize', { state, uploadId, index, uploadedSize })
					},
					contentLength: currentFile.size,
				})
				// Path for the sharing request
				const sharePath = '/' + uniquePath
				// Mark the file as uploaded in the store
				commit('markFileAsSuccessUpload', { uploadId, index, sharePath })
			} catch (exception) {
				let reason = 'failed-upload'
				if (exception.response) {
					console.error(`Error while uploading file "${fileName}":` + exception, fileName, exception.response.status)
					if (exception.response.status === 507) {
						reason = 'quota'
						showError(t('spreed', 'Not enough free space to upload file "{fileName}"', { fileName }))
					} else {
						showError(t('spreed', 'Error while uploading file "{fileName}"', { fileName }))
					}
				} else {
					console.error(`Error while uploading file "${fileName}":` + exception.message, fileName)
					showError(t('spreed', 'Error while uploading file "{fileName}"', { fileName }))
				}

				// Mark the upload as failed in the store
				commit('markFileAsFailedUpload', { uploadId, index })
				const { token, id } = uploadedFile.temporaryMessage
				dispatch('markTemporaryMessageAsFailed', { token, id, uploadId, reason })
			}
		}

		const performShare = async ([index, shareableFile]) => {
			const path = shareableFile.sharePath
			const temporaryMessage = shareableFile.temporaryMessage

			const rawMetadata = { messageType: temporaryMessage.messageType }
			if (caption && index === lastIndex) {
				Object.assign(rawMetadata, { caption })
			}
			if (options?.silent) {
				Object.assign(rawMetadata, { silent: options.silent })
			}
			if (temporaryMessage.parent) {
				Object.assign(rawMetadata, { replyTo: temporaryMessage.parent.id })
			}
			// if (isMultipleFilesUpload) {
			// 	Object.assign(rawMetadata, { noMessage: isMultipleFilesUpload })
			// }
			const metadata = JSON.stringify(rawMetadata)

			const { token, id, referenceId } = temporaryMessage
			try {
				dispatch('markFileAsSharing', { uploadId, index })
				await shareFile(path, token, referenceId, metadata)
				dispatch('markFileAsShared', { uploadId, index })

				// return share id of file returned from the server
				// return response.data.ocs.data.id
			} catch (error) {
				if (error?.response?.status === 403) {
					showError(t('spreed', 'You are not allowed to share files'))
				} else {
					showError(t('spreed', 'An error happened when trying to share your file'))
				}
				dispatch('markTemporaryMessageAsFailed', { token, id, uploadId, reason: 'failed-share' })
				console.error('An error happened when trying to share your file: ', error)
			}
		}

		const client = getDavClient()
		const userRoot = '/files/' + getters.getUserId()

		const uploads = getters.getUploadingFiles(uploadId)
		// Check for duplicate names in the uploads array
		if (hasDuplicateUploadNames(uploads)) {
			const { uniques, duplicates } = separateDuplicateUploads(uploads)
			await Promise.all(uniques.map(upload => performUpload(upload)))
			// Search for uniquePath and upload files one by one to avoid 423 (Locked)
			for (const upload of duplicates) {
				await performUpload(upload)
			}
		} else {
			// All original names are unique, upload files in parallel
			await Promise.all(uploads.map(upload => performUpload(upload)))
		}

		const shares = getters.getShareableFiles(uploadId)
		// Check if caption message for share was provided
		if (caption) {
			const captionShareIndex = shares.findIndex(([index]) => index === lastIndex)

			// Share all files in parallel, except for last one
			const parallelShares = shares.slice(0, captionShareIndex).concat(shares.slice(captionShareIndex + 1))
			await Promise.all(parallelShares.map(share => performShare(share)))

			// Share a last file, where caption is attached
			await performShare(shares.at(captionShareIndex))
		} else {
			// Share all files in parallel
			await Promise.all(shares.map(share => performShare(share)))
		}

		// if (isMultipleFilesUpload) {
		// 	await shareMultipleFiles(temporaryMessage.token, shareIds, caption, temporaryMessage.actorDisplayName, temporaryMessage.referenceId)
		// }

		EventBus.$emit('upload-finished')
	},

	/**
	 * Re-initialize failed uploads and open UploadEditor dialog
	 * Insert caption if was provided
	 *
	 * @param {object} context default store context;
	 * @param {object} data payload;
	 * @param {string} data.token the conversation token;
	 * @param {string} data.uploadId the internal id of the upload;
	 * @param {string} [data.caption] the message caption;
	 */
	retryUploadFiles(context, { token, uploadId, caption }) {
		context.getters.getFailedUploads(uploadId).forEach(([index, file]) => {
			context.dispatch('removeTemporaryMessageFromStore', { token, id: file.temporaryMessage.id })
			context.commit('markFileAsInitializedUpload', { uploadId, index })
		})

		if (caption) {
			const chatExtrasStore = useChatExtrasStore()
			chatExtrasStore.setChatInput({ token, text: caption })
		}

		context.commit('setCurrentUploadId', uploadId)
	},

	/**
	 * Set the folder to store new attachments in
	 *
	 * @param {object} context default store context;
	 * @param {string} attachmentFolder Folder to store new attachments in
	 */
	async setAttachmentFolder(context, attachmentFolder) {
		await setAttachmentFolder(attachmentFolder)
		context.commit('setAttachmentFolder', attachmentFolder)
	},

	/**
	 * Mark a file as shared
	 *
	 * @param {object} context the wrapping object.
	 * @param {Function} context.commit the contexts commit function.
	 * @param {object} context.state the contexts state object.
	 * @param {object} data the wrapping object;
	 * @param {string} data.uploadId The id of the upload process
	 * @param {number} data.index The object index inside the upload process
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
	 *
	 * @param {object} context default store context;
	 * @param {object} data the wrapping object;
	 * @param {string} data.uploadId The id of the upload process
	 * @param {number} data.index The object index inside the upload process
	 */
	markFileAsShared(context, { uploadId, index }) {
		context.commit('markFileAsShared', { uploadId, index })
	},

	/**
	 * Mark a file as shared
	 *
	 * @param {object} context the wrapping object.
	 * @param {Function} context.commit the contexts commit function.
	 * @param {string} temporaryMessageId message id of the temporary message associated to the file to remove
	 */
	removeFileFromSelection({ commit }, temporaryMessageId) {
		commit('removeFileFromSelection', temporaryMessageId)
	},

	async getFileTemplates({ commit, getters }) {
		if (getters.getUserId() === null) {
			console.debug('Skip file templates setup for participants that are not logged in')
			commit('markFileTemplatesInitialisedForGuests')
			return
		}

		try {
			const response = await getFileTemplates()

			response.data.ocs.data.forEach(template => {
				commit('storeFilesTemplates', { template })
			})
		} catch (error) {
			console.error('An error happened when trying to load the templates', error)
		}
	},
}

export default { state, mutations, getters, actions }
