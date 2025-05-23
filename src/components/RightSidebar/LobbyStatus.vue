<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed, ref } from 'vue'

import IconFileUpload from 'vue-material-design-icons/FileUpload.vue'
import IconLockOpen from 'vue-material-design-icons/LockOpen.vue'

import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'

import ImportEmailsDialog from '../ImportEmailsDialog.vue'

import { useStore } from '../../composables/useStore.js'
import { hasTalkFeature } from '../../services/CapabilitiesManager.ts'

const props = defineProps<{
	token: string,
}>()

const store = useStore()
const isLobbyStateLoading = ref(false)
const isImportEmailsDialogOpen = ref(false)

const supportImportEmails = computed(() => hasTalkFeature(props.token, 'email-csv-import'))

/**
 * Disable lobby for this conversation
 */
async function disableLobby() {
	isLobbyStateLoading.value = true
	try {
		await store.dispatch('toggleLobby', {
			token: props.token,
			enableLobby: false,
		})
		showSuccess(t('spreed', 'You opened the conversation to everyone'))
	} catch (e) {
		console.error('Error occurred when opening the conversation to everyone', e)
		showError(t('spreed', 'An error occurred when opening the conversation to everyone'))
	} finally {
		isLobbyStateLoading.value = false
	}
}
</script>

<template>
	<div class="lobby-status">
		<NcButton type="success" @click="disableLobby">
			<template #icon>
				<IconLockOpen :size="20" />
			</template>
			{{ t('spreed', 'Disable lobby') }}
		</NcButton>

		<NcButton v-if="supportImportEmails" @click="isImportEmailsDialogOpen = true">
			<template #icon>
				<IconFileUpload :size="20" />
			</template>
			{{ t('spreed', 'Import email participants') }}
		</NcButton>

		<ImportEmailsDialog v-if="isImportEmailsDialogOpen"
			:token="token"
			@close="isImportEmailsDialogOpen = false" />
	</div>
</template>

<style lang="scss">
.lobby-status {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: var(--default-grid-baseline);
}
</style>
