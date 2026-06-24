<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Conversation } from '../../types/index.ts'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import { computed, ref } from 'vue'
import { useStore } from 'vuex'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import StopPreservingDialog from './StopPreservingDialog.vue'
import { CONVERSATION, PARTICIPANT } from '../../constants.ts'

const props = defineProps<{
	token: string
}>()

const vuexStore = useStore()
const isLoading = ref(false)

const conversation = computed(() => vuexStore.getters.conversation(props.token) || vuexStore.getters.dummyConversation as Conversation)

const isPreserved = computed(() => Boolean(conversation.value.attributes & CONVERSATION.ATTRIBUTE.PRESERVE))

const isOwner = computed(() => conversation.value.participantType === PARTICIPANT.TYPE.OWNER)

/**
 * Change preserved state of the conversation.
 * Request an input confirmation, if about to disable
 *
 * @param newValue new preserved state
 */
async function togglePreserve(newValue: boolean) {
	if (!isOwner.value) {
		return
	}

	if (newValue) {
		await setPreserve(true)
		return
	}

	// Disabling requires typing the conversation token to confirm
	const confirmation = await spawnDialog(StopPreservingDialog, {
		container: '#conversation-settings-container',
		conversationName: conversation.value.displayName,
		token: props.token,
	})

	if (confirmation !== props.token) {
		if (confirmation) {
			showError(t('spreed', 'The entered token does not match the conversation token'))
		}
		return
	}

	await setPreserve(false)
}

/**
 *
 * @param preserve
 */
async function setPreserve(preserve: boolean) {
	isLoading.value = true
	await vuexStore.dispatch('setPreserveConversation', {
		token: props.token,
		preserve,
	})
	isLoading.value = false
}
</script>

<template>
	<div class="app-settings-subsection">
		<h4 class="app-settings-section__subtitle">
			{{ t('spreed', 'Preserve conversation') }}
		</h4>
		<p class="app-settings-section__hint">
			{{ t('spreed', 'While preserved, this conversation can not be deleted, its chat history can not be cleared and the guests and joinable settings can not be changed.') }}
		</p>
		<NcCheckboxRadioSwitch
			type="switch"
			:modelValue="isPreserved"
			:disabled="isLoading || !isOwner"
			@update:modelValue="togglePreserve">
			{{ t('spreed', 'Preserve conversation') }}
		</NcCheckboxRadioSwitch>
		<p v-if="!isOwner" class="app-settings-section__hint">
			{{ t('spreed', 'Only the owner can change whether this conversation is preserved.') }}
		</p>
	</div>
</template>
