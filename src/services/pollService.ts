/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	closePollResponse,
	createPollDraftResponse,
	createPollParams,
	createPollResponse,
	deletePollDraftResponse,
	getPollDraftsResponse,
	getPollResponse,
	updatePollDraftParams,
	updatePollDraftResponse,
	votePollParams,
	votePollResponse,
} from '../types/index.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

type createPollPayload = { token: string } & createPollParams
type updatePollDraftPayload = { token: string, pollId: number } & updatePollDraftParams

/**
 * @param payload The payload
 * @param payload.token The conversation token
 * @param payload.question The question of the poll
 * @param payload.options The options participants can vote for
 * @param payload.resultMode Result mode of the poll (0 - always visible | 1 - hidden until the poll is closed)
 * @param payload.maxVotes Maximum amount of options a user can vote for (0 - unlimited | 1 - single answer)
 */
async function createPoll({ token, question, options, resultMode, maxVotes, threadId }: createPollPayload): createPollResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/poll/{token}', { token }), {
		question,
		options,
		resultMode,
		maxVotes,
		draft: false,
		threadId,
	} as createPollParams)
}

/**
 * @param payload The payload
 * @param payload.token The conversation token
 * @param payload.question The question of the poll
 * @param payload.options The options participants can vote for
 * @param payload.resultMode Result mode of the poll (0 - always visible | 1 - hidden until the poll is closed)
 * @param payload.maxVotes Maximum amount of options a user can vote for (0 - unlimited | 1 - single answer)
 */
async function createPollDraft({ token, question, options, resultMode, maxVotes }: createPollPayload): createPollDraftResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/poll/{token}', { token }), {
		question,
		options,
		resultMode,
		maxVotes,
		draft: true,
	} as createPollParams)
}

/**
 * @param payload The payload
 * @param payload.token The conversation token
 * @param payload.pollId The id of poll draft
 * @param payload.question The question of the poll
 * @param payload.options The options participants can vote for
 * @param payload.resultMode Result mode of the poll (0 - always visible | 1 - hidden until the poll is closed)
 * @param payload.maxVotes Maximum amount of options a user can vote for (0 - unlimited | 1 - single answer)
 */
async function updatePollDraft({ token, pollId, question, options, resultMode, maxVotes }: updatePollDraftPayload): updatePollDraftResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/poll/{token}/draft/{pollId}', { token, pollId }), {
		question,
		options,
		resultMode,
		maxVotes,
	} as updatePollDraftParams)
}

/**
 * @param token The conversation token
 */
async function getPollDrafts(token: string): getPollDraftsResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/poll/{token}/drafts', { token }))
}

/**
 * @param token The conversation token
 * @param pollId Id of the poll
 */
async function getPollData(token: string, pollId: string): getPollResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/poll/{token}/{pollId}', { token, pollId }))
}

/**
 * @param token The conversation token
 * @param pollId Id of the poll
 * @param optionIds Indexes of options the participant votes for
 */
async function submitVote(token: string, pollId: string, optionIds: votePollParams['optionIds']): votePollResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/poll/{token}/{pollId}', { token, pollId }), {
		optionIds,
	} as votePollParams)
}

/**
 * @param token The conversation token
 * @param pollId Id of the poll
 */
async function endPoll(token: string, pollId: string): closePollResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/poll/{token}/{pollId}', { token, pollId }))
}
/**
 * @param token The conversation token
 * @param pollId Id of the poll draft
 */
async function deletePollDraft(token: string, pollId: string): deletePollDraftResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/poll/{token}/{pollId}', { token, pollId }))
}

export {
	createPoll,
	createPollDraft,
	deletePollDraft,
	endPoll,
	getPollData,
	getPollDrafts,
	submitVote,
	updatePollDraft,
}
