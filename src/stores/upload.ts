/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	ChatMessage,
	UploadEntry,
	UploadFile,
} from '../types/index.ts'
import type { TempChatMessageWithFile } from '../utils/prepareTemporaryMessage.ts'
import type { PROPFINDException } from '../utils/propfindErrorParse.ts'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { getUploader } from '@nextcloud/upload'
import { defineStore } from 'pinia'
import { reactive, ref } from 'vue'
import { useStore } from 'vuex'
import { useTemporaryMessage } from '../composables/useTemporaryMessage.ts'
import { MESSAGE, SHARED_ITEM } from '../constants.ts'
import { getDavClient } from '../services/DavClient.ts'
import { EventBus } from '../services/EventBus.ts'
import {
	shareFile as shareFileApi,
} from '../services/filesSharingServices.ts'
import { isAxiosErrorResponse } from '../types/guards.ts'
import {
	findUniquePath,
	getFileExtension,
	getFileNamePrompt,
	hasDuplicateUploadNames,
	separateDuplicateUploads,
} from '../utils/fileUpload.ts'
import { parseUploadError } from '../utils/propfindErrorParse.ts'
import { useActorStore } from './actor.ts'
import { useChatExtrasStore } from './chatExtras.ts'
import { useSettingsStore } from './settings.ts'

type UploadsState = {
	[uploadId: string]: {
		token: string
		files: {
			[index: string]: UploadFile
		}
	}
}

type UploadFilesPayload = {
	token: string
	uploadId: string
	caption?: string
	options: Pick<ChatMessage, | 'threadId' | 'threadTitle' | 'silent' | 'parent'> | null
}

export const useUploadStore = defineStore('upload', () => {
	const actorStore = useActorStore()
	const chatExtrasStore = useChatExtrasStore()
	const settingsStore = useSettingsStore()
	const vuexStore = useStore()

	const { createTemporaryMessage } = useTemporaryMessage()

	const uploads = reactive<UploadsState>({})
	const currentUploadId = ref<string | undefined>(undefined)
	const localUrls = reactive<Record<string, string>>({})

	/**
	 * Returns an array of uploads for a given upload id
	 *
	 * @param uploadId unique identifier
	 */
	function getUploadsArray(uploadId: string): UploadEntry[] {
		if (uploads[uploadId]) {
			return Object.entries(uploads[uploadId].files)
		} else {
			return []
		}
	}

	/**
	 * Returns all the files that are initialised for a given upload id
	 *
	 * @param uploadId unique identifier
	 */
	function getInitialisedUploads(uploadId: string): UploadEntry[] {
		return getUploadsArray(uploadId)
			.filter(([_index, uploadedFile]) => uploadedFile.status === 'initialised')
	}

	/**
	 * Returns all the files that are pending upload for a given upload id
	 *
	 * @param uploadId unique identifier
	 */
	function getPendingUploads(uploadId: string): UploadEntry[] {
		return getUploadsArray(uploadId)
			.filter(([_index, uploadedFile]) => uploadedFile.status === 'pendingUpload')
	}

	/**
	 * Returns all the files that are failed to upload for a given upload id
	 *
	 * @param uploadId unique identifier
	 */
	function getFailedUploads(uploadId: string): UploadEntry[] {
		return getUploadsArray(uploadId)
			.filter(([_index, uploadedFile]) => uploadedFile.status === 'failedUpload')
	}

	/**
	 * Returns all the files that are currently uploading for a given upload id
	 *
	 * @param uploadId unique identifier
	 */
	function getUploadingFiles(uploadId: string): UploadEntry[] {
		return getUploadsArray(uploadId)
			.filter(([_index, uploadedFile]) => uploadedFile.status === 'uploading')
	}

	/**
	 * Returns all the files that have been successfully uploaded for a given upload id
	 *
	 * @param uploadId unique identifier
	 */
	function getShareableFiles(uploadId: string): UploadEntry[] {
		return getUploadsArray(uploadId)
			.filter(([_index, uploadedFile]) => uploadedFile.status === 'successUpload')
	}

	/**
	 * Returns the local URL of uploaded image
	 *
	 * @param referenceId
	 */
	function getLocalUrl(referenceId: string): string | undefined {
		return localUrls[referenceId]
	}

	/**
	 * Returns a specific upload file
	 *
	 * @param uploadId unique identifier
	 * @param index
	 */
	function getUploadFile(uploadId: string, index: number): UploadFile | undefined {
		return uploads[uploadId]?.files[index]
	}

	/**
	 * Adds a "file to be shared" to the store
	 *
	 * @param payload wrapping object
	 * @param payload.file the file to be uploaded
	 * @param payload.temporaryMessage the temporary message associated to the file
	 * @param payload.localUrl the local URL of the file (for image previews)
	 * @param payload.token the conversation token
	 */
	function addFileToBeUploaded({ file, temporaryMessage, localUrl, token }: { file: File, temporaryMessage: TempChatMessageWithFile, localUrl?: string, token: string }) {
		const uploadId = temporaryMessage.messageParameters.file.uploadId
		const index = temporaryMessage.messageParameters.file.index
		// Create upload id if not present
		if (!uploads[uploadId]) {
			uploads[uploadId] = {
				token,
				files: {},
			}
		}
		uploads[uploadId].files[index] = {
			// @ts-expect-error: Type File is not assignable to type
			file,
			status: 'initialised',
			totalSize: file.size,
			temporaryMessage,
		}
		if (localUrl) {
			localUrls[temporaryMessage.referenceId] = localUrl
		}
	}

	/**
	 * Marks a given file as initialized (for retry)
	 *
	 * @param payload wrapping object
	 * @param payload.uploadId unique identifier
	 * @param payload.index file index
	 */
	function markFileAsInitializedUpload({ uploadId, index }: { uploadId: string, index: string }) {
		uploads[uploadId].files[index].status = 'initialised'
	}

	/**
	 * Marks a given file as ready to be uploaded (after PROPFIND)
	 *
	 * @param payload wrapping object
	 * @param payload.uploadId unique identifier
	 * @param payload.index file index
	 * @param payload.sharePath the unique path where to upload the file
	 */
	function markFileAsPendingUpload({ uploadId, index, sharePath }: { uploadId: string, index: string, sharePath: string }) {
		uploads[uploadId].files[index].status = 'pendingUpload'
		uploads[uploadId].files[index].sharePath = sharePath
	}

	/**
	 * Marks a given file as failed upload
	 *
	 * @param payload wrapping object
	 * @param payload.uploadId unique identifier
	 * @param payload.index file index
	 */
	function markFileAsFailedUpload({ uploadId, index }: { uploadId: string, index: string }) {
		uploads[uploadId].files[index].status = 'failedUpload'
	}

	/**
	 * Marks a given file as uploaded
	 *
	 * @param payload wrapping object
	 * @param payload.uploadId unique identifier
	 * @param payload.index file index
	 */
	function markFileAsSuccessUpload({ uploadId, index }: { uploadId: string, index: string }) {
		uploads[uploadId].files[index].status = 'successUpload'
	}

	/**
	 * Marks a given file as uploading
	 *
	 * @param payload wrapping object
	 * @param payload.uploadId unique identifier
	 * @param payload.index file index
	 */
	function markFileAsUploading({ uploadId, index }: { uploadId: string, index: string }) {
		uploads[uploadId].files[index].status = 'uploading'
	}

	/**
	 * Marks a given file as sharing
	 *
	 * @param payload wrapping object
	 * @param payload.uploadId unique identifier
	 * @param payload.index file index
	 */
	function markFileAsSharing({ uploadId, index }: { uploadId: string, index: string }) {
		if (uploads[uploadId].files[index].status !== 'successUpload') {
			throw new Error('Item is already being shared')
		}
		uploads[uploadId].files[index].status = 'sharing'
	}

	/**
	 * Marks a given file as shared
	 *
	 * @param payload wrapping object
	 * @param payload.uploadId unique identifier
	 * @param payload.index file index
	 */
	function markFileAsShared({ uploadId, index }: { uploadId: string, index: string }) {
		uploads[uploadId].files[index].status = 'shared'
	}

	/**
	 * Removes a file from the current selection
	 *
	 * @param temporaryMessageId message id of the temporary message associated to the file
	 */
	function removeFileFromSelection(temporaryMessageId: number) {
		const uploadId = currentUploadId.value!
		for (const index in uploads[uploadId].files) {
			if (uploads[uploadId].files[index].temporaryMessage!.id === temporaryMessageId) {
				delete uploads[uploadId].files[index]
			}
		}
	}

	/**
	 * Initialises uploads and shares files to a conversation
	 *
	 * @param payload the wrapping object
	 * @param payload.files the files to be processed
	 * @param payload.token the conversation's token where to share the files
	 * @param payload.threadId the thread id where to share the files
	 * @param payload.uploadId unique identifier
	 * @param payload.rename whether to rename the files (usually after pasting)
	 * @param payload.isVoiceMessage whether the file is a voice recording
	 */
	function initialiseUpload({ uploadId, token, threadId, files, rename = false, isVoiceMessage }: { uploadId: string, token: string, threadId?: number, files: File[], rename?: boolean, isVoiceMessage?: boolean }) {
		// Set last upload id
		currentUploadId.value = uploadId
		for (let i = 0; i < files.length; i++) {
			const file = files[i]

			if (rename) {
				// note: can't overwrite the original read-only name attribute
				// 'YYYY-MM-DDTHH:mm:ss.sssZ' -> 'YYYYMMDD_HHmmss.ext'
				// @ts-expect-error: property does not exist on type File
				file.newName = new Date(file.lastModified ?? file.lastModifiedDate)
					.toISOString().replace('T', '_').replace(/[:-]/g, '').split('.')[0]
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
				threadId,
				uploadId,
				index,
				// @ts-expect-error: Type File is not assignable to type
				file,
				localUrl,
				isThread: threadId ? true : undefined,
				messageType: isVoiceMessage ? MESSAGE.TYPE.VOICE_MESSAGE : MESSAGE.TYPE.COMMENT,
			}) as TempChatMessageWithFile
			console.debug('temporarymessage: ', temporaryMessage, 'uploadId', uploadId)
			addFileToBeUploaded({ file, temporaryMessage, localUrl, token })
		}
	}

	/**
	 * Discards an upload
	 *
	 * @param uploadId unique identifier
	 */
	function discardUpload(uploadId: string) {
		if (currentUploadId.value === uploadId) {
			currentUploadId.value = undefined
		}
		EventBus.emit('upload-discard')

		delete uploads[uploadId]
	}

	/**
	 * Uploads the files to the root directory of the user
	 *
	 * @param payload the wrapping object
	 * @param payload.token The conversation token
	 * @param payload.uploadId unique identifier
	 * @param payload.caption The text caption to the media
	 * @param payload.options The share options
	 */
	async function uploadFiles({ token, uploadId, caption, options }: UploadFilesPayload) {
		if (currentUploadId.value === uploadId) {
			currentUploadId.value = undefined
		}

		EventBus.emit('upload-start')

		// Tag previously indexed files and add temporary messages to the MessagesList
		// If caption is provided, attach to the last temporary message
		const lastIndex = getInitialisedUploads(uploadId).at(-1)![0]
		for (const [index, uploadedFile] of getInitialisedUploads(uploadId)) {
			// Store the previously created temporary message
			const message = {
				...uploadedFile.temporaryMessage,
				parent: options?.parent ? options.parent : uploadedFile.temporaryMessage.parent,
				message: index === lastIndex && caption ? caption : '{file}',
			}
			// Add temporary messages (files) to the messages list
			vuexStore.dispatch('addTemporaryMessage', { token, message })
			// Scroll the message list
			EventBus.emit('scroll-chat-to-bottom', { smooth: true, force: true })
		}

		await prepareUploadPaths({ token, uploadId })

		await processUpload({ token, uploadId })

		await shareFiles({ token, uploadId, lastIndex, caption, options })

		EventBus.emit('upload-finished')
	}

	/**
	 * Prepare unique paths to upload for each file
	 *
	 * @param payload the wrapping object
	 * @param payload.token The conversation token
	 * @param payload.uploadId unique identifier
	 */
	async function prepareUploadPaths({ token, uploadId }: { token: string, uploadId: string }) {
		const client = getDavClient()
		const userRoot = '/files/' + actorStore.userId

		// Store propfind attempts within one action to reduce amount of requests for duplicates
		const knownPaths: Record<string, number> = {}

		const performPropFind = async (uploadEntry: UploadEntry) => {
			const [index, uploadedFile] = uploadEntry
			const fileName = (uploadedFile.file.newName || uploadedFile.file.name)
			// Candidate rest of the path
			const path = settingsStore.attachmentFolder + '/' + fileName

			try {
				// Check if previous propfind attempt was stored
				const promptPath = getFileNamePrompt(path)
				const knownSuffix = knownPaths[promptPath]
				// Get a unique relative path based on the previous path variable
				const { uniquePath, suffix } = await findUniquePath(client, userRoot, path, knownSuffix)
				knownPaths[promptPath] = suffix
				markFileAsPendingUpload({ uploadId, index, sharePath: uniquePath })
			} catch (exception: unknown) {
				// FIXME add a type guard
				const propfindError = exception as PROPFINDException
				console.error('Error while uploading file "%s": %s', fileName, propfindError.message)
				if ('response' in propfindError) {
					const message = await parseUploadError(propfindError)
					if (message) {
						showError(message)
					} else {
						showError(t('spreed', 'Error while uploading file "{fileName}"', { fileName }))
					}
				}
				// Mark the upload as failed in the store
				markFileAsFailedUpload({ uploadId, index })
				const { id } = uploadedFile.temporaryMessage
				vuexStore.dispatch('markTemporaryMessageAsFailed', { token, id, uploadId, reason: 'failed-upload' })
			}
		}

		const initialisedUploads = getInitialisedUploads(uploadId)
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
	}

	/**
	 * Upload all pending files to the server
	 *
	 * @param payload the wrapping object
	 * @param payload.token The conversation token
	 * @param payload.uploadId unique identifier
	 */
	async function processUpload({ token, uploadId }: { token: string, uploadId: string }) {
		const performUpload = async (uploadEntry: UploadEntry) => {
			const [index, uploadedFile] = uploadEntry
			const currentFile = uploadedFile.file
			const fileName = (currentFile.newName || currentFile.name)

			try {
				markFileAsUploading({ uploadId, index })
				const uploader = getUploader()
				// @ts-expect-error: Type File is not assignable to type
				await uploader.upload(uploadedFile.sharePath!, currentFile)
				markFileAsSuccessUpload({ uploadId, index })
			} catch (exception) {
				let reason = 'failed-upload'
				if (isAxiosErrorResponse(exception) && exception.response) {
					console.error('Error while uploading file "%s": %s', fileName, exception.message)
					if (exception.response.status === 507) {
						reason = 'quota'
						showError(t('spreed', 'Not enough free space to upload file "{fileName}"', { fileName }))
					} else {
						showError(t('spreed', 'Error while uploading file "{fileName}"', { fileName }))
					}
				} else {
					console.error('Error while uploading file "%s": %s', fileName, (exception as Error).message)
					showError(t('spreed', 'Error while uploading file "{fileName}"', { fileName }))
				}

				// Mark the upload as failed in the store
				markFileAsFailedUpload({ uploadId, index })
				const { id } = uploadedFile.temporaryMessage
				vuexStore.dispatch('markTemporaryMessageAsFailed', { token, id, uploadId, reason })
			}
		}

		const uploads = getPendingUploads(uploadId)
		await Promise.all(uploads.map(performUpload))
	}

	/**
	 * Shares the files to the conversation
	 *
	 * @param payload the wrapping object
	 * @param payload.token The conversation token
	 * @param payload.uploadId unique identifier
	 * @param payload.lastIndex The index of last uploaded file
	 * @param payload.caption The text caption to the media
	 * @param payload.options The share options
	 */
	async function shareFiles({ token, uploadId, lastIndex, caption, options }: UploadFilesPayload & { lastIndex: string }) {
		const shares = getShareableFiles(uploadId)
		for await (const share of shares) {
			if (!share) {
				continue
			}
			const [index, shareableFile] = share
			const { id, messageType, referenceId } = shareableFile.temporaryMessage || {}

			const talkMetaData = JSON.stringify(Object.assign(
				messageType !== MESSAGE.TYPE.COMMENT ? { messageType } : {},
				caption && index === lastIndex ? { caption } : {},
				options?.silent ? { silent: options.silent } : {},
				options?.threadId ? { threadId: options.threadId } : {},
				options?.threadTitle ? { threadTitle: options.threadTitle } : {},
				options?.parent ? { replyTo: options.parent.id } : {},
			))

			await shareFile({ token, path: shareableFile.sharePath!, index, uploadId, id, referenceId, talkMetaData })
		}
	}

	/**
	 * Shares the files to the conversation
	 *
	 * @param payload the wrapping object
	 * @param payload.token The conversation token
	 * @param payload.path The file path from the user's root directory
	 * @param [payload.index] The index of uploaded file
	 * @param [payload.uploadId] unique identifier
	 * @param [payload.id] Id of temporary message
	 * @param [payload.referenceId] A reference id to recognize the message later
	 * @param [payload.talkMetaData] The metadata JSON-encoded object
	 */
	async function shareFile({ token, path, index, uploadId, id, referenceId, talkMetaData }: { token: string, path: string, index: string, uploadId: string, id: number, referenceId: string, talkMetaData: string }) {
		try {
			if (uploadId) {
				markFileAsSharing({ uploadId, index })
			}

			await shareFileApi({ path, shareWith: token, referenceId, talkMetaData })

			if (uploadId) {
				markFileAsShared({ uploadId, index })
			}
		} catch (error) {
			console.error('Error while sharing file: ', error)

			if (isAxiosErrorResponse(error) && error.response?.status === 403) {
				showError(t('spreed', 'You are not allowed to share files'))
			} else if (isAxiosErrorResponse(error) && error.response?.data?.ocs?.meta?.message) {
				showError(error.response.data.ocs.meta.message)
			} else {
				showError(t('spreed', 'Error while sharing file'))
			}

			if (uploadId) {
				vuexStore.dispatch('markTemporaryMessageAsFailed', { token, id, uploadId, reason: 'failed-share' })
			}
		}
	}

	/**
	 * Re-initialize failed uploads and open UploadEditor dialog
	 * Insert caption if was provided
	 *
	 * @param payload payload;
	 * @param payload.token the conversation token;
	 * @param payload.uploadId unique identifier
	 * @param [payload.caption] the message caption;
	 */
	function retryUploadFiles({ token, uploadId, caption }: { token: string, uploadId: string, caption?: string }) {
		getFailedUploads(uploadId).forEach(([index, file]) => {
			vuexStore.dispatch('removeTemporaryMessageFromStore', { token, id: file.temporaryMessage.id })
			markFileAsInitializedUpload({ uploadId, index })
		})

		if (caption) {
			chatExtrasStore.setChatInput({ token, text: caption })
		}

		currentUploadId.value = uploadId
	}

	return {
		uploads,
		currentUploadId,
		localUrls,

		getUploadsArray,
		getInitialisedUploads,
		getPendingUploads,
		getFailedUploads,
		getUploadingFiles,
		getShareableFiles,
		getLocalUrl,
		getUploadFile,

		addFileToBeUploaded,
		markFileAsInitializedUpload,
		markFileAsPendingUpload,
		markFileAsFailedUpload,
		markFileAsSuccessUpload,
		markFileAsUploading,
		markFileAsSharing,
		markFileAsShared,
		removeFileFromSelection,

		initialiseUpload,
		discardUpload,
		uploadFiles,
		prepareUploadPaths,
		processUpload,
		shareFiles,
		shareFile,
		retryUploadFiles,
	}
})
