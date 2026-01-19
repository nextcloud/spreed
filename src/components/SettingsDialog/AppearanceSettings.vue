<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { computed, ref } from 'vue'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcFormBoxButton from '@nextcloud/vue/components/NcFormBoxButton'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import { CHAT_STYLE, CONVERSATION } from '../../constants.ts'
import { getTalkConfig } from '../../services/CapabilitiesManager.ts'
import { useActorStore } from '../../stores/actor.ts'
import { useSettingsStore } from '../../stores/settings.ts'
import { useSoundsStore } from '../../stores/sounds.js'

const settingsUrl = generateUrl('/settings/user/notifications')
const supportConversationsListStyle = getTalkConfig('local', 'conversations', 'list-style') !== undefined
const supportChatStyle = getTalkConfig('local', 'chat', 'style') !== undefined

const actorStore = useActorStore()
const settingsStore = useSettingsStore()
const soundsStore = useSoundsStore()

const chatAppearanceLoading = ref(false)
const appearanceLoading = ref(false)
const playSoundsLoading = ref(false)

const isGuest = computed(() => !actorStore.userId)

const conversationsListStyle = computed(() => settingsStore.conversationsListStyle !== CONVERSATION.LIST_STYLE.TWO_LINES)
const chatSplitViewEnabled = computed(() => settingsStore.chatStyle === CHAT_STYLE.SPLIT)
const shouldPlaySounds = computed(() => soundsStore.shouldPlaySounds)

/**
 * Change personal setting for conversations list style
 *
 * @param value - new value
 */
async function toggleConversationsListStyle(value: boolean) {
	appearanceLoading.value = true
	try {
		await settingsStore.updateConversationsListStyle(value ? CONVERSATION.LIST_STYLE.COMPACT : CONVERSATION.LIST_STYLE.TWO_LINES)
	} catch (exception) {
		showError(t('spreed', 'Error while setting personal setting'))
	}
	appearanceLoading.value = false
}

/**
 * Change personal setting for chat messages style
 *
 * @param value - new value
 */
async function toggleChatStyle(value: boolean) {
	chatAppearanceLoading.value = true
	try {
		await settingsStore.updateChatStyle(value ? CHAT_STYLE.SPLIT : CHAT_STYLE.UNIFIED)
	} catch (exception) {
		showError(t('spreed', 'Error while setting personal setting'))
	}
	chatAppearanceLoading.value = false
}

/**
 * Change personal setting for playing sounds in call
 *
 * @param value - new value
 */
async function togglePlaySounds(value: boolean) {
	playSoundsLoading.value = true
	try {
		await soundsStore.setShouldPlaySounds(value)
	} catch (exception) {
		showError(t('spreed', 'Error while saving sounds setting'))
	}
	playSoundsLoading.value = false
}
</script>

<template>
	<NcFormBox>
		<NcFormBoxSwitch
			v-if="!isGuest && supportConversationsListStyle"
			:model-value="conversationsListStyle"
			:label="t('spreed', 'Compact conversations list')"
			:disabled="appearanceLoading"
			@update:model-value="toggleConversationsListStyle" />
		<NcFormBoxSwitch
			v-if="supportChatStyle"
			:model-value="chatSplitViewEnabled"
			:label="t('spreed', 'Show your chat in split view')"
			:disabled="chatAppearanceLoading"
			@update:model-value="toggleChatStyle" />
	</NcFormBox>

	<NcFormBox>
		<NcFormBoxSwitch
			:model-value="shouldPlaySounds"
			:label="t('spreed', 'Play sounds when participants join or leave a call')"
			:description="t('spreed', 'Currently not available on iPhone and iPad due to technical restrictions by the manufacturer')"
			:disabled="playSoundsLoading"
			@update:model-value="togglePlaySounds" />
		<NcFormBoxButton
			v-if="!isGuest"
			:label="t('spreed', 'Notification settings')"
			:description="t('spreed', 'Sounds for chat and call notifications')"
			:href="settingsUrl"
			target="_blank" />
	</NcFormBox>
</template>
