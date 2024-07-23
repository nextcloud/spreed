/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import type {
	getBansResponse,
	banActorParams,
	banActorResponse,
	unbanActorResponse,
} from '../types'

/**
 * Get information about configured bans for this conversation
 *
 * @param token - the conversation token
 * @param [options] - request options
 */
const getConversationBans = async function(token: string, options?: object): getBansResponse {
	return axios.get(generateOcsUrl('/apps/spreed/api/v1/ban/{token}', { token }, options), options)
}

/**
 * Ban actor with specified internal note for this conversation
 *
 * @param token - the conversation token
 * @param payload - banned actor information
 * @param [options] - request options
 */
const banActor = async function(token: string, payload: banActorParams, options?: object): banActorResponse {
	return axios.post(generateOcsUrl('/apps/spreed/api/v1/ban/{token}', { token }, options), payload, options)
}

/**
 * Ban actor with specified internal note for this conversation
 *
 * @param token - the conversation token
 * @param banId - ban id
 * @param [options] - request options
 */
const unbanActor = async function(token: string, banId: number, options?: object): unbanActorResponse {
	return axios.delete(generateOcsUrl('/apps/spreed/api/v1/ban/{token}/{banId}', { token, banId }, options), options)
}

export {
	getConversationBans,
	banActor,
	unbanActor,
}
