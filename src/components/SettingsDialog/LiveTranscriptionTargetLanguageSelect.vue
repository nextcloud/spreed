<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import { useStore } from 'vuex'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { useLiveTranscriptionStore } from '../../stores/liveTranscription.ts'
import { useSettingsStore } from '../../stores/settings.ts'

interface LanguageOption {
	id: string
	label: string
}

const store = useStore()
const liveTranscriptionStore = useLiveTranscriptionStore()
const settingsStore = useSettingsStore()

const loadLanguagesFailed = ref(false)
const languageBeingChanged = ref(false)

const placeholder = computed(() => {
	if (loadLanguagesFailed.value) {
		return t('spreed', 'Languages could not be loaded')
	}

	if (!languageOptions.value.length) {
		return t('spreed', 'Loading languages …')
	}

	if (settingsStore.liveTranscriptionTargetLanguageId && !selectedOption.value) {
		return t('spreed', 'Invalid language ({languageId})', {
			languageId: settingsStore.liveTranscriptionTargetLanguageId,
		})
	}

	if (!selectedOption.value) {
		return defaultLanguageLabel.value
	}

	return null
})

const defaultLanguageLabel = computed(() => {
	const languageId = liveTranscriptionStore.getLiveTranscriptionDefaultTargetLanguageId()
	if (!languageId) {
		return t('spreed', 'Default language')
	}

	const languageName = liveTranscriptionStore.getLiveTranscriptionTargetLanguages()?.[languageId]?.name ?? languageId

	return t('spreed', 'Default language ({languageName})', {
		languageName,
	})
})

const languageOptions = computed(() => {
	const liveTranscriptionLanguages = liveTranscriptionStore.getLiveTranscriptionTargetLanguages()
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
			return option.id === settingsStore.liveTranscriptionTargetLanguageId
		}) ?? null
	},

	set(value: LanguageOption) {
		changeLanguage(value)
	},
})

liveTranscriptionStore.loadLiveTranscriptionTranslationLanguages().catch(() => {
	loadLanguagesFailed.value = true

	showError(t('spreed', 'Error when trying to load the available live translation languages'))
})

/**
 * Set the live transcription target language from the given option
 *
 * @param language the option with the target language to set
 */
async function changeLanguage(language: LanguageOption) {
	languageBeingChanged.value = true

	try {
		await settingsStore.updateLiveTranscriptionTargetLanguageId(language ? language.id : '')
	} catch (error) {
		showError(t('spreed', 'Error when trying to set live translation language'))
	}

	languageBeingChanged.value = false
}
</script>

<template>
	<NcSelect
		v-model="selectedOption"
		class="live_transcription_settings_target_language_id"
		:input-label="t('spreed', 'Set language used to show the transcriptions')"
		:placeholder="placeholder"
		:options="languageOptions"
		:disabled="!languageOptions.length || loadLanguagesFailed || languageBeingChanged"
		:loading="(!languageOptions.length && !loadLanguagesFailed) || languageBeingChanged" />
</template>

<style lang="scss" scoped>
// Temporary style to align component until it is migrated in the library.
.live_transcription_settings_target_language_id {
	padding-inline: var(--app-settings-section-text-offset);
}
</style>
