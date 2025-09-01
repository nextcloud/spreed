<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Conversation } from '../types/index.ts'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { provide, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useStore } from 'vuex'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import IconAccountMultiplePlusOutline from 'vue-material-design-icons/AccountMultiplePlusOutline.vue'
import NewConversationContactsPage from './NewConversationDialog/NewConversationContactsPage.vue'
import { ATTENDEE, CONVERSATION } from '../constants.ts'

const props = defineProps<{
	token: string
	container?: string
}>()

const store = useStore()
const router = useRouter()

const selectedParticipants = ref(getArrayWithSecondAttendee(props.token))
provide('selectedParticipants', selectedParticipants)

const lockedParticipants = ref(getArrayWithSecondAttendee(props.token))
provide('lockedParticipants', lockedParticipants)

// Add a visual bulk selection state for SelectableParticipant component
provide('bulkParticipantsSelection', true)

watch(() => props.token, (newValue) => {
	selectedParticipants.value = getArrayWithSecondAttendee(newValue)
	lockedParticipants.value = getArrayWithSecondAttendee(newValue)
})

/**
 * Returns second attendee of 1-1 conversation as SelectableParticipant-compatible object
 * @param token - conversation token
 */
function getArrayWithSecondAttendee(token: string) {
	const conversation = store.getters.conversation(token) as Conversation | undefined
	if (!conversation || conversation.type !== CONVERSATION.TYPE.ONE_TO_ONE) {
		return []
	}
	return [{ id: conversation.name, source: ATTENDEE.ACTOR_TYPE.USERS, label: conversation.displayName }]
}

/**
 * Add current participants and selected ones to the new conversation
 */
async function extendOneToOneConversation() {
	try {
		const newConversation = await store.dispatch('extendOneToOneConversation', {
			token: props.token,
			newParticipants: selectedParticipants.value,
		})
		if (newConversation) {
			await router.push({ name: 'conversation', params: { token: newConversation.token } })
		}
	} catch (error) {
		console.error('Error creating new conversation: ', error)
		showError(t('spreed', 'Error while creating the conversation'))
	}
}
</script>

<template>
	<NcPopover :container="container"
		popup-role="dialog"
		close-on-click-outside>
		<template #trigger>
			<NcButton variant="tertiary"
				:title="t('spreed', 'Start a group conversation')"
				:aria-label="t('spreed', 'Start a group conversation')">
				<template #icon>
					<IconAccountMultiplePlusOutline :size="20" />
				</template>
			</NcButton>
		</template>
		<template #default>
			<div class="start-group__content">
				<h5 class="start-group__header">
					{{ t('spreed', 'Start a group conversation') }}
				</h5>
				<NewConversationContactsPage
					v-model:selected-participants="selectedParticipants"
					class="start-group__contacts"
					:token="token"
					only-users />
				<NcButton class="start-group__action"
					variant="primary"
					:disabled="!selectedParticipants.length"
					@click="extendOneToOneConversation">
					{{ t('spreed', 'Create conversation') }}
				</NcButton>
			</div>
		</template>
	</NcPopover>
</template>

<style lang="scss" scoped>
.start-group {
	&__content {
		display: flex;
		flex-direction: column;
		gap: calc(2 * var(--default-grid-baseline));
		width: 350px;
		padding: calc(2 * var(--default-grid-baseline));
	}

	&__header {
		margin-block: 0 var(--default-grid-baseline);
		text-align: center;
	}

	&__contacts {
		display: flex;
		flex-direction: column;
		max-height: 50vh;
	}

	&__action {
		justify-self: flex-end;
		align-self: flex-end;
	}
}
</style>
