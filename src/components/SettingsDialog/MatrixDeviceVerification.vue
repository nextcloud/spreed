<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { MatrixVerification } from '../../types/index.ts'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconClose from 'vue-material-design-icons/Close.vue'
import IconShieldCheckOutline from 'vue-material-design-icons/ShieldCheckOutline.vue'
import { useActionStatus } from '../../composables/useActionStatus.ts'
import { cancelMatrixVerification, confirmMatrixVerification, getMatrixVerification, startMatrixVerification } from '../../services/matrixService.ts'
import { isAxiosErrorResponse } from '../../types/guards.ts'

const emit = defineEmits<{
	close: []
}>()

const START_ERRORS: Record<string, string> = {
	unavailable: t('spreed', 'Encrypted rooms are not supported on this server because the PHP sodium extension is missing'),
	keys: t('spreed', 'This device could not publish its keys to the homeserver yet. Try again later.'),
}

/** Milliseconds between two polls, the server waits for the other device itself as well */
const POLL_INTERVAL = 1000

const { getActionStatus, runAction } = useActionStatus()

const verification = ref<MatrixVerification | null>(null)
const started = ref(false)
let pollTimeout: ReturnType<typeof setTimeout> | undefined
let unmounted = false

const isRunning = computed(() => verification.value !== null && !['done', 'cancelled'].includes(verification.value.state))
const showIntro = computed(() => !started.value && !isRunning.value)
const isPending = computed(() => ['start', 'confirm', 'cancel'].some((key) => getActionStatus(key) === 'pending'))

// 4 emoji in the first row, the rest in the second, each row centered on its own
const emojiRows = computed(() => {
	const emoji = verification.value?.emoji ?? []
	return emoji.length ? [emoji.slice(0, 4), emoji.slice(4)].filter((row) => row.length) : []
})

const statusText = computed(() => {
	switch (verification.value?.state) {
		case 'requested':
			return t('spreed', 'Accept the verification request on another device of your Matrix account.')
		case 'keys_exchanged':
			return t('spreed', 'Do the emoji match the ones shown on your other device, in the same order?')
		case 'mac_sent':
			return t('spreed', 'Waiting for your other device to confirm the emoji …')
		case 'done':
			return t('spreed', 'The server is verified. Your other device now trusts it, so encrypted messages are shared with it.')
		case 'cancelled':
			return t('spreed', 'The verification was cancelled: {reason}', { reason: verification.value.reason ?? '' })
		default:
			return t('spreed', 'Waiting for your other device …')
	}
})

onMounted(async () => {
	try {
		verification.value = (await getMatrixVerification()).data.ocs.data
	} catch {
		// No verification yet
	}
	// Resume a running verification, otherwise the intro asks first
	schedulePoll()
})

onBeforeUnmount(() => {
	unmounted = true
	clearTimeout(pollTimeout)
})

/**
 * Poll while the other device has to answer
 */
function schedulePoll() {
	clearTimeout(pollTimeout)
	if (unmounted || !isRunning.value || verification.value?.state === 'keys_exchanged') {
		return
	}
	pollTimeout = setTimeout(async () => {
		try {
			verification.value = (await getMatrixVerification()).data.ocs.data
		} catch (error) {
			console.error(error)
		}
		schedulePoll()
	}, POLL_INTERVAL)
}

/**
 * Show why an action failed
 *
 * @param error The error of the request
 * @param message Shown when the server gave no known reason
 */
function showActionError(error: unknown, message: string) {
	console.error(error)
	const reason = isAxiosErrorResponse<{ error: string }>(error) ? error.response?.data?.ocs?.data?.error : undefined
	showError((reason && START_ERRORS[reason]) || message)
}

/**
 * Ask the other devices to verify this one
 */
async function start() {
	try {
		verification.value = await runAction('start', async () => (await startMatrixVerification()).data.ocs.data)
		started.value = true
	} catch (error) {
		showActionError(error, t('spreed', 'Could not start the verification'))
	}
	schedulePoll()
}

/**
 * The emoji match
 */
async function confirm() {
	try {
		verification.value = await runAction('confirm', async () => (await confirmMatrixVerification()).data.ocs.data)
	} catch (error) {
		showActionError(error, t('spreed', 'Could not confirm the verification'))
	}
	schedulePoll()
}

/**
 * Cancel the verification, also when the emoji do not match
 */
async function cancel() {
	try {
		verification.value = await runAction('cancel', async () => {
			await cancelMatrixVerification()
			return (await getMatrixVerification()).data.ocs.data
		})
	} catch (error) {
		showActionError(error, t('spreed', 'Could not cancel the verification'))
	}
	schedulePoll()
}

/**
 * Close the dialog, a running verification is cancelled so the other device does not wait
 */
function close() {
	if (isRunning.value) {
		cancelMatrixVerification().catch((error) => console.error(error))
	}
	emit('close')
}
</script>

<template>
	<NcDialog
		:name="t('spreed', 'Server verification')"
		size="normal"
		@update:open="close">
		<div v-if="showIntro" class="matrix-verification__intro">
			<p>{{ t('spreed', 'Your other Matrix devices only share the keys of encrypted rooms with verified devices. Verifying adds this Nextcloud server as such a device, not the device you are using right now.') }}</p>
			<NcNoteCard
				type="warning"
				:text="t('spreed', 'The server becomes the end of the end-to-end encryption: it decrypts the messages and stores them unencrypted in its database.')" />
			<p>{{ t('spreed', 'Only verify the server if you trust it with your messages. Without verification, encrypted rooms stay unreadable in Talk.') }}</p>
		</div>
		<p v-else>
			{{ statusText }}
		</p>
		<div v-if="!showIntro && emojiRows.length" class="matrix-verification__emoji">
			<div v-for="(row, rowIndex) in emojiRows" :key="rowIndex" class="matrix-verification__emoji-row">
				<div v-for="(emoji, index) in row" :key="index" class="matrix-verification__emoji-item">
					<span class="matrix-verification__emoji-symbol" aria-hidden="true">{{ emoji.emoji }}</span>
					<span>{{ emoji.name }}</span>
				</div>
			</div>
		</div>

		<template #actions>
			<template v-if="showIntro">
				<NcButton :disabled="isPending" @click="close">
					{{ t('spreed', 'Do not verify') }}
				</NcButton>
				<NcButton
					variant="primary"
					:disabled="isPending"
					@click="start">
					<template #icon>
						<NcLoadingIcon v-if="getActionStatus('start') === 'pending'" :size="20" />
						<IconShieldCheckOutline v-else :size="20" />
					</template>
					{{ t('spreed', 'Verify server') }}
				</NcButton>
			</template>
			<div v-else-if="verification?.state === 'keys_exchanged'" class="matrix-verification__match-actions">
				<NcButton variant="primary" :disabled="isPending" @click="confirm">
					<template #icon>
						<NcLoadingIcon v-if="getActionStatus('confirm') === 'pending'" :size="20" />
						<IconCheck v-else :size="20" />
					</template>
					{{ t('spreed', 'They match') }}
				</NcButton>
				<NcButton :disabled="isPending" @click="cancel">
					<template #icon>
						<NcLoadingIcon v-if="getActionStatus('cancel') === 'pending'" :size="20" />
						<IconClose v-else :size="20" />
					</template>
					{{ t('spreed', 'They do not match') }}
				</NcButton>
			</div>
			<NcButton v-else-if="isRunning" :disabled="isPending" @click="cancel">
				<template #icon>
					<NcLoadingIcon v-if="getActionStatus('cancel') === 'pending'" :size="20" />
					<IconClose v-else :size="20" />
				</template>
				{{ t('spreed', 'Cancel verification') }}
			</NcButton>
			<NcButton
				v-else-if="verification?.state === 'done'"
				variant="primary"
				@click="close">
				{{ t('spreed', 'Close') }}
			</NcButton>
			<NcButton
				v-else
				variant="primary"
				:disabled="isPending"
				@click="start">
				<template #icon>
					<NcLoadingIcon v-if="getActionStatus('start') === 'pending'" :size="20" />
					<IconShieldCheckOutline v-else :size="20" />
				</template>
				{{ t('spreed', 'Try again') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<style lang="scss" scoped>
.matrix-verification__emoji {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 3);
	margin-block: calc(var(--default-grid-baseline) * 2);
}

.matrix-verification__emoji-row {
	// 4 emoji in the first row and the rest in the second, each row centered on its own
	display: flex;
	justify-content: center;
	gap: calc(var(--default-grid-baseline) * 3);
}

.matrix-verification__emoji-item {
	display: flex;
	flex-direction: column;
	align-items: center;
	width: 64px;
}

.matrix-verification__match-actions {
	display: flex;
	justify-content: center;
	gap: calc(var(--default-grid-baseline) * 2);
	width: 100%;
}

.matrix-verification__emoji-symbol {
	font-size: 32px;
	line-height: 1.5;
}

.matrix-verification__intro {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
}
</style>
