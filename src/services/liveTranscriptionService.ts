/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	liveTranscriptionDisableResponse,
	liveTranscriptionEnableResponse,
	liveTranscriptionGetAvailableLanguagesResponse,
	liveTranscriptionSetLanguageResponse,
} from '../types/index.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Enable live transcription
 *
 * @param {string} token conversation token
 */
async function enableLiveTranscription(token: string): liveTranscriptionEnableResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/live-transcription/{token}', { token }))
}

/**
 * Disable live transcription
 *
 * @param {string} token conversation token
 */
async function disableLiveTranscription(token: string): liveTranscriptionDisableResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/live-transcription/{token}', { token }))
}

/**
 * Get available languages for live transcriptions
 */
async function getLiveTranscriptionLanguages(): liveTranscriptionGetAvailableLanguagesResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/live-transcription/languages'))
}

/**
 * Set language for live transcription
 *
 * @param {string} token conversation token
 * @param {string} languageId the ID of the language
 */
async function setLiveTranscriptionLanguage(token: string, languageId: string): liveTranscriptionSetLanguageResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/live-transcription/{token}/language', { token }), {
		languageId,
	})
}

export {
	disableLiveTranscription,
	enableLiveTranscription,
	getLiveTranscriptionLanguages,
	setLiveTranscriptionLanguage,
}
