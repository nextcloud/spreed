/**
 * @copyright Copyright (c) 2022 Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @author Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

// Returns the last n shared items for each category and for a given conversation
// (n = limit)
const getSharedItemsOverview = async function(token, limit) {
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
const getSharedItems = async function(token, objectType, lastKnownMessageId, limit) {
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
