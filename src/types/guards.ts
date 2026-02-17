/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { AxiosError } from '@nextcloud/axios'
import type { components } from './openapi/openapi.ts'

export type ApiErrorResponse<T = null> = AxiosError<{
	ocs: {
		meta: components['schemas']['OCSMeta']
		data: T
	}
}>

/**
 * Check whether caught error is from OCS API
 *
 * @param exception - exception (from catch block) to be verified.
 * Expected to be an AxiosError with OCS response structure, data property is optional and can vary (usually 'null')
 */
export function isAxiosErrorResponse<T = null>(exception: unknown): exception is ApiErrorResponse<T> {
	return exception !== null && typeof exception === 'object' && 'response' in exception
}
