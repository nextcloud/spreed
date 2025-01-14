<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog class="drafts"
		:name="t('spreed', 'Poll drafts')"
		size="normal"
		close-on-click-outside
		v-on="$listeners"
		@update:open="emit('close')">
		<EmptyView v-if="!pollDrafts.length"
			class="drafts__empty"
			:name="t('spreed', 'No poll drafts')"
			:description="t('spreed', 'There is no poll drafts yet saved for this conversation')">
			<template #icon>
				<IconPoll />
			</template>
		</EmptyView>
		<div v-else class="drafts__wrapper">
			<Poll v-for="item in pollDrafts"
				:id="item.id.toString()"
				:key="item.id"
				:token="token"
				:name="item.question"
				draft
				@click="openPollEditor" />
		</div>
		<template v-if="props.showCreateButton" #actions>
			<NcButton @click="openPollEditor(null)">
				{{ t('spreed', 'Create new poll') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import IconPoll from 'vue-material-design-icons/Poll.vue'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'

import EmptyView from '../EmptyView.vue'
import Poll from '../MessagesList/MessagesGroup/Message/MessagePart/Poll.vue'

import { EventBus } from '../../services/EventBus.ts'
import { usePollsStore } from '../../stores/polls.ts'

const props = defineProps<{
	token: string,
	showCreateButton?: boolean,
}>()
const emit = defineEmits<{
	(event: 'close'): void,
}>()

const pollsStore = usePollsStore()
/**
 * Receive poll drafts for the current conversation as owner/moderator
 */
pollsStore.getPollDrafts(props.token)
const pollDrafts = computed(() => pollsStore.getDrafts(props.token))

/**
 * Opens poll editor pre-filled from the draft
 * @param id poll draft ID
 */
function openPollEditor(id: number) {
	EventBus.emit('poll-editor-open', id)
}
</script>

<style lang="scss" scoped>
.drafts {
	:deep(.dialog__content) {
		min-height: 200px;
	}

	&__wrapper {
		display: grid;
		grid-template-columns: 1fr 1fr;
		grid-gap: var(--default-grid-baseline);
	}

	&__empty {
		margin: 0 !important;
	}
}
</style>
