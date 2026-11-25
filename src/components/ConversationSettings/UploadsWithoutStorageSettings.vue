<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Conversation } from '../../types/index.ts'

import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { computed, ref, watch } from 'vue'
import { useStore } from 'vuex'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import { getUploadsWithoutStorage, setUploadsWithoutStorage } from '../../services/conversationsService.ts'

const props = defineProps<{
	token: string
	canModerate: boolean
}>()

const vuexStore = useStore()
const isLoading = ref(false)
const guestsFolder = ref<{ owner: string, path: string | null } | null>(null)

const conversation = computed(() => vuexStore.getters.conversation(props.token) || vuexStore.getters.dummyConversation as Conversation)

const isAllowed = computed(() => conversation.value.uploadsWithoutStorage)

const guestsFolderLabel = computed(() => {
	if (!guestsFolder.value) {
		return ''
	}
	return guestsFolder.value.path
		? t('spreed', 'Folder: {path}', { path: guestsFolder.value.path })
		: t('spreed', 'Uploading to shared folder of {owner}', { owner: guestsFolder.value.owner })
})

const summaryLabel = computed(() => isAllowed.value
	? t('spreed', 'Guests and participants without storage can upload files')
	: t('spreed', 'Only participants with storage can upload files'))

watch(isAllowed, async (allowed) => {
	guestsFolder.value = null
	if (!allowed || !props.canModerate) {
		return
	}
	try {
		const response = await getUploadsWithoutStorage(props.token)
		guestsFolder.value = response.data.ocs.data
	} catch (error) {
		console.error('Error while loading the Guests folder', error)
	}
}, { immediate: true })

/**
 * Allow or stop uploads of participants without storage
 *
 * @param allowed whether participants without storage may upload files
 */
async function toggleUploadsWithoutStorage(allowed: boolean) {
	isLoading.value = true
	try {
		const response = await setUploadsWithoutStorage(props.token, allowed)
		await vuexStore.dispatch('updateConversationIfHasChanged', response.data.ocs.data)
		showSuccess(allowed
			? t('spreed', 'Guests can now upload files.')
			: t('spreed', 'File uploads have been limited to participants with storage.'))
	} catch (error) {
		console.error('Error while changing uploads without storage', error)
		showError(t('spreed', 'Could not change file uploads for guests'))
	}
	isLoading.value = false
}
</script>

<template>
	<div v-if="canModerate">
		<NcCheckboxRadioSwitch
			:modelValue="isAllowed"
			:disabled="isLoading"
			type="switch"
			@update:modelValue="toggleUploadsWithoutStorage">
			{{ t('spreed', 'Allow file uploads from guests and participants without storage') }}
		</NcCheckboxRadioSwitch>
		<p v-if="guestsFolder" class="app-settings-section__hint">
			{{ guestsFolderLabel }}
		</p>
	</div>

	<div v-else>
		<h5 class="app-settings-section__subtitle">
			{{ t('spreed', 'File uploads') }}
		</h5>
		<p>{{ summaryLabel }}</p>
	</div>
</template>
