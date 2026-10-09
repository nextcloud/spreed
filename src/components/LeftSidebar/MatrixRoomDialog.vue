<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcRadioGroup from '@nextcloud/vue/components/NcRadioGroup'
import NcRadioGroupButton from '@nextcloud/vue/components/NcRadioGroupButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { createMatrixRoom, joinMatrixRoom } from '../../services/matrixService.ts'

const emit = defineEmits<{
	(event: 'close'): void
}>()

const ERRORS: Record<string, string> = {
	account: t('spreed', 'Link a Matrix account in the Talk settings first'),
	invite: t('spreed', 'Enter Matrix user ids like @alice:example.org'),
	name: t('spreed', 'Enter a name for the room'),
	reference: t('spreed', 'Enter a room address like #room:example.org'),
	room: t('spreed', 'The Matrix room does not exist or can not be joined'),
}

const router = useRouter()

const mode = ref<'room' | 'direct' | 'join'>('room')
const name = ref('')
const topic = ref('')
const invites = ref('')
const reference = ref('')
const loading = ref(false)

const canSubmit = computed(() => {
	switch (mode.value) {
		case 'room':
			return name.value.trim() !== ''
		case 'direct':
			return invites.value.trim() !== ''
		default:
			return reference.value.trim() !== ''
	}
})

/**
 * Create or join the room and open the conversation
 */
async function submit() {
	loading.value = true
	try {
		const response = mode.value === 'join'
			? await joinMatrixRoom({ reference: reference.value.trim() })
			: await createMatrixRoom({
					name: mode.value === 'room' ? name.value.trim() : '',
					topic: mode.value === 'room' ? topic.value.trim() : '',
					invites: invites.value.split(/[\s,]+/).filter((invite) => invite !== ''),
					direct: mode.value === 'direct',
				})

		const token = response.data.ocs.data.token
		if (token) {
			router.push({ name: 'conversation', params: { token } })
		} else {
			showSuccess(t('spreed', 'The conversation appears with the next sync'))
		}
		emit('close')
	} catch (error) {
		console.error(error)
		// @ts-expect-error Vue: Object is of type unknown
		const reason = error?.response?.data?.ocs?.data?.error
		showError(ERRORS[reason] ?? t('spreed', 'The Matrix homeserver could not be reached'))
	} finally {
		loading.value = false
	}
}
</script>

<template>
	<NcDialog
		:name="t('spreed', 'Matrix room')"
		size="normal"
		@update:open="emit('close')">
		<form class="matrix-room" @submit.prevent="submit">
			<NcRadioGroup v-model="mode" :label="t('spreed', 'Matrix room')" hideLabel>
				<NcRadioGroupButton :label="t('spreed', 'New room')" value="room" />
				<NcRadioGroupButton :label="t('spreed', 'Direct chat')" value="direct" />
				<NcRadioGroupButton :label="t('spreed', 'Join room')" value="join" />
			</NcRadioGroup>

			<template v-if="mode === 'room'">
				<NcTextField v-model="name" :label="t('spreed', 'Name')" :disabled="loading" />
				<NcTextField v-model="topic" :label="t('spreed', 'Topic')" :disabled="loading" />
				<NcTextField
					v-model="invites"
					:label="t('spreed', 'Invite Matrix users')"
					placeholder="@alice:example.org, @bob:example.org"
					:disabled="loading" />
			</template>
			<NcTextField
				v-else-if="mode === 'direct'"
				v-model="invites"
				:label="t('spreed', 'Matrix user')"
				placeholder="@alice:example.org"
				:disabled="loading" />
			<NcTextField
				v-else
				v-model="reference"
				:label="t('spreed', 'Room address')"
				placeholder="#room:example.org"
				:disabled="loading" />

			<NcButton
				type="submit"
				variant="primary"
				:disabled="loading || !canSubmit">
				{{ mode === 'join' ? t('spreed', 'Join') : t('spreed', 'Create') }}
			</NcButton>
		</form>
	</NcDialog>
</template>

<style lang="scss" scoped>
.matrix-room {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
	padding-bottom: calc(var(--default-grid-baseline) * 2);
}
</style>
