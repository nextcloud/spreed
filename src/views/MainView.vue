<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script lang="ts" setup>
import { emit } from '@nextcloud/event-bus'
import { computed, onMounted, watch, watchEffect } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useStore } from 'vuex'
import CallFailedDialog from '../components/CallView/CallFailedDialog.vue'
import CallView from '../components/CallView/CallView.vue'
import ChatView from '../components/ChatView.vue'
import LobbyScreen from '../components/LobbyScreen.vue'
import PollViewer from '../components/PollViewer/PollViewer.vue'
import TopBar from '../components/TopBar/TopBar.vue'
import { useIsInCall } from '../composables/useIsInCall.js'
import { useActorStore } from '../stores/actor.ts'

const props = defineProps<{
	token: string
}>()

const store = useStore()
const isInCall = useIsInCall()
const router = useRouter()
const route = useRoute()
const actorStore = useActorStore()

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
			emit('talk:media-settings:show', '')
			router.replace({ hash: '' })
		} else if (route.hash === '#settings') {
			emit('show-conversation-settings', { token: props.token })
			router.replace({ hash: '' })
		}
	})
})
</script>

<template>
	<div class="main-view">
		<LobbyScreen v-if="isInLobby" />
		<template v-else>
			<TopBar :is-in-call="isInCall" />
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
