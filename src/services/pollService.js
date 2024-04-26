/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

const pollService = {
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
	async postNewPoll(token, question, options, resultMode, maxVotes) {
		return axios.post(generateOcsUrl('apps/spreed/api/v1/poll/{token}', { token }), {
			question,
			options,
			resultMode,
			maxVotes,
		})
	},

	/**
	 *
	 * @param {string} token The conversation token
	 * @param {number} pollId ID of the poll
	 * @return {object} The poll object
	 */
	async getPollData(token, pollId) {
		return axios.get(generateOcsUrl('apps/spreed/api/v1/poll/{token}/{pollId}', { token, pollId }))
	},

	/**
	 * Submit poll vote
	 *
	 * @param {string} token  The conversation token
	 * @param {number} pollId ID of the poll
	 * @param {Array} optionIds The option IDs the participant wants to vote for
	 * @return {object} The poll object
	 */
	async submitVote(token, pollId, optionIds) {
		return axios.post(generateOcsUrl('apps/spreed/api/v1/poll/{token}/{pollId}', { token, pollId }), {
			optionIds,
		})
	},

	/**
	 * Ends the poll
	 *
	 * @param {string} token The conversation token
	 * @param {number} pollId ID of the poll
	 * @return {object} The poll object
	 */
	async endPoll(token, pollId) {
		return axios.delete(generateOcsUrl('apps/spreed/api/v1/poll/{token}/{pollId}', { token, pollId }))
	},
}

export default pollService
