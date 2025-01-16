<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { nextTick, computed, onMounted, onBeforeUnmount, ref } from 'vue'

import PollDraftHandler from './PollDraftHandler.vue'
import PollEditor from './PollEditor.vue'

import { useStore } from '../../composables/useStore.js'
import { CONVERSATION, PARTICIPANT } from '../../constants.js'
import { hasTalkFeature } from '../../services/CapabilitiesManager.ts'
import { EventBus } from '../../services/EventBus.ts'
import type { Events } from '../../services/EventBus.ts'

const store = useStore()

const pollEditorRef = ref(null)

const showPollEditor = ref(false)
const showPollDraftHandler = ref(false)

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

const openPollDraftHandler = () => {
	showPollDraftHandler.value = true
}

const openPollEditor = ({ id, fromDrafts }: Events['poll-editor-open']) => {
	showPollEditor.value = true
	nextTick(() => {
		pollEditorRef.value?.fillPollEditorFromDraft(id, fromDrafts)
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
			@close="showPollEditor = false" />
		<!-- Poll drafts dialog -->
		<PollDraftHandler v-if="canCreatePollDrafts && showPollDraftHandler"
			:token="token"
			:editor-opened="showPollEditor"
			@close="showPollDraftHandler = false" />
	</div>
</template>
