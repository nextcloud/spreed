<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed } from 'vue'
import { useStore } from 'vuex'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcModal from '@nextcloud/vue/components/NcModal'
import IconAlertOctagonOutline from 'vue-material-design-icons/AlertOctagonOutline.vue'
import IconRefresh from 'vue-material-design-icons/Refresh.vue'
import { messagePleaseTryToReload } from '../../utils/talkDesktopUtils.ts'

const props = defineProps({
	token: {
		type: String,
		required: true,
	},
})

const store = useStore()

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
 * Reset error status in the store
 */
function clearConnectionFailedError() {
	store.dispatch('clearConnectionFailed', props.token)
}

/**
 * Reload the page to get a valid room object and HPB settings
 */
function reloadApp() {
	window.location.reload()
}

</script>

<template>
	<NcModal
		:label-id="connectionFailedDialogId"
		@close="clearConnectionFailedError">
		<NcEmptyContent
			:name="t('spreed', 'Connection failed')"
			:description="message">
			<template #icon>
				<IconAlertOctagonOutline />
			</template>
			<template #action>
				<NcButton
					variant="primary"
					@click="reloadApp">
					<template #icon>
						<IconRefresh />
					</template>
					{{ t('spreed', 'Reload') }}
				</NcButton>
			</template>
		</NcEmptyContent>
	</NcModal>
</template>
