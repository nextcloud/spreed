<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { ref } from 'vue'
import NcRadioGroup from '@nextcloud/vue/components/NcRadioGroup'
import NcRadioGroupButton from '@nextcloud/vue/components/NcRadioGroupButton'
import { CONVERSATION } from '../../constants.ts'
import { useSettingsStore } from '../../stores/settings.ts'

type UNARCHIVE_OPTIONS = typeof CONVERSATION.UNARCHIVE[keyof typeof CONVERSATION.UNARCHIVE]

const UNARCHIVE_LABELS = {
	// TRANSLATORS Unarchive option - never unarchive conversations automatically
	NEVER: t('spreed', 'Never'),
	// TRANSLATORS Unarchive option - unarchive conversations when the user is mentioned or replied to
	MENTION: t('spreed', 'On mention or reply'),
	// TRANSLATORS Unarchive option - unarchive conversations on any new message
	ALWAYS: t('spreed', 'On any message'),
}

const settingsStore = useSettingsStore()

const unarchiveLoading = ref(false)

/**
 * Change personal setting for unarchiving conversations automatically
 *
 * @param value - new value
 */
async function setUnarchive(value: string) {
	unarchiveLoading.value = true
	try {
		await settingsStore.updateUnarchive(value as UNARCHIVE_OPTIONS)
	} catch (exception) {
		showError(t('spreed', 'Error while setting personal setting'))
	}
	unarchiveLoading.value = false
}
</script>

<template>
	<NcRadioGroup
		:label="t('spreed', 'Unarchive conversations automatically')"
		:description="t('spreed', 'Move a conversation back from the archived list when a new message is received')"
		:modelValue="settingsStore.unarchive"
		@update:modelValue="setUnarchive">
		<NcRadioGroupButton
			:label="UNARCHIVE_LABELS.NEVER"
			:value="CONVERSATION.UNARCHIVE.NEVER"
			:disabled="unarchiveLoading" />
		<NcRadioGroupButton
			:label="UNARCHIVE_LABELS.MENTION"
			:value="CONVERSATION.UNARCHIVE.MENTION"
			:disabled="unarchiveLoading" />
		<NcRadioGroupButton
			:label="UNARCHIVE_LABELS.ALWAYS"
			:value="CONVERSATION.UNARCHIVE.ALWAYS"
			:disabled="unarchiveLoading" />
	</NcRadioGroup>
</template>
