/**
 * @copyright Copyright (c) 2024 Maksim Sukharev <antreesy.web@gmail.com>
 *
 * @author Marco Ambrosini <marcoambrosini@icloud.com>
 * @author Maksim Sukharev <antreesy.web@gmail.com>
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

const addReactionToMessage = async function(token, messageId, selectedEmoji, options) {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/reaction/{token}/{messageId}', {
		token,
		messageId
	}, options), {
		reaction: selectedEmoji,
	}, options)
}

const removeReactionFromMessage = async function(token, messageId, selectedEmoji, options) {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/reaction/{token}/{messageId}', {
		token,
		messageId
	}, options), {
		...options,
		params: {
			reaction: selectedEmoji,
		},
	})
}

const getReactionsDetails = async function(token, messageId, options) {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/reaction/{token}/{messageId}', {
		token,
		messageId
	}, options), options)
}

export { addReactionToMessage, removeReactionFromMessage, getReactionsDetails }
