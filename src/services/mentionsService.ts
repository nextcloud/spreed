/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { getMentionsParams, getMentionsResponse } from '../types/index.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Fetch possible mentions
 *
 * @param token The token of the conversation.
 * @param search The string that will be used in the search query.
 */
async function searchPossibleMentions(token: string, search: string): getMentionsResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/chat/{token}/mentions', { token }), {
		params: {
			search,
			includeStatus: 1,
		} as getMentionsParams,
	})
}

export {
	searchPossibleMentions,
}
