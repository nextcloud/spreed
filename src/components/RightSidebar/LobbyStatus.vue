<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { ref } from 'vue'

import IconLockOpen from 'vue-material-design-icons/LockOpen.vue'

import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import { useStore } from '../../composables/useStore.js'

const props = defineProps<{
	token: string,
}>()

const store = useStore()
const isLobbyStateLoading = ref(false)

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
		showError(t('spreed', 'Error occurred when opening the conversation to everyone'))
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
			{{ t('spreed', 'Disable lobby' ) }}
		</NcButton>
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
