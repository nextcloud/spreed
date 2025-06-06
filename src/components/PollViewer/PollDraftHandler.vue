<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog class="drafts"
		:name="t('spreed', 'Poll drafts')"
		:container="container"
		size="normal"
		close-on-click-outside
		v-on="$listeners"
		@update:open="emit('close')">
		<EmptyView v-if="!pollDrafts.length"
			class="drafts__empty"
			:name="pollDraftsLoaded ? t('spreed', 'No poll drafts') : t('spreed', 'Loading …')"
			:description="pollDraftsLoaded ? t('spreed', 'There is no poll drafts yet saved for this conversation') : ''">
			<template #icon>
				<IconPoll v-if="pollDraftsLoaded" />
				<NcLoadingIcon v-else />
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
		<template v-if="!props.editorOpened" #actions>
			<NcButton @click="openPollEditor({ id: null, action: 'fill' })">
				{{ t('spreed', 'Create new poll') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import IconPoll from 'vue-material-design-icons/Poll.vue'
import EmptyView from '../EmptyView.vue'
import Poll from '../MessagesList/MessagesGroup/Message/MessagePart/Poll.vue'
import { EventBus } from '../../services/EventBus.ts'
import { usePollsStore } from '../../stores/polls.ts'

const props = defineProps<{
	token: string
	editorOpened?: boolean
	container?: string
}>()
const emit = defineEmits<{
	(event: 'close'): void
}>()

const pollsStore = usePollsStore()
/**
 * Receive poll drafts for the current conversation as owner/moderator
 */
pollsStore.getPollDrafts(props.token)
const pollDrafts = computed(() => pollsStore.getDrafts(props.token))
const pollDraftsLoaded = computed(() => pollsStore.draftsLoaded(props.token))

/**
 * Opens poll editor pre-filled from the draft
 * @param payload method payload
 * @param payload.id poll draft ID
 * @param payload.action required action ('fill' from draft or 'edit' draft)
 */
function openPollEditor({ id, action }: { id: number | null, action?: string }) {
	EventBus.emit('poll-editor-open', { token: props.token, id, fromDrafts: !props.editorOpened, action, selector: props.container })
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
