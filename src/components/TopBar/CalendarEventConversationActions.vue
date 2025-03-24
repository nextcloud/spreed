<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed, ref } from 'vue'
import { isNavigationFailure, NavigationFailureType } from 'vue-router'
import { useRouter, useRoute } from 'vue-router/composables'

import IconAlert from 'vue-material-design-icons/Alert.vue'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'

import { useStore } from '../../composables/useStore.js'
import { hasTalkFeature } from '../../services/CapabilitiesManager.ts'

const supportsArchive = hasTalkFeature('local', 'archived-conversations-v2')

const props = defineProps<{
	token: string,
	container?: string,
}>()

const store = useStore()
const router = useRouter()
const route = useRoute()
const showEventConversationDialog = ref(false)

const isModerator = computed(() => store.getters.isModerator)

/**
 * Delete conversation
 */
async function deleteEventConversation() {
	try {
		if (route?.params?.token === props.token) {
			await router.push({ name: 'root' })
				.catch((failure) => !isNavigationFailure(failure, NavigationFailureType.duplicated) && Promise.reject(failure))
		}
		await store.dispatch('deleteConversationFromServer', { token: props.token })
	} catch (error) {
		console.error(`Error while deleting conversation ${error}`)
		showError(t('spreed', 'Error while deleting conversation'))
	}
}

/**
 * Archive conversation
 */
async function archiveEventConversation() {
	await store.dispatch('toggleArchive', { token: props.token, isArchived: false })
	showEventConversationDialog.value = false
}

</script>

<template>
	<div>
		<NcButton :aria-label="t('spreed', 'Event conversation expiry')"
			:title="t('spreed', 'Event conversation expiry')"
			type="warning"
			@click="showEventConversationDialog = true">
			<template #icon>
				<IconAlert :size="20" />
			</template>
		</NcButton>
		<NcDialog v-if="showEventConversationDialog"
			size="small"
			:container="container"
			close-on-click-outside
			:name="t('spreed', 'Event conversation to be expired')"
			@close="showEventConversationDialog = false">
			<template #default>
				<p>{{ t('spreed', 'Event conversations are archived after 7 days of no activity.') }}</p>
			</template>
			<template #actions>
				<NcButton v-if="isModerator"
					type="error"
					@click="deleteEventConversation">
					{{ t('spreed', 'Delete now') }}
				</NcButton>
				<NcButton v-if="supportsArchive"
					type="warning"
					@click="archiveEventConversation">
					{{ t('spreed', 'Archive now') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>
