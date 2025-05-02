<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed, ref } from 'vue'
import { isNavigationFailure, NavigationFailureType } from 'vue-router'
import { useRouter, useRoute } from 'vue-router/composables'

import IconArchive from 'vue-material-design-icons/Archive.vue'
import IconDelete from 'vue-material-design-icons/Delete.vue'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'

import { useStore } from '../../composables/useStore.js'
import { hasTalkFeature } from '../../services/CapabilitiesManager.ts'

const supportsArchive = hasTalkFeature('local', 'archived-conversations-v2')

const props = defineProps<{
	token: string,
	isHighlighted: boolean,
}>()

const store = useStore()
const router = useRouter()
const route = useRoute()
const showDialog = ref(false)

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
}
</script>

<template>
	<div class="conversation-actions"
		:class="{ 'conversation-actions--highlighted': props.isHighlighted }">
		<p>{{ t('spreed', 'Meeting conversations are archived after 7 days of no activity.') }}</p>
		<div class="conversation-actions__buttons">
			<NcButton v-if="supportsArchive"
				type="primary"
				@click="archiveEventConversation">
				<template #icon>
					<IconArchive />
				</template>
				{{ t('spreed', 'Archive now') }}
			</NcButton>
			<NcButton v-if="isModerator"
				type="error"
				@click="showDialog = true">
				<template #icon>
					<IconDelete />
				</template>
				{{ t('spreed', 'Delete now') }}
			</NcButton>
		</div>
		<NcDialog :open.sync="showDialog"
			:name="t('spreed', 'Delete conversation')"
			:message="t('spreed', 'Are you sure you want to delete this conversation?')">
			<template #actions>
				<NcButton type="tertiary" @click="showDialog = false">
					{{ t('spreed', 'No') }}
				</NcButton>
				<NcButton type="error" @click="deleteEventConversation">
					{{ t('spreed', 'Yes') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<style scoped lang="scss">
.conversation-actions {
	padding: calc(var(--default-grid-baseline) * 2) var(--default-grid-baseline);
	transition: background-color var(--animation-quick) ease;

	&--highlighted {
		background-color: var(--color-primary-element-light);
		p {
			color: var(--color-main-text);
		}
		border-radius: var(--border-radius);
	}

	&__buttons {
		display: flex;
		justify-content: center;
		gap: var(--default-grid-baseline);
		margin-top: var(--default-grid-baseline);
	}
}
</style>
