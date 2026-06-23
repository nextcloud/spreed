import type {
	createFileFromTemplateParams,
	createFileFromTemplateResponse,
	createFileShareParams,
	createFileShareResponse,
	getFileTemplatesListResponse,
	PostAttachmentFolderParams,
	PostAttachmentFolderResponse,
	ProbeAttachmentFolderParams,
	ProbeAttachmentFolderResponse,
} from '../types/index.ts'

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { SHARE } from '../constants.ts'

/**
 * Appends a file as a message to the messages list
 *
 * @param payload The function payload
 * @param payload.path The file path from the user's root directory
 * @param payload.shareWith The conversation's token
 * @param payload.referenceId A reference id to recognize the message later
 * @param payload.talkMetaData The metadata JSON-encoded object
 */
async function shareFile({ path, shareWith, referenceId, talkMetaData }: createFileShareParams): createFileShareResponse {
	return axios.post(generateOcsUrl('apps/files_sharing/api/v1/shares'), {
		shareType: SHARE.TYPE.ROOM,
		path,
		shareWith,
		referenceId,
		talkMetaData,
	} as createFileShareParams)
}

/**
 * List the available file templates to create per app
 */
async function getFileTemplates(): getFileTemplatesListResponse {
	return axios.get(generateOcsUrl('apps/files/api/v1/templates'))
}

/**
 * Create a new file from the template
 *
 * @param payload Function payload
 * @param payload.filePath Path of the new file
 * @param payload.templatePath Source path of the template file
 * @param payload.templateType Type of the template (e.g 'user')
 */
async function createNewFile({ filePath, templatePath, templateType }: createFileFromTemplateParams): createFileFromTemplateResponse {
	return axios.post(generateOcsUrl('apps/files/api/v1/templates/create'), {
		filePath,
		templatePath,
		templateType,
	} as createFileFromTemplateParams)
}

/**
 * Probe the conversation attachment folder for the given conversation.
 *
 * Creates the caller's conversation subfolder hierarchy (and the folder-level
 * TYPE_ROOM share that grants all room members access) server-side if not yet
 * present, and returns the path of the Draft staging folder where files must
 * be uploaded before being posted via {@link postAttachment}.
 *
 * @param payload The function payload
 * @param payload.token The conversation token
 * @param payload.fileNames Desired file names — used only for server-side
 *        rename-on-conflict probing; the authoritative final names are
 *        returned by {@link postAttachment}.
 * @return Draft folder path (relative to user home root, no leading slash)
 *         and a rename simulation for the requested file names.
 */
async function probeAttachmentFolder({ token, fileNames }: { token: string } & ProbeAttachmentFolderParams): ProbeAttachmentFolderResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/chat/{token}/attachment/folder', { token }), { fileNames })
}

/**
 * Post a file staged in the conversation Draft folder as a chat message.
 *
 * Unlike {@link shareFile}, this does not create a per-file TYPE_ROOM share —
 * access is controlled by the folder-level share created by
 * {@link probeAttachmentFolder}. The backend moves the file from Draft into
 * the shared conversation subfolder, resolving name conflicts by appending
 * " (1)", " (2)", … to the desired file name.
 *
 * @param payload The function payload
 * @param payload.token The conversation token
 * @param payload.filePath Draft file path relative to the user's home root
 *        (must be inside the Draft folder returned by probeAttachmentFolder)
 * @param payload.fileName Desired final file name (for rename-on-conflict)
 * @param payload.referenceId Client reference ID for the chat message
 * @param payload.talkMetaData JSON-encoded metadata (caption, messageType, silent, …)
 * @return An array of `{ originalName: finalName }` entries — one per posted
 *         file.  When the backend had to rename due to a conflict the two
 *         names differ; otherwise they are identical.
 */
async function postAttachment({ token, filePath, fileName, referenceId, talkMetaData }: { token: string } & PostAttachmentFolderParams): PostAttachmentFolderResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/chat/{token}/attachment', { token }), {
		filePath,
		fileName,
		referenceId,
		talkMetaData,
	})
}

export {
	createNewFile,
	getFileTemplates,
	postAttachment,
	probeAttachmentFolder,
	shareFile,
}
