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
				draft />
		</div>
	</NcDialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import IconPoll from 'vue-material-design-icons/Poll.vue'

import { t } from '@nextcloud/l10n'

import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'

import EmptyView from '../EmptyView.vue'
import Poll from '../MessagesList/MessagesGroup/Message/MessagePart/Poll.vue'

import { useStore } from '../../composables/useStore.js'
import { usePollsStore } from '../../stores/polls.ts'

const props = defineProps<{
	token: string,
}>()
const emit = defineEmits<{
	(event: 'close'): void,
}>()

const store = useStore()
const pollsStore = usePollsStore()
/**
 * Receive poll drafts for the current conversation as owner/moderator
 */
pollsStore.getPollDrafts(props.token)
const pollDrafts = computed(() => pollsStore.getDrafts(props.token))
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
