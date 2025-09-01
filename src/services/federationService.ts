/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { AxiosRequestConfig } from '@nextcloud/axios'
import type { acceptShareResponse, getCapabilitiesResponse, getSharesResponse, rejectShareResponse } from '../types/index.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Fetches list of shares for a current user
 *
 * @param [options] Axios request options
 */
async function getShares(options?: AxiosRequestConfig): getSharesResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/federation/invitation'), options)
}

/**
 * Accept an invitation by provided id.
 *
 * @param id invitation id;
 * @param [options] Axios request options
 */
async function acceptShare(id: string | number, options?: AxiosRequestConfig): acceptShareResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/federation/invitation/{id}', { id }), {}, options)
}

/**
 * Reject an invitation by provided id.
 *
 * @param id invitation id;
 * @param [options] Axios request options
 */
async function rejectShare(id: string | number, options?: AxiosRequestConfig): rejectShareResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/federation/invitation/{id}', { id }), options)
}

/**
 * Fetches capabilities of remote server by local conversation token
 *
 * @param token local conversation token;
 * @param [options] Axios request options
 */
async function getRemoteCapabilities(token: string, options?: AxiosRequestConfig): getCapabilitiesResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v4/room/{token}/capabilities', { token }), options)
}

export {
	acceptShare,
	getRemoteCapabilities,
	getShares,
	rejectShare,
}
