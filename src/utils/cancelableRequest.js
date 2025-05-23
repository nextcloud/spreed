/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'

/**
 * Creates an axios 'cancelable request object'.
 *
 * @param {Function} request the axios promise request
 * @return {object} The cancelable requests
 * `object.request`: the api request funtion with the cancel token associated to it.
 * `object.cancel`: the cancel function, when call it's going to delete the request.
 */
const CancelableRequest = function(request) {
	/**
	 * Generate an axios cancel token
	 */
	const CancelToken = axios.CancelToken
	const source = CancelToken.source()

	/**
	 * Execute the request
	 *
	 * @param {string} data the data to send the request to
	 * @param {object} [options] optional config for the request
	 * @return { object }
	 */
	const fetch = async function(data, options) {
		return request(
			data,
			Object.assign({ cancelToken: source.token }, options),
		)
	}
	return {
		request: fetch,
		cancel: source.cancel,
	}
}

// expose function to check if an exception is from a cancellation
CancelableRequest.isCancel = axios.isCancel

export default CancelableRequest
