/*!
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	liveTranscriptionDisableResponse,
	liveTranscriptionEnableResponse,
	liveTranscriptionGetAvailableLanguagesResponse,
	liveTranscriptionGetAvailableTranslationLanguagesResponse,
	liveTranscriptionGetProvidersResponse,
	liveTranscriptionGetProviderStatusResponse,
	liveTranscriptionSetLanguageResponse,
	liveTranscriptionSetTargetLanguageResponse,
} from '../types/index.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Enable live transcription
 *
 * @param token conversation token
 */
async function enableLiveTranscription(token: string): liveTranscriptionEnableResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/live-transcription/{token}', { token }))
}

/**
 * Disable live transcription
 *
 * @param token conversation token
 */
async function disableLiveTranscription(token: string): liveTranscriptionDisableResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/live-transcription/{token}', { token }))
}

/**
 * Get the external apps that can be used as live transcription provider (admin only)
 */
async function getLiveTranscriptionProviders(): liveTranscriptionGetProvidersResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/live-transcription/providers'))
}

/**
 * Get the status of the configured live transcription provider (admin only)
 */
async function getLiveTranscriptionProviderStatus(): liveTranscriptionGetProviderStatusResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/live-transcription/status'))
}

/**
 * Get available languages for live transcriptions
 */
async function getLiveTranscriptionLanguages(): liveTranscriptionGetAvailableLanguagesResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/live-transcription/languages'))
}

/**
 * Get available translation languages for live transcriptions
 */
async function getLiveTranscriptionTranslationLanguages(): liveTranscriptionGetAvailableTranslationLanguagesResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/live-transcription/translation-languages'))
}

/**
 * Set language for live transcription
 *
 * @param token conversation token
 * @param languageId the ID of the language
 */
async function setLiveTranscriptionLanguage(token: string, languageId: string): liveTranscriptionSetLanguageResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/live-transcription/{token}/language', { token }), {
		languageId,
	})
}

/**
 * Set target language for live translation
 *
 * @param token conversation token
 * @param targetLanguageId the ID of the target language
 */
async function setLiveTranscriptionTargetLanguage(token: string, targetLanguageId: string | null): liveTranscriptionSetTargetLanguageResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/live-transcription/{token}/target-language', { token }), {
		targetLanguageId,
	})
}

export {
	disableLiveTranscription,
	enableLiveTranscription,
	getLiveTranscriptionLanguages,
	getLiveTranscriptionProviders,
	getLiveTranscriptionProviderStatus,
	getLiveTranscriptionTranslationLanguages,
	setLiveTranscriptionLanguage,
	setLiveTranscriptionTargetLanguage,
}
