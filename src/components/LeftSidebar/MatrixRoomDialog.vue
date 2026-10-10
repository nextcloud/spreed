<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcFormGroup from '@nextcloud/vue/components/NcFormGroup'
import NcRadioGroup from '@nextcloud/vue/components/NcRadioGroup'
import NcRadioGroupButton from '@nextcloud/vue/components/NcRadioGroupButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { createMatrixRoom, joinMatrixRoom } from '../../services/matrixService.ts'
import { isAxiosErrorResponse } from '../../types/guards.ts'

const emit = defineEmits<{
	(event: 'close'): void
}>()

const ERRORS: Record<string, string> = {
	account: t('spreed', 'Link a Matrix account in the Talk settings first'),
	invite: t('spreed', 'Enter Matrix user IDs like @alice:example.org'),
	name: t('spreed', 'Enter a name for the room'),
	reference: t('spreed', 'Enter a room address like #room:example.org'),
	room: t('spreed', 'The Matrix room does not exist or cannot be joined'),
}

const LABELS = {
	// TRANSLATORS: Dialog title to create a Matrix room or chat, or to join an existing one
	dialog: t('spreed', 'Matrix room'),
	// TRANSLATORS: Field for the topic of the Matrix room, which Talk shows as the conversation description
	topic: t('spreed', 'Description (Topic)'),
	// TRANSLATORS: Field for the Matrix user IDs to invite to the new room
	invites: t('spreed', 'Invite Matrix users'),
	// TRANSLATORS: Field for the Matrix user ID to start a direct chat with
	directUser: t('spreed', 'Matrix user'),
	// TRANSLATORS: Field for the alias, ID or link of the Matrix room to join
	reference: t('spreed', 'Room address'),
}

const router = useRouter()

const mode = ref<'room' | 'direct' | 'join'>('room')
const name = ref('')
const topic = ref('')
const invites = ref('')
const directUser = ref('')
const reference = ref('')
const loading = ref(false)

const canSubmit = computed(() => {
	switch (mode.value) {
		case 'room':
			return name.value.trim() !== ''
		case 'direct':
			return directUser.value.trim() !== ''
		default:
			return reference.value.trim() !== ''
	}
})

const buttons = computed(() => [{
	label: mode.value === 'join' ? t('spreed', 'Join') : t('spreed', 'Create'),
	type: 'submit' as const,
	variant: 'primary' as const,
	disabled: !canSubmit.value,
	callback: submit,
}])

/**
 * Create or join the room and open the conversation
 *
 * @return Whether the dialog can be closed
 */
async function submit(): Promise<boolean> {
	loading.value = true
	try {
		const response = mode.value === 'join'
			? await joinMatrixRoom({ reference: reference.value.trim() })
			: await createMatrixRoom({
					name: mode.value === 'room' ? name.value.trim() : '',
					topic: mode.value === 'room' ? topic.value.trim() : '',
					invites: mode.value === 'direct'
						? [directUser.value.trim()]
						: invites.value.split(/[\s,]+/).filter((invite) => invite !== ''),
					direct: mode.value === 'direct',
				})

		const token = response.data.ocs.data.token
		if (token) {
			router.push({ name: 'conversation', params: { token } })
		} else {
			showSuccess(t('spreed', 'The conversation appears with the next sync'))
		}
		return true
	} catch (error) {
		console.error(error)
		const reason = isAxiosErrorResponse<{ error: string }>(error) ? error.response?.data?.ocs?.data?.error : undefined
		showError((reason && ERRORS[reason]) || t('spreed', 'The homeserver could not be reached'))
		return false
	} finally {
		loading.value = false
	}
}
</script>

<template>
	<NcDialog
		:name="LABELS.dialog"
		size="normal"
		isForm
		:buttons
		@update:open="emit('close')">
		<NcFormGroup :label="LABELS.dialog" hideLabel>
			<NcRadioGroup v-model="mode" :label="LABELS.dialog" hideLabel>
				<!-- TRANSLATORS: Option to create a new Matrix room -->
				<NcRadioGroupButton :label="t('spreed', 'New room')" value="room" />
				<!-- TRANSLATORS: Option to start a direct chat with one Matrix user -->
				<NcRadioGroupButton :label="t('spreed', 'Direct chat')" value="direct" />
				<!-- TRANSLATORS: Option to join an existing Matrix room by its address -->
				<NcRadioGroupButton :label="t('spreed', 'Join room')" value="join" />
			</NcRadioGroup>

			<!-- All panels share one grid cell, so the dialog keeps the height of the tallest -->
			<div class="matrix-room__panels">
				<div class="matrix-room__panel" :class="{ 'matrix-room__panel--hidden': mode !== 'room' }">
					<NcTextField v-model="name" :label="t('spreed', 'Name')" :disabled="loading" />
					<NcTextField v-model="topic" :label="LABELS.topic" :disabled="loading" />
					<NcTextField
						v-model="invites"
						:label="LABELS.invites"
						placeholder="@alice:example.org, @bob:example.org"
						:disabled="loading" />
				</div>
				<div class="matrix-room__panel" :class="{ 'matrix-room__panel--hidden': mode !== 'direct' }">
					<NcTextField
						v-model="directUser"
						:label="LABELS.directUser"
						placeholder="@alice:example.org"
						:disabled="loading" />
				</div>
				<div class="matrix-room__panel" :class="{ 'matrix-room__panel--hidden': mode !== 'join' }">
					<NcTextField
						v-model="reference"
						:label="LABELS.reference"
						placeholder="#room:example.org"
						:disabled="loading" />
				</div>
			</div>
		</NcFormGroup>
	</NcDialog>
</template>

<style lang="scss" scoped>
.matrix-room__panels {
	display: grid;
}

.matrix-room__panel {
	grid-area: 1 / 1;
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);

	// Also removes the fields from the tab order and the accessibility tree
	&--hidden {
		visibility: hidden;
	}
}
</style>
