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

// For API documentation see https://nextcloud-talk.readthedocs.io/en/latest/poll/

/**
 *
 * @param {string} token The conversation token
 * @param {string} question The question of the polln
 * @param {Array} options The options participants can vote for
 * @param {number} resultMode Result mode of the poll
 * @param {number} maxVotes Maximum amount of options a user can vote for, 0 means unlimited
 * @return {object} The poll object
 */
const postNewPoll = async function(token, question, options, resultMode, maxVotes) {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/poll/{token}', { token }), {
		question,
		options,
		resultMode,
		maxVotes,
	})
}

/**
 *
 * @param {string} token The conversation token
 * @param {number} pollId ID of the poll
 * @return {object} The poll object
 */
const getPollData = async function(token, pollId) {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/poll/{token}/{pollId}', { token, pollId }))
}

export {
	postNewPoll,
	getPollData,
}
