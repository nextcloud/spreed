<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Events } from '../../services/EventBus.ts'

import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import PollDraftHandler from './PollDraftHandler.vue'
import PollEditor from './PollEditor.vue'
import { useStore } from '../../composables/useStore.js'
import { CONVERSATION, PARTICIPANT } from '../../constants.ts'
import { hasTalkFeature } from '../../services/CapabilitiesManager.ts'
import { EventBus } from '../../services/EventBus.ts'

const store = useStore()

const pollEditorRef = ref<InstanceType<typeof PollEditor> | null>(null)

const showPollEditor = ref(false)
const showPollDraftHandler = ref(false)
const container = ref<string | undefined>(undefined)

const token = computed(() => store.getters.getConversationSettingsToken() || store.getters.getToken())
const canCreatePollDrafts = computed(() => {
	const { participantType, type } = store.getters.conversation(token.value) ?? {}
	// TODO: getters.isModerator should accept token
	return hasTalkFeature(token.value, 'talk-polls-drafts')
		&& ([PARTICIPANT.TYPE.OWNER, PARTICIPANT.TYPE.MODERATOR, PARTICIPANT.TYPE.GUEST_MODERATOR].includes(participantType))
		&& ([CONVERSATION.TYPE.GROUP, CONVERSATION.TYPE.PUBLIC].includes(type))
})

onMounted(() => {
	EventBus.on('poll-editor-open', openPollEditor)
	EventBus.on('poll-drafts-open', openPollDraftHandler)
})

onBeforeUnmount(() => {
	EventBus.off('poll-editor-open', openPollEditor)
	EventBus.off('poll-drafts-open', openPollDraftHandler)
})

/**
 * Opens PollDraftHandler dialog
 * @param payload event payload
 * @param [payload.selector] selector to mount dialog to (body by default)
 */
function openPollDraftHandler({ selector }: Events['poll-drafts-open']) {
	container.value = selector
	showPollDraftHandler.value = true
}

/**
 * Opens PollEditor dialog
 * @param payload event payload
 * @param payload.id poll draft ID to fill form with (null for empty form)
 * @param payload.fromDrafts whether editor was opened from PollDraftHandler dialog
 * @param payload.action required action ('fill' from draft or 'edit' draft)
 * @param [payload.selector] selector to mount dialog to (body by default)
 */
function openPollEditor({ id, fromDrafts, action, selector }: Events['poll-editor-open']) {
	container.value = selector
	showPollEditor.value = true
	nextTick(() => {
		pollEditorRef.value?.fillPollEditorFromDraft(id, fromDrafts, action)
		// Wait for editor to be mounted and filled before unmounting drafts dialog to avoid issues when inserting nodes
		showPollDraftHandler.value = false
	})
}
</script>

<template>
	<div>
		<!-- Poll creation dialog -->
		<PollEditor v-if="showPollEditor"
			ref="pollEditorRef"
			:token="token"
			:can-create-poll-drafts="canCreatePollDrafts"
			:container="container"
			@close="showPollEditor = false" />
		<!-- Poll drafts dialog -->
		<PollDraftHandler v-if="canCreatePollDrafts && showPollDraftHandler"
			:token="token"
			:container="container"
			:editor-opened="showPollEditor"
			@close="showPollDraftHandler = false" />
	</div>
</template>
