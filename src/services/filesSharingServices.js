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
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Appends a file as a message to the message list.
 *
 * @param {string} path The file path from the user's root directory
 * @param {string} token The conversation's token
 * e.g. `/myfile.txt`
 * @param {string} referenceId An optional reference id to recognize the message later
 * @param {string} metadata the metadata json encoded array
 */
const shareFile = async function(path, token, referenceId, metadata) {
	try {
		return await axios.post(
			generateOcsUrl('apps/files_sharing/api/v1/shares'),
			{
				shareType: 10, // OC.Share.SHARE_TYPE_ROOM,
				path,
				shareWith: token,
				referenceId,
				talkMetaData: metadata,
			})
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
 * Appends a multiple files as a message to the message list.
 *
 * @param {string} token The conversation's token
 * @param {Array<string>} shareIds a list of ids we're getting in shareFile() response
 * @param {string} caption a text message attached to the files
 * @param {string} actorDisplayName The display name of the actor
 * @param {string} referenceId An optional reference id to recognize the message later
 */
const shareMultipleFiles = async function(token, shareIds, caption, actorDisplayName, referenceId) {
	try {
		return axios.post(generateOcsUrl('apps/spreed/api/v1/chat/{token}/share-files', { token }),
			{
				shareIds,
				caption,
				actorDisplayName,
				referenceId,
			})
	} catch (error) {
		// FIXME: errors should be handled by called instead
		if (error?.response?.data?.ocs?.meta?.message) {
			console.error('Error while sharing files: ' + error.response.data.ocs.meta.message)
			showError(error.response.data.ocs.meta.message)
		} else {
			console.error('Error while sharing files: Unknown error')
			showError(t('spreed', 'Error while sharing files'))
		}
	}
}

const getFileTemplates = async () => {
	return await axios.get(generateOcsUrl('apps/files/api/v1/templates'))
}

/**
 * Share a text file to a conversation
 *
 * @param {string} filePath The new file destination path
 * @param {string} [templatePath] The template source path
 * @param {string} [templateType] The template type e.g 'user'
 * @return { object } the file object
 */
const createNewFile = async function(filePath, templatePath, templateType) {
	return await axios.post(generateOcsUrl('apps/files/api/v1/templates/create'), {
		filePath,
		templatePath,
		templateType,
	})
}

export {
	shareFile,
	shareMultipleFiles,
	getFileTemplates,
	createNewFile,
}
