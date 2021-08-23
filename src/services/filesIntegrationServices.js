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

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Gets the conversation token for a given file id
 *
 * @param {object} .fileId the id of the file
 * @param options.fileId
 * @param {object} options unused
 * @return {string} the conversation token
 */
const getFileConversation = async function({ fileId }, options) {
	const response = await axios.get(generateOcsUrl('apps/spreed/api/v1/file/{fileId}', { fileId }))
	return response
}

/**
 * Gets the public share conversation token for a given share token.
 *
 * @param {string} shareToken the token of the share
 * @return {string} the conversation token
 * @throws {Exception} if the conversation token could not be got
 */
const getPublicShareConversationData = async function(shareToken) {
	const response = await axios.get(generateOcsUrl('apps/spreed/api/v1/publicshare/{shareToken}', { shareToken }))
	return response.data.ocs.data
}

export {
	getFileConversation,
	getPublicShareConversationData,
}
