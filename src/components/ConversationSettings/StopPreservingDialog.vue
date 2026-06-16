<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { NcDialogButtonProps } from '../UIShared/ConfirmDialog.vue'

import { t } from '@nextcloud/l10n'
import ConfirmDialog from '../UIShared/ConfirmDialog.vue'

defineProps<{
	/** Selector of the element the dialog is mounted into */
	container: string
	/** Display name of the conversation, shown for clarification */
	conversationName: string
	/** Token the owner has to type to confirm */
	token: string
}>()

const emit = defineEmits<{
	close: [result?: string]
}>()

const buttons: NcDialogButtonProps[] = [
	{
		label: t('spreed', 'Cancel'),
		variant: 'tertiary',
		callback: () => undefined,
	},
	{
		label: t('spreed', 'Stop preserving'),
		variant: 'error',
		type: 'submit',
		callback: () => true,
	},
]

/**
 *
 * @param result
 */
function onClose(result?: unknown) {
	emit('close', result as string | undefined)
}
</script>

<template>
	<ConfirmDialog
		:name="t('spreed', 'Stop preserving conversation')"
		:container="container"
		:buttons="buttons"
		size="normal"
		isForm
		:inputProps="{ label: t('spreed', 'Conversation token') }"
		@close="onClose">
		<div class="stop-preserving">
			<p>
				{{ t('spreed', 'If you stop preserving this conversation, it can be deleted, its chat history can be cleared and the guests and joinable settings can be changed again.') }}
			</p>
			<p>
				{{ t('spreed', 'Conversation') }}: <strong>{{ conversationName }}</strong>
			</p>
			<p>
				{{ t('spreed', 'To confirm, type the conversation token into the field below:') }}
				<samp>{{ token }}</samp>
			</p>
		</div>
	</ConfirmDialog>
</template>

<style lang="scss" scoped>
.stop-preserving {
	display: flex;
	flex-direction: column;
	gap: calc(2 * var(--default-grid-baseline));
	margin-bottom: var(--default-grid-baseline);
}
</style>
