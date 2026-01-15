/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	liveTranscriptionGetAvailableLanguagesResponse,
	liveTranscriptionGetAvailableTranslationLanguagesResponse,
	LiveTranscriptionLanguage,
} from '../types/index.ts'

import { defineStore } from 'pinia'
import {
	getLiveTranscriptionLanguages,
	getLiveTranscriptionTranslationLanguages,
} from '../services/liveTranscriptionService.ts'

type LiveTranscriptionLanguageMap = { [key: string]: LiveTranscriptionLanguage }
type LiveTranscriptionTranslationLanguages = {
	originLanguages: LiveTranscriptionLanguageMap
	targetLanguages: LiveTranscriptionLanguageMap
	defaultTargetLanguageId: string
}
type State = {
	languages: LiveTranscriptionLanguageMap | null
	translationLanguages: LiveTranscriptionTranslationLanguages | null
}

let languagesPromise: liveTranscriptionGetAvailableLanguagesResponse | null = null
let translationLanguagesPromise: liveTranscriptionGetAvailableTranslationLanguagesResponse | null = null

export const useLiveTranscriptionStore = defineStore('liveTranscription', {
	state: (): State => ({
		languages: null,
		translationLanguages: null,
	}),

	actions: {
		getLiveTranscriptionLanguages() {
			return this.languages
		},

		/**
		 * Fetch the available languages for live transcriptions and save them
		 * in the store.
		 */
		async loadLiveTranscriptionLanguages() {
			if (this.languages) {
				return
			}

			if (languagesPromise) {
				await languagesPromise

				return
			}

			languagesPromise = getLiveTranscriptionLanguages()

			try {
				const response = await languagesPromise
				this.languages = response.data.ocs.data
			} catch (exception) {
				languagesPromise = null
				this.languages = null

				console.error('Error while getting available live transcription languages', exception)

				throw exception
			}
		},

		getLiveTranscriptionTargetLanguages() {
			return this.translationLanguages?.targetLanguages
		},

		getLiveTranscriptionDefaultTargetLanguageId() {
			return this.translationLanguages?.defaultTargetLanguageId
		},

		/**
		 * Fetch the available translation languages for live transcriptions and
		 * save them in the store.
		 */
		async loadLiveTranscriptionTranslationLanguages() {
			if (this.translationLanguages) {
				return
			}

			if (translationLanguagesPromise) {
				await translationLanguagesPromise

				return
			}

			translationLanguagesPromise = getLiveTranscriptionTranslationLanguages()

			try {
				const response = await translationLanguagesPromise
				this.translationLanguages = response.data.ocs.data
			} catch (exception) {
				translationLanguagesPromise = null
				this.translationLanguages = null

				console.error('Error while getting available live transcription translation languages', exception)

				throw exception
			}
		},
	},
})
