/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	getSharedItemsOverviewParams,
	getSharedItemsOverviewResponse,
	getSharedItemsParams,
	getSharedItemsResponse,
} from '../types/index.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Returns the last N (N = limit param) shared items for each category and for a given conversation
 *
 * @param payload
 * @param payload.token
 * @param payload.limit
 */
async function getSharedItemsOverview({ token, limit }: { token: string } & getSharedItemsOverviewParams): getSharedItemsOverviewResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/chat/{token}/share/overview', {
		token,
	}), {
		params: {
			limit,
		},
	})
}

// Returns the last 200 (or limit) shared items, given a conversation and the type
// of shared item
/**
 *
 * @param payload
 * @param payload.token
 * @param payload.objectType
 * @param payload.lastKnownMessageId
 * @param payload.limit
 */
async function getSharedItems({ token, objectType, lastKnownMessageId, limit }: { token: string } & getSharedItemsParams): getSharedItemsResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/chat/{token}/share', {
		token,
	}), {
		params: {
			limit,
			objectType,
			lastKnownMessageId,
		},
	})
}

export {
	getSharedItems,
	getSharedItemsOverview,
}
