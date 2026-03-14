<!--
 - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
 -->

<script setup lang="ts">
import { getGuestNickname, setGuestNickname } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import { computed, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcGuestContent from '@nextcloud/vue/components/NcGuestContent'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { isAxiosErrorResponse } from '../types/guards.ts'

const targetUserId = loadState<string>('spreed', 'meet_target_user_id')
const targetDisplayName = loadState<string>('spreed', 'meet_target_display_name')

const guestName = ref(getGuestNickname() || '')
const message = ref('')
const loading = ref(false)
const error = ref('')
const canSubmit = computed(() => guestName.value.trim() !== '' && !loading.value)

/**
 * Create a meet room and redirect to the conversation.
 */
async function startConversation() {
	loading.value = true
	error.value = ''

	setGuestNickname(guestName.value)

	try {
		const response = await axios.post(
			generateOcsUrl('/apps/spreed/api/v4/meet/{targetUserId}', { targetUserId }),
			{ message: message.value, displayName: guestName.value },
		)
		window.location.href = generateUrl('/call/{token}', { token: response.data.ocs.data.token })
	} catch (e) {
		if (isAxiosErrorResponse(e) && e.response) {
			if (e.response.status === 429) {
				error.value = t('spreed', 'Too many requests. Please try again later.')
			} else if (e.response.status === 404) {
				error.value = t('spreed', 'The user could not be found.')
			} else if (e.response.status === 403) {
				error.value = t('spreed', 'This action is not allowed.')
			} else {
				error.value = t('spreed', 'An error occurred. Please try again later.')
			}
		} else {
			error.value = t('spreed', 'An error occurred. Please try again later.')
		}
		loading.value = false
	}
}
</script>

<template>
	<NcGuestContent class="meet__content">
		<h2>{{ t('spreed', 'Meet {displayName}', { displayName: targetDisplayName }) }}</h2>
		<NcNoteCard
			v-if="error"
			type="error">
			{{ error }}
		</NcNoteCard>
		<NcTextField
			v-model="guestName"
			:label="t('spreed', 'Your name')"
			:disabled="loading"
			@keydown.enter="canSubmit && startConversation()" />
		<NcTextArea
			v-model="message"
			:label="t('spreed', 'Your message')"
			:disabled="loading"
			resize="vertical" />
		<NcButton
			variant="primary"
			wide
			:disabled="!canSubmit"
			@click="startConversation">
			{{ t('spreed', 'Start conversation') }}
		</NcButton>
	</NcGuestContent>
</template>

<style scoped lang="scss">
.meet__content {
	box-sizing: border-box;
	width: fit-content;
}
</style>
