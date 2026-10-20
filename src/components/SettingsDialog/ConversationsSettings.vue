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
import { getTalkConfig } from '../../services/CapabilitiesManager.ts'
import { useSettingsStore } from '../../stores/settings.ts'

type UNARCHIVE_OPTIONS = typeof CONVERSATION.UNARCHIVE[keyof typeof CONVERSATION.UNARCHIVE]
type TAGS_SHOW_UNREAD_OPTIONS = typeof CONVERSATION.TAGS_SHOW_UNREAD[keyof typeof CONVERSATION.TAGS_SHOW_UNREAD]

const supportUnarchive = getTalkConfig('local', 'conversations', 'unarchive') !== undefined
const supportTagsShowUnread = getTalkConfig('local', 'conversations', 'tags-show-unread') !== undefined

const UNARCHIVE_LABELS = {
	// TRANSLATORS Unarchive option - never unarchive conversations automatically
	[CONVERSATION.UNARCHIVE.NEVER]: t('spreed', 'Off'),
	// TRANSLATORS Unarchive option - unarchive conversations when the user is mentioned or replied to
	[CONVERSATION.UNARCHIVE.MENTION]: t('spreed', '@-mentions only'),
	// TRANSLATORS Unarchive option - unarchive conversations on any new message
	[CONVERSATION.UNARCHIVE.ALWAYS]: t('spreed', 'All messages'),
}

const TAGS_SHOW_UNREAD_LABELS = {
	// TRANSLATORS Tags show unread option - never show unread conversations in collapsed tags
	[CONVERSATION.TAGS_SHOW_UNREAD.NEVER]: t('spreed', 'Off'),
	// TRANSLATORS Tags show unread option - show unread conversations in collapsed tags when the user is mentioned or replied to
	[CONVERSATION.TAGS_SHOW_UNREAD.MENTION]: t('spreed', '@-mentions only'),
	// TRANSLATORS Tags show unread option - show unread conversations in collapsed tags on any new message
	[CONVERSATION.TAGS_SHOW_UNREAD.ALWAYS]: t('spreed', 'All messages'),
}

const settingsStore = useSettingsStore()

const unarchiveLoading = ref(false)
const tagsShowUnreadLoading = ref(false)

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

/**
 * Change personal setting for showing unread conversations in collapsed tags
 *
 * @param value - new value
 */
async function setTagsShowUnread(value: string) {
	tagsShowUnreadLoading.value = true
	try {
		await settingsStore.updateTagsShowUnread(value as TAGS_SHOW_UNREAD_OPTIONS)
	} catch (exception) {
		showError(t('spreed', 'Error while setting personal setting'))
	}
	tagsShowUnreadLoading.value = false
}
</script>

<template>
	<NcRadioGroup
		v-if="supportTagsShowUnread"
		:label="t('spreed', 'Show unread conversations under collapsed tags')"
		:modelValue="settingsStore.tagsShowUnread"
		@update:modelValue="setTagsShowUnread">
		<NcRadioGroupButton
			v-for="value in CONVERSATION.TAGS_SHOW_UNREAD"
			:key="value"
			:label="TAGS_SHOW_UNREAD_LABELS[value]"
			:value
			:disabled="tagsShowUnreadLoading" />
	</NcRadioGroup>

	<NcRadioGroup
		v-if="supportUnarchive"
		:label="t('spreed', 'Unarchive conversation when new messages are received')"
		:modelValue="settingsStore.unarchive"
		@update:modelValue="setUnarchive">
		<NcRadioGroupButton
			v-for="value in CONVERSATION.UNARCHIVE"
			:key="value"
			:label="UNARCHIVE_LABELS[value]"
			:value
			:disabled="unarchiveLoading" />
	</NcRadioGroup>
</template>
