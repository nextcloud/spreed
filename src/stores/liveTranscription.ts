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
	languages: LiveTranscriptionLanguageMap | liveTranscriptionGetAvailableLanguagesResponse | null
	translationLanguages: LiveTranscriptionTranslationLanguages | liveTranscriptionGetAvailableTranslationLanguagesResponse | null
}
export const useLiveTranscriptionStore = defineStore('liveTranscription', {
	state: (): State => ({
		languages: null,
		translationLanguages: null,
	}),

	actions: {
		getLiveTranscriptionLanguages() {
			if (!this.languages || this.languages instanceof Promise) {
				return undefined
			}

			return this.languages as LiveTranscriptionLanguageMap
		},

		/**
		 * Fetch the available languages for live transcriptions and save them
		 * in the store.
		 */
		async loadLiveTranscriptionLanguages() {
			if (this.languages) {
				if (this.languages instanceof Promise) {
					await this.languages
				}

				return
			}

			this.languages = getLiveTranscriptionLanguages()

			try {
				const response = await this.languages
				this.languages = response.data.ocs.data
			} catch (exception) {
				this.languages = null

				throw exception
			}
		},

		getLiveTranscriptionTargetLanguages() {
			if (!this.translationLanguages || this.translationLanguages instanceof Promise) {
				return undefined
			}

			return (this.translationLanguages as LiveTranscriptionTranslationLanguages).targetLanguages
		},

		getLiveTranscriptionDefaultTargetLanguageId() {
			if (!this.translationLanguages || this.translationLanguages instanceof Promise) {
				return undefined
			}

			return (this.translationLanguages as LiveTranscriptionTranslationLanguages).defaultTargetLanguageId
		},

		/**
		 * Fetch the available translation languages for live transcriptions and
		 * save them in the store.
		 */
		async loadLiveTranscriptionTranslationLanguages() {
			if (this.translationLanguages) {
				if (this.translationLanguages instanceof Promise) {
					await this.translationLanguages
				}

				return
			}

			this.translationLanguages = getLiveTranscriptionTranslationLanguages()

			try {
				const response = await this.translationLanguages
				this.translationLanguages = response.data.ocs.data
			} catch (exception) {
				this.translationLanguages = null

				throw exception
			}
		},
	},
})
