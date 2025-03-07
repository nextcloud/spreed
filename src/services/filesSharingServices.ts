/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import type {
	createFileFromTemplateParams,
	createFileFromTemplateResponse,
	createFileShareParams,
	createFileShareResponse,
	getFileTemplatesListResponse,
} from '../types/index.ts'

/**
 * Appends a file as a message to the messagelist.
 *
 * @param {string} path The file path from the user's root directory
 * @param {string} token The conversation's token
 * e.g. `/myfile.txt`
 * @param {string} referenceId An optional reference id to recognize the message later
 * @param {string} metadata the metadata json encoded array
 */
const shareFile = async function(path, token, referenceId, metadata): createFileShareResponse {
	try {
		return await axios.post(
			generateOcsUrl('apps/files_sharing/api/v1/shares'),
			{
				shareType: 10, // OC.Share.SHARE_TYPE_ROOM,
				path,
				shareWith: token,
				referenceId,
				talkMetaData: metadata,
			} as createFileShareParams)
	} catch (error) {
		// FIXME: errors should be handled by called instead
		if (error?.response?.data?.ocs?.meta?.message) {
			console.error('Error while sharing file: ' + error.response.data.ocs.meta.message)
			showError(error.response.data.ocs.meta.message)
		} else {
			console.error('Error while sharing file: Unknown error')
			showError(t('spreed', 'Error while sharing file'))
		}
	}
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
