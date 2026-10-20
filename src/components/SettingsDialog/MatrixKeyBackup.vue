<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { showError, showSuccess } from '@nextcloud/dialogs'
import { n, t } from '@nextcloud/l10n'
import { ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import { restoreMatrixBackup } from '../../services/matrixService.ts'
import { isAxiosErrorResponse } from '../../types/guards.ts'

const emit = defineEmits<{
	close: []
}>()

const ERRORS: Record<string, string> = {
	'recovery-key': t('spreed', 'The recovery key is wrong'),
	backup: t('spreed', 'Your Matrix account has no key backup'),
	unavailable: t('spreed', 'Encrypted rooms are not supported on this server because the PHP sodium extension is missing'),
}

const recoveryKey = ref('')
const loading = ref(false)

/**
 * Restore the keys, the recovery key is not kept
 */
async function restore() {
	if (!recoveryKey.value || loading.value) {
		return
	}
	loading.value = true
	try {
		const response = await restoreMatrixBackup({ recoveryKey: recoveryKey.value })
		showSuccess(n('spreed', 'Restored %n key of encrypted messages', 'Restored %n keys of encrypted messages', response.data.ocs.data.restored))
		emit('close')
	} catch (error) {
		console.error(error)
		const reason = isAxiosErrorResponse<{ error: string }>(error) ? error.response?.data?.ocs?.data?.error : undefined
		showError((reason && ERRORS[reason]) || t('spreed', 'Could not restore the keys from the key backup'))
	} finally {
		loading.value = false
		recoveryKey.value = ''
	}
}
</script>

<template>
	<NcDialog
		:name="t('spreed', 'Restore encrypted history')"
		size="normal"
		@update:open="emit('close')">
		<p>{{ t('spreed', 'Messages of encrypted rooms from before the server was verified can be read after restoring their keys from the key backup of your Matrix account. Enter the recovery key (security key) your Matrix client showed when it set up the backup.') }}</p>
		<p class="matrix-key-backup__hint">
			{{ t('spreed', 'The recovery key is only used once. The server keeps the key of the backup to read messages whose keys arrive in the backup later.') }}
		</p>
		<NcPasswordField
			v-model="recoveryKey"
			:label="t('spreed', 'Recovery key')"
			autocomplete="off"
			:disabled="loading"
			@keydown.enter="restore" />

		<template #actions>
			<NcButton :disabled="loading" @click="emit('close')">
				{{ t('spreed', 'Cancel') }}
			</NcButton>
			<NcButton variant="primary" :disabled="loading || !recoveryKey" @click="restore">
				<template v-if="loading" #icon>
					<NcLoadingIcon :size="20" />
				</template>
				{{ t('spreed', 'Restore keys') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<style lang="scss" scoped>
.matrix-key-backup__hint {
	margin-block: var(--default-grid-baseline) calc(var(--default-grid-baseline) * 2);
	color: var(--color-text-maxcontrast);
}
</style>
