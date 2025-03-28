/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'

import { showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { getUploader } from '@nextcloud/upload'

import { useTemporaryMessage } from '../composables/useTemporaryMessage.ts'
import { SHARED_ITEM } from '../constants.ts'
import { getDavClient } from '../services/DavClient.js'
import { EventBus } from '../services/EventBus.ts'
import {
	getFileTemplates,
	shareFile,
} from '../services/filesSharingServices.ts'
import { setAttachmentFolder } from '../services/settingsService.ts'
import { useChatExtrasStore } from '../stores/chatExtras.js'
import {
	hasDuplicateUploadNames,
	findUniquePath,
	getFileExtension,
	getFileNamePrompt,
	separateDuplicateUploads,
} from '../utils/fileUpload.js'
import { formatDateTime } from '../utils/formattedTime.ts'
import { parseUploadError } from '../utils/propfindErrorParse.ts'

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

	getPendingUploads: (state, getters) => (uploadId) => {
		return getters.getUploadsArray(uploadId)
			.filter(([_index, uploadedFile]) => uploadedFile.status === 'pendingUpload')
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

	getUploadFile: (state) => (uploadId, index) => {
		return state.uploads[uploadId]?.files[index]
	},

	currentUploadId: (state) => {
		return state.currentUploadId
	},

	areFileTemplatesInitialised: (state) => {
		return state.fileTemplatesInitialised
	},

	fileTemplates: (state) => {
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
			temporaryMessage,
		 })
		if (localUrl) {
			Vue.set(state.localUrls, temporaryMessage.referenceId, localUrl)
		}
	},

	// Marks a given file as initialized (for retry)
	markFileAsInitializedUpload(state, { uploadId, index }) {
		state.uploads[uploadId].files[index].status = 'initialised'
	},

	// Marks a given file as ready to be uploaded (after propfind)
	markFileAsPendingUpload(state, { uploadId, index, sharePath }) {
		state.uploads[uploadId].files[index].status = 'pendingUpload'
		Vue.set(state.uploads[uploadId].files[index], 'sharePath', sharePath)
	},

	// Marks a given file as failed upload
	markFileAsFailedUpload(state, { uploadId, index, status }) {
		state.uploads[uploadId].files[index].status = 'failedUpload'
	},

	// Marks a given file as uploaded
	markFileAsSuccessUpload(state, { uploadId, index, sharePath }) {
		state.uploads[uploadId].files[index].status = 'successUpload'
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

	storeFilesTemplates(state, templates) {
		Vue.set(state, 'fileTemplates', templates)
		state.fileTemplatesInitialised = true
	},

	markFileTemplatesInitialised(state) {
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
	initialiseUpload(context, { uploadId, token, files, rename = false, isVoiceMessage }) {
		// Set last upload id
		context.commit('setCurrentUploadId', uploadId)
		const { createTemporaryMessage } = useTemporaryMessage(context)

		for (let i = 0; i < files.length; i++) {
			const file = files[i]

			if (rename) {
				// note: can't overwrite the original read-only name attribute
				file.newName = formatDateTime(file.lastModified || file.lastModifiedDate, 'YYYYMMDD_HHmmss')
					+ getFileExtension(file.name)
			}

			// Get localUrl for allowed image previews and voice messages uploads
			const localUrl = (isVoiceMessage || SHARED_ITEM.MEDIA_ALLOWED_PREVIEW.includes(file.type))
				? URL.createObjectURL(file)
				: undefined

			// Create a unique index for each file
			const date = new Date()
			const index = 'temp_' + date.getTime() + Math.random()
			// Create temporary message for the file and add it to the message list
			const temporaryMessage = createTemporaryMessage({
				message: '{file}',
				token,
				uploadId,
				index,
				file,
				localUrl,
				messageType: isVoiceMessage ? 'voice-message' : 'comment',
			})
			console.debug('temporarymessage: ', temporaryMessage, 'uploadId', uploadId)
			context.commit('addFileToBeUploaded', { file, temporaryMessage, localUrl, token })
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
		EventBus.emit('upload-discard')

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

		EventBus.emit('upload-start')

		// Tag previously indexed files and add temporary messages to the MessagesList
		// If caption is provided, attach to the last temporary message
		const lastIndex = getters.getInitialisedUploads(uploadId).at(-1).at(0)
		for (const [index, uploadedFile] of getters.getInitialisedUploads(uploadId)) {
			// Store the previously created temporary message
			const message = {
				...uploadedFile.temporaryMessage,
				message: index === lastIndex && caption ? caption : '{file}',
			}
			// Add temporary messages (files) to the messages list
			dispatch('addTemporaryMessage', { token, message })
			// Scroll the message list
			EventBus.emit('scroll-chat-to-bottom', { smooth: true, force: true })
		}

		await dispatch('prepareUploadPaths', { token, uploadId })

		await dispatch('processUpload', { token, uploadId })

		await dispatch('shareFiles', { token, uploadId, lastIndex, caption, options })

		EventBus.emit('upload-finished')
	},

	/**
	 * Prepare unique paths to upload for each file
	 *
	 * @param {object} context the wrapping object
	 * @param {object} data the wrapping object
	 * @param {string} data.token The conversation token
	 * @param {string} data.uploadId The unique uploadId
	 */
	async prepareUploadPaths(context, { token, uploadId }) {
		const client = getDavClient()
		const userRoot = '/files/' + context.getters.getUserId()

		// Store propfind attempts within one action to reduce amount of requests for duplicates
		const knownPaths = {}

		const performPropFind = async ([index, uploadedFile]) => {
			const fileName = (uploadedFile.file.newName || uploadedFile.file.name)
			// Candidate rest of the path
			const path = context.getters.getAttachmentFolder() + '/' + fileName

			try {
				// Check if previous propfind attempt was stored
				const promptPath = getFileNamePrompt(path)
				const knownSuffix = knownPaths[promptPath]
				// Get a unique relative path based on the previous path variable
				const { uniquePath, suffix } = await findUniquePath(client, userRoot, path, knownSuffix)
				knownPaths[promptPath] = suffix
				context.commit('markFileAsPendingUpload', { uploadId, index, sharePath: uniquePath })
			} catch (exception) {
				console.error(`Error while uploading file "${fileName}":` + exception.message, fileName)
				if (exception.response) {
					const message = await parseUploadError(exception)
					if (message) {
						showError(message)
					} else {
						showError(t('spreed', 'Error while uploading file "{fileName}"', { fileName }))
					}
				}
				// Mark the upload as failed in the store
				context.commit('markFileAsFailedUpload', { uploadId, index })
				const { id } = uploadedFile.temporaryMessage
				context.dispatch('markTemporaryMessageAsFailed', { token, id, uploadId, reason: 'failed-upload' })
			}
		}

		const initialisedUploads = context.getters.getInitialisedUploads(uploadId)
		// Check for duplicate names in the uploads array
		if (hasDuplicateUploadNames(initialisedUploads)) {
			const { uniques, duplicates } = separateDuplicateUploads(initialisedUploads)
			await Promise.all(uniques.map(performPropFind))
			// Search for uniquePath one by one
			for (const duplicate of duplicates) {
				await performPropFind(duplicate)
			}
		} else {
			// All original names are unique, prepare files in parallel
			await Promise.all(initialisedUploads.map(performPropFind))
		}
	},

	/**
	 * Upload all pending files to the server
	 *
	 * @param {object} context the wrapping object
	 * @param {object} data the wrapping object
	 * @param {string} data.token The conversation token
	 * @param {string} data.uploadId The unique uploadId
	 */
	async processUpload(context, { token, uploadId }) {
		const performUpload = async ([index, uploadedFile]) => {
			const currentFile = uploadedFile.file
			const fileName = (currentFile.newName || currentFile.name)

			try {
				context.commit('markFileAsUploading', { uploadId, index })
				const uploader = getUploader()
				await uploader.upload(uploadedFile.sharePath, currentFile)
				context.commit('markFileAsSuccessUpload', { uploadId, index })
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
				context.commit('markFileAsFailedUpload', { uploadId, index })
				const { id } = uploadedFile.temporaryMessage
				context.dispatch('markTemporaryMessageAsFailed', { token, id, uploadId, reason })
			}
		}

		const uploads = context.getters.getPendingUploads(uploadId)
		await Promise.all(uploads.map(performUpload))
	},

	/**
	 * Shares the files to the conversation
	 *
	 * @param {object} context the wrapping object
	 * @param {object} data the wrapping object
	 * @param {string} data.token The conversation token
	 * @param {string} data.uploadId The unique uploadId
	 * @param {string} data.lastIndex The index of last uploaded file
	 * @param {string|null} data.caption The text caption to the media
	 * @param {object|null} data.options The share options
	 */
	async shareFiles(context, { token, uploadId, lastIndex, caption, options }) {
		const shares = context.getters.getShareableFiles(uploadId)
		for await (const share of shares) {
			if (!share) {
				continue
			}
			const [index, shareableFile] = share
			const { id, messageType, parent, referenceId } = shareableFile.temporaryMessage || {}

			const talkMetaData = JSON.stringify(Object.assign(
				messageType !== 'comment' ? { messageType } : {},
				caption && index === lastIndex ? { caption } : {},
				options?.silent ? { silent: options.silent } : {},
				parent ? { replyTo: parent.id } : {},
			))

			await context.dispatch('shareFile', { token, path: shareableFile.sharePath, index, uploadId, id, referenceId, talkMetaData })
		}
	},

	/**
	 * Shares the files to the conversation
	 *
	 * @param {object} context the wrapping object
	 * @param {object} data the wrapping object
	 * @param {string} data.token The conversation token
	 * @param {string} data.path The file path from the user's root directory
	 * @param {string} [data.index] The index of uploaded file
	 * @param {string} [data.uploadId] The unique uploadId
	 * @param {string} [data.id] Id of temporary message
	 * @param {string} [data.referenceId] A reference id to recognize the message later
	 * @param {string} [data.talkMetaData] The metadata JSON-encoded object
	 */
	async shareFile(context, { token, path, index, uploadId, id, referenceId, talkMetaData }) {
		try {
			if (uploadId) {
				context.dispatch('markFileAsSharing', { uploadId, index })
			}

			await shareFile({ path, shareWith: token, referenceId, talkMetaData })

			if (uploadId) {
				context.dispatch('markFileAsShared', { uploadId, index })
			}
		} catch (error) {
			console.error('Error while sharing file: ', error)

			if (error?.response?.status === 403) {
				showError(t('spreed', 'You are not allowed to share files'))
			} else if (error?.response?.data?.ocs?.meta?.message) {
				showError(error.response.data.ocs.meta.message)
			} else {
				showError(t('spreed', 'Error while sharing file'))
			}

			if (uploadId) {
				context.dispatch('markTemporaryMessageAsFailed', { token, id, uploadId, reason: 'failed-share' })
			}
		}
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
		if (getters.fileTemplates.length) {
			console.debug('Skip file templates setup as already done')
			commit('markFileTemplatesInitialised')
			return
		}

		if (getters.getUserId() === null) {
			console.debug('Skip file templates setup for participants that are not logged in')
			commit('markFileTemplatesInitialised')
			return
		}

		try {
			const response = await getFileTemplates()
			commit('storeFilesTemplates', response.data.ocs.data)
		} catch (error) {
			console.error('An error happened when trying to load the templates', error)
		}
	},
}

export default { state, mutations, getters, actions }
