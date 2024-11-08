<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed } from 'vue'

import IconAlertOctagon from 'vue-material-design-icons/AlertOctagon.vue'

import { t } from '@nextcloud/l10n'

import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'

import { useStore } from '../../composables/useStore.js'

const store = useStore()

const props = defineProps({
	token: {
		type: String,
		required: true,
	},
})

const STATUS_ERRORS = {
	400: t('spreed', 'Recording consent is required'),
	403: t('spreed', 'This conversation is read-only'),
	404: t('spreed', 'Conversation not found or not joined'),
	412: t('spreed', "Lobby is still active and you're not a moderator"),
}
const connectionFailed = computed(() => store.getters.connectionFailed(props.token))
const connectionFailedDialogId = `connection-failed-${props.token}`
const message = computed(() => {
	if (!connectionFailed.value) {
		return ''
	}

	const statusCode = connectionFailed.value?.meta?.statuscode
	if (STATUS_ERRORS[statusCode]) {
		return STATUS_ERRORS[statusCode]
	}
	if (connectionFailed.value?.data?.error) {
		return connectionFailed.value.data.error
	}

	return t('spreed', 'Please try to reload the page')
})

/**
 *
 */
function clearConnectionFailedError() {
	store.dispatch('clearConnectionFailed', props.token)
}

</script>

<template>
	<NcModal :label-id="connectionFailedDialogId"
		@close="clearConnectionFailedError">
		<NcEmptyContent :name="t('spreed', 'Connection failed')"
			:description="message">
			<template #icon>
				<IconAlertOctagon />
			</template>
		</NcEmptyContent>
	</NcModal>
</template>
