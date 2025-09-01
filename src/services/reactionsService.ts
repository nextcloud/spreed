/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { AxiosRequestConfig } from '@nextcloud/axios'
import type {
	addReactionParams,
	addReactionResponse,
	deleteReactionParams,
	deleteReactionResponse,
	getReactionsResponse,
} from '../types/index.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 *
 */
async function addReactionToMessage(token: string, messageId: number, selectedEmoji: addReactionParams['reaction'], options?: AxiosRequestConfig): addReactionResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/reaction/{token}/{messageId}', { token, messageId }), {
		reaction: selectedEmoji,
	} as addReactionParams, options)
}

/**
 *
 */
async function removeReactionFromMessage(token: string, messageId: number, selectedEmoji: deleteReactionParams['reaction'], options?: AxiosRequestConfig): deleteReactionResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/reaction/{token}/{messageId}', { token, messageId }), {
		...options,
		params: {
			reaction: selectedEmoji,
		} as deleteReactionParams,
	})
}

/**
 *
 */
async function getReactionsDetails(token: string, messageId: number, options?: AxiosRequestConfig): getReactionsResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/reaction/{token}/{messageId}', { token, messageId }), options)
}

export { addReactionToMessage, getReactionsDetails, removeReactionFromMessage }
