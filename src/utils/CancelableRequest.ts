/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { AxiosRequestConfig } from '@nextcloud/axios'

import axios from '@nextcloud/axios'

type Callback<T, R> = (data: T, options?: AxiosRequestConfig) => R

type CancelableRequestReturnType<T, R> = {
	request: Callback<T, R>
	cancel: () => void
	isCancel: (value: unknown) => boolean
}

/**
 * Creates an axios 'cancelable request object'.
 *
 * @param callback - the axios promise request
 * @return the cancelable request
 * - `object.request`: the api request function with the cancel token associated to it,
 * - `object.cancel`: the cancel function, when call it's going to delete the request,
 * - `object.isCancel`: exposed function to check if an exception is from a cancellation.
 */
function CancelableRequest<T, R>(callback: Callback<T, R>): CancelableRequestReturnType<T, R> {
	const controller = new AbortController()
	const cancel = () => controller.abort()

	/**
	 * Return the callback, modified with controller signal
	 *
	 * @param data the data to send the request to
	 * @param [options] optional config for the request
	 */
	const request: Callback<T, R> = function(data: T, options?: AxiosRequestConfig) {
		return callback(data, {
			signal: controller.signal,
			...options,
		})
	}

	return {
		request,
		cancel,
		isCancel: axios.isCancel,
	}
}

CancelableRequest.isCancel = axios.isCancel

export default CancelableRequest
