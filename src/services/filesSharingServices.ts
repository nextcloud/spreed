/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import { SHARE } from '../constants.ts'
import type {
	createFileFromTemplateParams,
	createFileFromTemplateResponse,
	createFileShareParams,
	createFileShareResponse,
	getFileTemplatesListResponse,
} from '../types/index.ts'

/**
 * Appends a file as a message to the messages list
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

export {
	shareFile,
	getFileTemplates,
	createNewFile,
}
