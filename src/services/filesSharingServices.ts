import type {
	createFileFromTemplateParams,
	createFileFromTemplateResponse,
	createFileShareParams,
	createFileShareResponse,
	getFileTemplatesListResponse,
} from '../types/index.ts'

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { SHARE } from '../constants.ts'

type PostAttachmentParams = {
	token: string
	filePath: string
	fileName: string
	referenceId: string
	talkMetaData: string
}

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
 * Post a file from a conversation attachment subfolder as a chat message.
 *
 * Unlike shareFile(), this does not create a per-file TYPE_ROOM share.
 * Access is controlled by the folder-level share that was automatically
 * created when the conversation subfolder was first created via WebDAV MKCOL.
 *
 * @param payload The function payload
 * @param payload.token The conversation token
 * @param payload.filePath File path relative to the user's home root
 * @param payload.fileName Desired final file name (used for server-side rename-on-conflict)
 * @param payload.referenceId Client reference ID for the chat message
 * @param payload.talkMetaData JSON-encoded metadata (caption, messageType, silent, …)
 * @return An array of `{ originalName: finalName }` entries — one per posted
 *         file.  When the backend had to rename due to a conflict the two
 *         names differ; otherwise they are identical.
 */
async function postAttachment({ token, filePath, fileName, referenceId, talkMetaData }: PostAttachmentParams): Promise<Record<string, string>[]> {
	const response = await axios.post<{ ocs: { data: { renames: Record<string, string>[] } } }>(
		generateOcsUrl('apps/spreed/api/v1/chat/{token}/attachment', { token }),
		{
			filePath,
			fileName,
			referenceId,
			talkMetaData,
		},
	)
	return response.data?.ocs?.data?.renames ?? []
}

export {
	createNewFile,
	getFileTemplates,
	postAttachment,
	shareFile,
}
