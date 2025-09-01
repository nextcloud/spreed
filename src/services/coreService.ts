/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { AxiosRequestConfig } from '@nextcloud/axios'
import type {
	AutocompleteParams,
	AutocompleteResponse,
	SearchMessagePayload,
	TaskProcessingResponse,
	UnifiedSearchResponse,
	UserProfileResponse,
} from '../types/index.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { SHARE } from '../constants.ts'
import { getTalkConfig, hasTalkFeature } from './CapabilitiesManager.ts'

const canInviteToFederation = hasTalkFeature('local', 'federation-v1')
	&& getTalkConfig('local', 'federation', 'enabled')
	&& getTalkConfig('local', 'federation', 'outgoing-enabled')

// Only explicit share types are allowed to use in autocompleteQuery
type ShareType = typeof SHARE.TYPE.USER
	| typeof SHARE.TYPE.GROUP
	| typeof SHARE.TYPE.EMAIL
	| typeof SHARE.TYPE.REMOTE
	| typeof SHARE.TYPE.CIRCLE
type SearchPayload = {
	searchText: string
	token?: string | 'new'
	onlyUsers?: boolean
	forceTypes?: ShareType[]
}

/**
 * Fetch possible conversations
 *
 * @param payload the wrapping object;
 * @param payload.searchText The string that will be used in the search query.
 * @param [payload.token] The token of the conversation (if any) | 'new' for new conversations
 * @param [payload.onlyUsers] Whether to return only registered users
 * @param [payload.forceTypes] Whether to force some types to be included in query
 * @param [options] Axios request options
 */
async function autocompleteQuery({
	searchText,
	token = 'new',
	onlyUsers = false,
	forceTypes = [],
}: SearchPayload, options?: AxiosRequestConfig): AutocompleteResponse {
	const shareTypes: ShareType[] = onlyUsers
		? [SHARE.TYPE.USER]
		: [
				SHARE.TYPE.USER,
				SHARE.TYPE.GROUP,
				SHARE.TYPE.CIRCLE,
				...(token !== 'new' ? [SHARE.TYPE.EMAIL] : []),
				...(canInviteToFederation ? [SHARE.TYPE.REMOTE] : []),
			]

	return axios.get(generateOcsUrl('core/autocomplete/get'), {
		...options,
		params: {
			search: searchText,
			itemType: 'call',
			itemId: token,
			shareTypes: shareTypes.concat(forceTypes),
		} as AutocompleteParams,
	})
}

/**
 *
 * @param userId
 * @param options
 */
async function getUserProfile(userId: string, options?: AxiosRequestConfig): UserProfileResponse {
	return axios.get(generateOcsUrl('profile/{userId}', { userId }), options)
}

/**
 *
 * @param id
 * @param options
 */
async function getTaskById(id: number, options?: AxiosRequestConfig): TaskProcessingResponse {
	return axios.get(generateOcsUrl('taskprocessing/task/{id}', { id }), options)
}

/**
 *
 * @param id
 * @param options
 */
async function deleteTaskById(id: number, options?: AxiosRequestConfig): Promise<null> {
	return axios.delete(generateOcsUrl('taskprocessing/task/{id}', { id }), options)
}

/**
 *
 * @param params
 * @param options
 */
async function searchMessages(params: SearchMessagePayload, options?: AxiosRequestConfig): UnifiedSearchResponse {
	return axios.get(generateOcsUrl('search/providers/talk-message-current/search'), {
		...options,
		params,
	})
}

export {
	autocompleteQuery,
	deleteTaskById,
	getTaskById,
	getUserProfile,
	searchMessages,
}
