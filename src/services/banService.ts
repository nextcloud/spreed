/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { AxiosRequestConfig } from '@nextcloud/axios'
import type {
	banActorParams,
	banActorResponse,
	getBansResponse,
	unbanActorResponse,
} from '../types/index.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Get information about configured bans for this conversation
 *
 * @param token - the conversation token
 * @param [options] - Axios request options
 */
async function getConversationBans(token: string, options?: AxiosRequestConfig): getBansResponse {
	return axios.get(generateOcsUrl('/apps/spreed/api/v1/ban/{token}', { token }), options)
}

/**
 * Ban actor with specified internal note for this conversation
 *
 * @param token - the conversation token
 * @param payload - banned actor information
 * @param [options] - Axios request options
 */
async function banActor(token: string, payload: banActorParams, options?: AxiosRequestConfig): banActorResponse {
	return axios.post(generateOcsUrl('/apps/spreed/api/v1/ban/{token}', { token }), payload, options)
}

/**
 * Ban actor with specified internal note for this conversation
 *
 * @param token - the conversation token
 * @param banId - ban id
 * @param [options] - Axios request options
 */
async function unbanActor(token: string, banId: number, options?: AxiosRequestConfig): unbanActorResponse {
	return axios.delete(generateOcsUrl('/apps/spreed/api/v1/ban/{token}/{banId}', { token, banId }), options)
}

export {
	banActor,
	getConversationBans,
	unbanActor,
}
