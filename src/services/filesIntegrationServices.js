/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Gets the conversation token for a given file id
 *
 * @param {object} data the wrapping object;
 * @param {number} data.fileId The file id to get the conversation for
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
 */
const getPublicShareConversationData = async function(shareToken) {
	const response = await axios.get(generateOcsUrl('apps/spreed/api/v1/publicshare/{shareToken}', { shareToken }))
	return response.data.ocs.data
}

export {
	getFileConversation,
	getPublicShareConversationData,
}
