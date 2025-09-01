<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import { useStore } from 'vuex'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { useLiveTranscriptionStore } from '../../stores/liveTranscription.ts'

interface LanguageOption {
	id: string
	label: string
}

const { token } = defineProps<{
	token: string
}>()

const store = useStore()
const liveTranscriptionStore = useLiveTranscriptionStore()

const loadLanguagesFailed = ref(false)
const languageBeingChanged = ref(false)

const conversation = computed(() => {
	return store.getters.conversation(token) || store.getters.dummyConversation
})

const inputLabel = computed(() => {
	return t('spreed', 'Set language spoken in calls')
})

const placeholder = computed(() => {
	if (loadLanguagesFailed.value) {
		return t('spreed', 'Languages could not be loaded')
	}

	if (!languageOptions.value.length) {
		return t('spreed', 'Loading languages …')
	}

	if (conversation.value.liveTranscriptionLanguageId && !selectedOption.value) {
		return t('spreed', 'Invalid language')
	}

	if (!selectedOption.value) {
		return t('spreed', 'Default language (English)')
	}

	return null
})

const languageOptions = computed(() => {
	const liveTranscriptionLanguages = liveTranscriptionStore.getLiveTranscriptionLanguages()
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
})

const selectedOption = computed({
	get() {
		return languageOptions.value.find((option) => {
			return option.id === conversation.value.liveTranscriptionLanguageId
		}) ?? null
	},

	set(value: LanguageOption) {
		changeLanguage(value)
	},
})

liveTranscriptionStore.loadLiveTranscriptionLanguages().catch(() => {
	loadLanguagesFailed.value = true

	showError(t('spreed', 'Error when trying to load the available live transcription languages'))
})

/**
 * Set the live transcription language from the given option
 *
 * @param language the option with the language to set
 */
async function changeLanguage(language: LanguageOption) {
	languageBeingChanged.value = true

	try {
		await store.dispatch('setLiveTranscriptionLanguage', {
			token,
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

	languageBeingChanged.value = false
}
</script>

<template>
	<div class="app-settings-subsection">
		<h4 class="app-settings-section__subtitle">
			{{ t('spreed', 'Language') }}
		</h4>

		<NcSelect id="live_transcription_settings_language_id"
			v-model="selectedOption"
			:input-label="inputLabel"
			:placeholder="placeholder"
			:options="languageOptions"
			:disabled="!languageOptions.length || loadLanguagesFailed || languageBeingChanged"
			:loading="(!languageOptions.length && !loadLanguagesFailed) || languageBeingChanged" />
	</div>
</template>
