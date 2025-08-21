<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="app-settings-subsection">
		<h4 class="app-settings-section__subtitle">
			{{ t('spreed', 'Language') }}
		</h4>

		<NcSelect id="live_transcription_settings_language_id"
			v-model="selectedOption"
			:input-label="t('spreed', 'Set language spoken in calls')"
			:placeholder="placeholder"
			:options="languageOptions"
			:disabled="!languageOptions.length || loadLanguagesFailed || languageBeingChanged"
			:loading="(!languageOptions.length && !loadLanguagesFailed) || languageBeingChanged" />
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { useLiveTranscriptionStore } from '../../stores/liveTranscription.ts'

export default {
	name: 'LiveTranscriptionSettings',

	components: {
		NcSelect,
	},

	props: {
		token: {
			type: String,
			default: null,
		},
	},

	setup() {
		const liveTranscriptionStore = useLiveTranscriptionStore()

		return {
			liveTranscriptionStore,
		}
	},

	data() {
		return {
			loadLanguagesFailed: false,
			languageBeingChanged: false,
		}
	},

	computed: {
		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		placeholder() {
			if (this.loadLanguagesFailed) {
				return t('spreed', 'Languages could not be loaded')
			}

			if (!this.languageOptions.length) {
				return t('spreed', 'Loading languages')
			}

			if (this.conversation.liveTranscriptionLanguageId && !this.selectedOption) {
				return t('spreed', 'Invalid language')
			}

			if (!this.selectedOption) {
				return t('spreed', 'Default language (English)')
			}

			return null
		},

		languageOptions() {
			const liveTranscriptionLanguages = this.liveTranscriptionStore.getLiveTranscriptionLanguages()
			if (!liveTranscriptionLanguages) {
				return []
			}

			const languageOptions = Object.keys(liveTranscriptionLanguages).map((key) => {
				return {
					id: key,
					label: liveTranscriptionLanguages[key].name,
				}
			})

			return languageOptions
		},

		selectedOption: {
			get() {
				return this.languageOptions.find((option) => {
					return option.id === this.conversation.liveTranscriptionLanguageId
				}) ?? null
			},

			set(value) {
				this.changeLanguage(value)
			},
		},
	},

	created() {
		this.liveTranscriptionStore.loadLiveTranscriptionLanguages().catch(() => {
			this.loadLanguagesFailed = true

			showError(t('spreed', 'Error when trying to load the available live transcription languages'))
		})
	},

	methods: {
		t,
		async changeLanguage(language) {
			this.languageBeingChanged = true

			try {
				await this.$store.dispatch('setLiveTranscriptionLanguage', {
					token: this.token,
					languageId: language ? language.id : '',
				})

				if (!language) {
					showSuccess(t('spreed', 'Default live transcription language set'))
				} else {
					showSuccess(t('spreed', 'Live transcription language set: {languageName}', {
						languageName: language.label,
					}))
				}
			} catch (error) {
				showError(t('spreed', 'Error when trying to set live transcription language'))
			}

			this.languageBeingChanged = false
		},
	},
}
</script>
