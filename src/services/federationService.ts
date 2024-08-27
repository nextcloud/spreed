/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import type { acceptShareResponse, getSharesResponse, rejectShareResponse, getCapabilitiesResponse } from '../types'

/**
 * Fetches list of shares for a current user
 *
 * @param [options] options;
 */
const getShares = async function(options?: object): getSharesResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/federation/invitation', undefined, options), options)
}

/**
 * Accept an invitation by provided id.
 *
 * @param id invitation id;
 * @param [options] options;
 */
const acceptShare = async function(id: string | number, options?: object): acceptShareResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/federation/invitation/{id}', { id }, options), {}, options)
}

/**
 * Reject an invitation by provided id.
 *
 * @param id invitation id;
 * @param [options] options;
 */
const rejectShare = async function(id: string | number, options?: object): rejectShareResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/federation/invitation/{id}', { id }, options), options)
}

/**
 * Fetches capabilities of remote server by local conversation token
 *
 * @param token local conversation token;
 * @param [options] options;
 */
const getRemoteCapabilities = async function(token: string, options?: object): getCapabilitiesResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v4/room/{token}/capabilities', { token }, options), options)
}

export {
	getShares,
	acceptShare,
	rejectShare,
	getRemoteCapabilities,
}
