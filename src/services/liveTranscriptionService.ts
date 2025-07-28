/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	liveTranscriptionDisableResponse,
	liveTranscriptionEnableResponse,
} from '../types/index.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Enable live transcription
 *
 * @param {string} token conversation token
 */
const enableLiveTranscription = async function(token: string): Promise<liveTranscriptionEnableResponse> {
	return await axios.post(generateOcsUrl('apps/spreed/api/v1/live-transcription/{token}', { token }))
}

/**
 * Disable live transcription
 *
 * @param {string} token conversation token
 */
const disableLiveTranscription = async function(token: string): Promise<liveTranscriptionDisableResponse> {
	return await axios.delete(generateOcsUrl('apps/spreed/api/v1/live-transcription/{token}', { token }))
}

export {
	disableLiveTranscription,
	enableLiveTranscription,
}
