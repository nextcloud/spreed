<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script lang="ts" setup>
import type { WatchStopHandle } from 'vue'
import type { Conversation } from '../types/index.ts'

import { emit } from '@nextcloud/event-bus'
import { computed, onMounted, onUnmounted, watch, watchEffect } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useStore } from 'vuex'
import CallFailedDialog from '../components/CallView/CallFailedDialog.vue'
import CallView from '../components/CallView/CallView.vue'
import ChatView from '../components/ChatView.vue'
import ExternalCallView from '../components/ExternalCallView.vue'
import LobbyScreen from '../components/LobbyScreen.vue'
import PollViewer from '../components/PollViewer/PollViewer.vue'
import TopBar from '../components/TopBar/TopBar.vue'
import { useIsInCall } from '../composables/useIsInCall.js'
import { useJoinCall } from '../composables/useJoinCall.ts'
import { watchJoinedConversation } from '../composables/useJoinedConversation.ts'
import { CALL, CONVERSATION } from '../constants.ts'
import { getTalkConfig } from '../services/CapabilitiesManager.ts'
import { useActorStore } from '../stores/actor.ts'
import { useSettingsStore } from '../stores/settings.ts'
import { isConversationPhoneRoom } from '../utils/conversation.ts'

const props = defineProps<{
	token: string
}>()

const store = useStore()
const isInCall = useIsInCall()
const { joinCall } = useJoinCall()
const router = useRouter()
const route = useRoute()
const actorStore = useActorStore()
const settingsStore = useSettingsStore()

/** Internal handlers for 'joined-conversation' watcher (direct-call) */
let unwatchJoinedConversation: WatchStopHandle | undefined
let watchedJoinedConversationToken: string | undefined
/**
 * Release the listener for joined conversation
 */
function stopWatchingJoinedConversation() {
	unwatchJoinedConversation?.()
	unwatchJoinedConversation = undefined
	watchedJoinedConversationToken = undefined
}

const isInLobby = computed(() => store.getters.isInLobby)
const connectionFailed = computed(() => store.getters.connectionFailed(props.token))
const isVoiceRoom = computed(() => Boolean(store.getters.conversation(props.token)?.attributes & CONVERSATION.ATTRIBUTE.VOICE_ROOM))
const isInExternalCall = computed(() => {
	const conversation = store.getters.conversation(props.token) as Conversation | undefined
	return conversation?.objectType === CONVERSATION.OBJECT_TYPE.EXTERNAL_CALL && isInCall.value
		&& !getTalkConfig('local', 'call', 'enabled')
		&& getTalkConfig('local', 'call', 'external-call-service')
})

watch([() => props.token, isVoiceRoom], ([newToken, newIsVoiceRoom]) => {
	// Release a stale joined-conversation listener when navigating away
	if (watchedJoinedConversationToken && watchedJoinedConversationToken !== newToken) {
		stopWatchingJoinedConversation()
	}
	if (newIsVoiceRoom && newToken) {
		handleDirectCall(newToken)
	}
}, { immediate: true })

watch(isInLobby, (isInLobby) => {
	// User is now blocked by the lobby
	if (isInLobby && isInCall.value) {
		store.dispatch('leaveCall', {
			token: props.token,
			participantIdentifier: actorStore.participantIdentifier,
		})
	}
})

onMounted(() => {
	watchEffect(() => {
		if (route.hash === '#direct-call') {
			handleDirectCall(route.params.token as string)
			router.replace({ hash: '' })
		} else if (route.hash === '#settings') {
			emit('show-conversation-settings', { token: props.token })
			router.replace({ hash: '' })
		}
	})
})

onUnmounted(() => {
	stopWatchingJoinedConversation()
})

/**
 * Check if the user should join the call directly or show MediaSettings
 *
 * @param routeToken token of conversation to join
 */
function handleDirectCall(routeToken: string) {
	stopWatchingJoinedConversation()

	const conversation = store.getters.conversation(routeToken)
	if ([CONVERSATION.TYPE.CHANGELOG, CONVERSATION.TYPE.NOTE_TO_SELF].includes(conversation.type)) {
		// Do not allow calls in these conversations
		return
	}

	const showRecordingWarning = [
		CALL.RECORDING.VIDEO_STARTING,
		CALL.RECORDING.AUDIO_STARTING,
		CALL.RECORDING.VIDEO,
		CALL.RECORDING.AUDIO,
	].includes(conversation.callRecording)
	|| conversation.recordingConsent === CALL.RECORDING_CONSENT.ENABLED

	// Verify conditions for showing MediaSettings (required or user opted out)
	if (showRecordingWarning || settingsStore.showMediaSettings || isConversationPhoneRoom(conversation)) {
		emit('talk:media-settings:show')
		return
	}

	watchedJoinedConversationToken = routeToken
	unwatchJoinedConversation = watchJoinedConversation(routeToken, () => {
		stopWatchingJoinedConversation()
		void joinCall(routeToken, { directCall: true })
	}, { immediate: true })
}
</script>

<template>
	<div class="main-view">
		<LobbyScreen v-if="isInLobby" />
		<template v-else>
			<TopBar v-if="!isInExternalCall" :isInCall="isInCall" />
			<ExternalCallView v-if="isInExternalCall" :token="token" />
			<CallView v-else-if="isInCall" :token="token" />
			<ChatView v-else />
			<PollViewer />
			<CallFailedDialog v-if="connectionFailed" :token="token" />
		</template>
	</div>
</template>

<style lang="scss" scoped>
.main-view {
	height: 100%;
	width: 100%;
	display: flex;
	flex-grow: 1;
	flex-direction: column;
	align-content: space-between;
	position: relative;
}
</style>
