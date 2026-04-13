<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script lang="ts" setup>
import { emit } from '@nextcloud/event-bus'
import { computed, onMounted, onUnmounted, watch, watchEffect } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useStore } from 'vuex'
import CallFailedDialog from '../components/CallView/CallFailedDialog.vue'
import CallView from '../components/CallView/CallView.vue'
import ChatView from '../components/ChatView.vue'
import LobbyScreen from '../components/LobbyScreen.vue'
import PollViewer from '../components/PollViewer/PollViewer.vue'
import TopBar from '../components/TopBar/TopBar.vue'
import { useIsInCall } from '../composables/useIsInCall.js'
import { useJoinCall } from '../composables/useJoinCall.ts'
import { CALL, CONVERSATION } from '../constants.ts'
import { EventBus } from '../services/EventBus.ts'
import SessionStorage from '../services/SessionStorage.js'
import { useActorStore } from '../stores/actor.ts'
import { useSettingsStore } from '../stores/settings.ts'

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

const isInLobby = computed(() => store.getters.isInLobby)
const connectionFailed = computed(() => store.getters.connectionFailed(props.token))

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
	EventBus.off('joined-conversation')
})

/**
 * Check if the user should join the call directly or show MediaSettings
 *
 * @param routeToken token of conversation to join
 */
function handleDirectCall(routeToken: string) {
	const conversation = store.getters.conversation(routeToken)
	const showRecordingWarning = [
		CALL.RECORDING.VIDEO_STARTING,
		CALL.RECORDING.AUDIO_STARTING,
		CALL.RECORDING.VIDEO,
		CALL.RECORDING.AUDIO,
	].includes(conversation.callRecording)
	|| conversation.recordingConsent === CALL.RECORDING_CONSENT.ENABLED
	const isConversationPhoneRoom = [
		CONVERSATION.OBJECT_TYPE.PHONE_LEGACY,
		CONVERSATION.OBJECT_TYPE.PHONE_PERSISTENT,
		CONVERSATION.OBJECT_TYPE.PHONE_TEMPORARY,
	].includes(conversation.objectType)
	&& conversation.objectId === CONVERSATION.OBJECT_ID.PHONE_OUTGOING

	// Verify conditions for showing MediaSettings (required or user opted out)
	if (showRecordingWarning || settingsStore.showMediaSettings || isConversationPhoneRoom) {
		emit('talk:media-settings:show')
		return
	}

	// Verify that conversation is joined before trying to join the call
	const currentJoinedToken = SessionStorage.getItem('joined_conversation')
	if (currentJoinedToken === routeToken) {
		joinCall(routeToken)
	} else {
		EventBus.once('joined-conversation', async ({ token }) => {
			if (token === routeToken) {
				// If the correct conversation joined, proceed
				joinCall(routeToken)
			}
		})
	}
}
</script>

<template>
	<div class="main-view">
		<LobbyScreen v-if="isInLobby" />
		<template v-else>
			<TopBar :isInCall="isInCall" />
			<CallView v-if="isInCall" :token="token" />
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
