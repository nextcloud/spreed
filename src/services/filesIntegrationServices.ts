/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	getRoomDataByFileIdResponse,
	getRoomDataByShareTokenResponse,
} from '../types/index.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Gets the conversation token for a given file id
 *
 * @param fileId The file id to get the conversation for
 * @param options options
 */
const getFileConversation = async function(fileId: number, options?: object): getRoomDataByFileIdResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/file/{fileId}', { fileId }), options)
}

/**
 * Gets the public share conversation token for a given share token.
 *
 * @param shareToken the token of the share
 * @param options options
 */
const getPublicShareConversationData = async function(shareToken: string, options?: object): getRoomDataByShareTokenResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/publicshare/{shareToken}', { shareToken }), options)
}

export {
	getFileConversation,
	getPublicShareConversationData,
}
