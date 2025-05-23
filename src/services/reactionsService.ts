/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import type {
	addReactionParams,
	addReactionResponse,
	deleteReactionParams,
	deleteReactionResponse,
	getReactionsResponse,
} from '../types/index.ts'

const addReactionToMessage = async function(token: string, messageId: number, selectedEmoji: addReactionParams['reaction'], options: object): addReactionResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/reaction/{token}/{messageId}', {
		token,
		messageId,
	}, options), {
		reaction: selectedEmoji,
	} as addReactionParams, options)
}

const removeReactionFromMessage = async function(token: string, messageId: number, selectedEmoji: deleteReactionParams['reaction'], options: object): deleteReactionResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/reaction/{token}/{messageId}', {
		token,
		messageId,
	}, options), {
		...options,
		params: {
			reaction: selectedEmoji,
		} as deleteReactionParams,
	})
}

const getReactionsDetails = async function(token: string, messageId: number, options: object): getReactionsResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/reaction/{token}/{messageId}', {
		token,
		messageId,
	}, options), options)
}

export { addReactionToMessage, removeReactionFromMessage, getReactionsDetails }
