<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { provide, ref, watch } from 'vue'
import { useRouter } from 'vue-router/composables'

import IconAccountMultiplePlus from 'vue-material-design-icons/AccountMultiplePlus.vue'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcPopover from '@nextcloud/vue/components/NcPopover'

import NewConversationContactsPage from './NewConversationDialog/NewConversationContactsPage.vue'

import { useStore } from '../composables/useStore.js'
import { ATTENDEE, CONVERSATION } from '../constants.ts'

const props = defineProps<{
  token: string,
  container?: string,
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
	const conversation = store.getters.conversation(token)
	if (conversation?.type !== CONVERSATION.TYPE.ONE_TO_ONE) {
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
		popup-role="dialog">
		<template #trigger>
			<NcButton type="tertiary"
				:title="t('spreed', 'Start a group conversation')"
				:aria-label="t('spreed', 'Start a group conversation')">
				<template #icon>
					<IconAccountMultiplePlus :size="20" />
				</template>
			</NcButton>
		</template>
		<template #default>
			<div class="start-group__content">
				<h5 class="start-group__header">
					{{ t('spreed', 'Start a group conversation') }}
				</h5>
				<NewConversationContactsPage class="start-group__contacts"
					:token="token"
					:selected-participants.sync="selectedParticipants"
					only-users />
				<NcButton class="start-group__action"
					type="primary"
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

		/* FIXME: remove after https://github.com/nextcloud-libraries/nextcloud-vue/pull/4959 is released */
		&,
		& :deep(*) {
			box-sizing: border-box;
		}
		/* FIXME: remove after https://github.com/nextcloud-libraries/nextcloud-vue/pull/6669 is released */
		& :deep(.avatardiv:has(img)) {
			line-height: 0 !important;
		}
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
