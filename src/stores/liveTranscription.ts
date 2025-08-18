/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	liveTranscriptionGetAvailableLanguagesResponse,
	LiveTranscriptionLanguage,
} from '../types/index.ts'

import { defineStore } from 'pinia'
import { getLiveTranscriptionLanguages } from '../services/liveTranscriptionService.ts'

type LiveTranscriptionLanguageMap = { [key: string]: LiveTranscriptionLanguage }
type State = {
	languages: LiveTranscriptionLanguageMap | liveTranscriptionGetAvailableLanguagesResponse | null
}
export const useLiveTranscriptionStore = defineStore('liveTranscription', {
	state: (): State => ({
		languages: null,
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
	},
})
