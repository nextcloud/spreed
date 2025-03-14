<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed } from 'vue'

import IconAlertOctagon from 'vue-material-design-icons/AlertOctagon.vue'

import { t } from '@nextcloud/l10n'

import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcModal from '@nextcloud/vue/components/NcModal'

import { useStore } from '../../composables/useStore.js'
import { messagePleaseTryToReload } from '../../utils/talkDesktopUtils.ts'

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
} as const
const connectionFailed = computed(() => store.getters.connectionFailed(props.token))
const connectionFailedDialogId = `connection-failed-${props.token}`
const message = computed(() => {
	if (!connectionFailed.value) {
		return ''
	}

	const statusCode: keyof typeof STATUS_ERRORS | undefined = connectionFailed.value.meta?.statuscode
	if (statusCode && STATUS_ERRORS[statusCode]) {
		return STATUS_ERRORS[statusCode]
	}
	if (connectionFailed.value?.data?.error) {
		return connectionFailed.value.data.error
	}

	return messagePleaseTryToReload
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
