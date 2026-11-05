<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { showError, showSuccess } from '@nextcloud/dialogs'
import { n, t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import { useStore } from 'vuex'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { formatMessageExpiration, messageExpirationOptions } from '../../utils/formattedTime.ts'

interface ExpirationOption {
	id: number
	label: string
}

const { token, canModerate = false } = defineProps<{
	token: string
	canModerate?: boolean
}>()

// Signed 32-bit INTEGER column in the DB
const MAX_EXPIRATION_IN_SECONDS = 2147483647

const store = useStore()

const search = ref('')

const conversation = computed(() => {
	return store.getters.conversation(token) || store.getters.dummyConversation
})

const options = computed(() => {
	const value = Number(search.value)
	if (!Number.isInteger(value) || value <= 0) {
		return messageExpirationOptions
	}

	return [
		{ id: value * 60, label: n('spreed', '%n minute', '%n minutes', value) },
		{ id: value * 3600, label: n('spreed', '%n hour', '%n hours', value) },
		{ id: value * 86400, label: n('spreed', '%n day', '%n days', value) },
		{ id: value * 604800, label: n('spreed', '%n week', '%n weeks', value) },
		{ id: value * 31536000, label: n('spreed', '%n year', '%n years', value) },
	].filter((option) => option.id <= MAX_EXPIRATION_IN_SECONDS)
})

const selectedOption = computed({
	get() {
		return messageExpirationOptions.find((option) => {
			return option.id === conversation.value.messageExpiration
		}) ?? {
			id: conversation.value.messageExpiration,
			label: formatMessageExpiration(conversation.value.messageExpiration),
		}
	},

	set(value: ExpirationOption) {
		changeExpiration(value)
	},
})

/**
 * Save the new message expiration for the conversation
 *
 * @param expiration the selected option
 */
async function changeExpiration(expiration: ExpirationOption) {
	try {
		await store.dispatch('setMessageExpiration', {
			token,
			seconds: expiration.id,
		})

		if (expiration.id === 0) {
			showSuccess(t('spreed', 'Message expiration disabled'))
		} else {
			showSuccess(t('spreed', 'Message expiration set: {duration}', {
				duration: expiration.label,
			}))
		}
	} catch (error) {
		showError(t('spreed', 'Error when trying to set message expiration'))
		console.error(error)
	}
}
</script>

<template>
	<div class="app-settings-subsection">
		<h4 class="app-settings-section__subtitle">
			{{ t('spreed', 'Message expiration') }}
		</h4>
		<div class="app-settings-section__hint">
			{{ t('spreed', 'Chat messages can be expired after a certain time. Note: Files shared in chat will not be deleted for the owner, but will no longer be shared in the conversation.') }}
		</div>

		<template v-if="canModerate">
			<NcSelect
				id="moderation_settings_message_expiration"
				v-model="selectedOption"
				:inputLabel="t('spreed', 'Set message expiration')"
				:options="options"
				label="label"
				:clearable="false"
				@search="search = $event" />
		</template>

		<template v-else>
			<h5 class="app-settings-section__subtitle">
				{{ t('spreed', 'Current message expiration') }}
			</h5>
			<p>{{ selectedOption.label }}</p>
		</template>
	</div>
</template>
