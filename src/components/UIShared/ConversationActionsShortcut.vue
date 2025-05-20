<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed } from 'vue'
import { isNavigationFailure, NavigationFailureType } from 'vue-router'
import { useRouter, useRoute } from 'vue-router/composables'

import IconCheckUnderline from 'vue-material-design-icons/CheckUnderline.vue'
import IconDelete from 'vue-material-design-icons/Delete.vue'

import { showError } from '@nextcloud/dialogs'
import { t, getLanguage } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'

import ConfirmDialog from '../../components/UIShared/ConfirmDialog.vue'

import { useStore } from '../../composables/useStore.js'
import { CONVERSATION } from '../../constants.ts'
import { hasTalkFeature, getTalkConfig } from '../../services/CapabilitiesManager.ts'

const supportsArchive = hasTalkFeature('local', 'archived-conversations-v2')
const retentionEventPeriod = getTalkConfig('local', 'conversations', 'retention-event')
const retentionPhonePeriod = getTalkConfig('local', 'conversations', 'retention-phone')
const retentionInstantMeetingPeriod = getTalkConfig('local', 'conversations', 'retention-instant-meetings')

const props = defineProps<{
	token: string,
	objectType: string,
	isHighlighted: boolean,
}>()

const store = useStore()
const router = useRouter()
const route = useRoute()

const isModerator = computed(() => store.getters.isModerator)

const expirationDuration = computed(() => {
	if (props.objectType === CONVERSATION.OBJECT_TYPE.EVENT) {
		return retentionEventPeriod
	} else if (props.objectType === CONVERSATION.OBJECT_TYPE.PHONE_TEMPORARY) {
		return retentionPhonePeriod
	} else if (props.objectType === CONVERSATION.OBJECT_TYPE.INSTANT_MEETING) {
		return retentionInstantMeetingPeriod
	}
	return 0
})

const isShown = computed(() => isModerator.value || expirationDuration.value !== 0)

const descriptionLabel = computed(() => {
	if (expirationDuration.value === 0) {
		return t('spreed', 'Would you like to delete this conversation?')
	}
	const expirationDurationFormatted = new Intl.RelativeTimeFormat(getLanguage(), { numeric: 'always' }).format(
		expirationDuration.value, 'days'
	)
	return t('spreed', 'This conversation will be automatically deleted for everyone {expirationDurationFormatted} of no activity.', { expirationDurationFormatted })
})

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
 * Unbind conversation from object
 */
async function resetObjectConversation() {
	await store.dispatch('unbindConversationFromObject', { token: props.token })
}

/**
 * Show confirmation dialog
 */
async function showConfirmationDialog() {
	spawnDialog(ConfirmDialog, {
		name: t('spreed', 'Delete conversation'),
		message: t('spreed', 'Are you sure you want to delete this conversation?'),
		buttons: [
			{
				label: t('spreed', 'No'),
				type: 'tertiary',
			},
			{
				label: t('spreed', 'Yes'),
				type: 'error',
				callback: () => {
					deleteEventConversation()
				},
			}
		],
	})
}
</script>

<template>
	<div v-if="isShown"
		class="conversation-actions"
		:class="{ 'conversation-actions--highlighted': props.isHighlighted }">
		<p>{{ descriptionLabel }}</p>
		<div v-if="isModerator"
			class="conversation-actions__buttons">
			<NcButton type="error"
				@click="showConfirmationDialog">
				<template #icon>
					<IconDelete />
				</template>
				{{ t('spreed', 'Delete now') }}
			</NcButton>
			<NcButton v-if="supportsArchive"
				type="secondary"
				@click="resetObjectConversation">
				<template #icon>
					<IconCheckUnderline />
				</template>
				{{ t('spreed', 'Keep') }}
			</NcButton>
		</div>
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
