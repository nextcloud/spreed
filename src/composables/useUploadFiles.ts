/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { MaybeRefOrGetter } from 'vue'
import type { UploadEntry, UploadFile } from '../types/index.ts'

import { computed, toValue } from 'vue'
import { MESSAGE } from '../constants.ts'
import { getTalkConfig, hasTalkFeature } from '../services/CapabilitiesManager.ts'
import { useUploadStore } from '../stores/upload.ts'
import { supportImageCompression } from '../utils/imageCompression.ts'
import { useGetThreadId } from './useGetThreadId.ts'

/**
 * Composable to initialise and track files staged for an upload.
 *
 * Covers the intake (adding and removing files) and derives the state of the
 * current upload from the store. Sending it is left to the caller, which
 * builds the message around it, see uploadStore.uploadFiles().
 *
 * @param token the conversation token to upload to
 */
export function useUploadFiles(token: MaybeRefOrGetter<string>) {
	const threadId = useGetThreadId()
	const uploadStore = useUploadStore()

	const currentUploadId = computed(() => uploadStore.currentUploadId)

	const files = computed<UploadEntry[]>(() => currentUploadId.value
		? uploadStore.getInitialisedUploads(currentUploadId.value)
		: [])

	const hasFiles = computed(() => files.value.length > 0)

	const firstFile = computed<UploadFile | undefined>(() => files.value.at(0)?.[1])

	const isVoiceMessage = computed(() => {
		return firstFile.value?.temporaryMessage.messageType === MESSAGE.TYPE.VOICE_MESSAGE
	})

	const hasImages = computed(() => {
		return files.value.some(([, uploadedFile]) => supportImageCompression(uploadedFile.file.type))
	})

	// TODO not supported in Nextcloud 27 and older, EOL in 06-2024
	const supportMediaCaption = computed(() => hasTalkFeature(toValue(token), 'media-caption'))

	const supportConversationSubfolders = computed(() => {
		return getTalkConfig(toValue(token), 'attachments', 'conversation-subfolders') === true
	})

	/**
	 * Stages files for upload, appending to the current upload if there is one
	 *
	 * @param newFiles the files to stage
	 * @param [options] the wrapping object
	 * @param [options.rename] whether to rename the files (usually after pasting)
	 * @param [options.isVoiceMessage] whether the file is a voice recording
	 */
	function addFiles(newFiles: File[], { rename, isVoiceMessage }: { rename?: boolean, isVoiceMessage?: boolean } = {}) {
		uploadStore.initialiseUpload({
			files: newFiles,
			token: toValue(token),
			threadId: threadId.value,
			// Create a unique id for the upload operation, unless one is ongoing
			uploadId: currentUploadId.value ?? String(new Date().getTime()),
			rename,
			isVoiceMessage,
		})
	}

	/**
	 * Removes a staged file from the current upload, discarding the upload
	 * itself once no files are left attached
	 *
	 * @param temporaryMessageId message id of the temporary message associated to the file
	 */
	function removeFile(temporaryMessageId: number) {
		uploadStore.removeFileFromSelection(temporaryMessageId)

		if (!hasFiles.value && currentUploadId.value) {
			uploadStore.discardUpload(currentUploadId.value)
		}
	}

	return {
		currentUploadId,
		files,
		hasFiles,
		firstFile,
		isVoiceMessage,
		hasImages,
		supportMediaCaption,
		supportConversationSubfolders,

		addFiles,
		removeFile,
	}
}
