/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Fetch possible mentions
 *
 * @param {string} token The token of the conversation.
 * @param {string} searchText The string that will be used in the search query.
 */
const searchPossibleMentions = async function(token, searchText) {
	try {
		const response = await axios.get(generateOcsUrl('apps/spreed/api/v1/chat/{token}/mentions', { token }), {
			params: {
				search: searchText,
				includeStatus: 1,
			},
		})
		return response
	} catch (error) {
		console.debug('Error while searching possible mentions: ', error)
	}
}

export {
	searchPossibleMentions,
}
