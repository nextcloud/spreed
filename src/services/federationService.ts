/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import type { acceptShareResponse, getSharesResponse, rejectShareResponse } from '../types'

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
const acceptShare = async function(id: number, options?: object): acceptShareResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/federation/invitation/{id}', { id }, options), {}, options)
}

/**
 * Reject an invitation by provided id.
 *
 * @param id invitation id;
 * @param [options] options;
 */
const rejectShare = async function(id: number, options?: object): rejectShareResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/federation/invitation/{id}', { id }, options), options)
}

export {
	getShares,
	acceptShare,
	rejectShare,
}
