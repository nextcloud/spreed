<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

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

<script>
import { emit } from '@nextcloud/event-bus'

import CallFailedDialog from '../components/CallView/CallFailedDialog.vue'
import CallView from '../components/CallView/CallView.vue'
import ChatView from '../components/ChatView.vue'
import LobbyScreen from '../components/LobbyScreen.vue'
import PollViewer from '../components/PollViewer/PollViewer.vue'
import TopBar from '../components/TopBar/TopBar.vue'

import { useIsInCall } from '../composables/useIsInCall.js'
import Router from '../router/router.js'

export default {
	name: 'MainView',
	components: {
		CallView,
		CallFailedDialog,
		ChatView,
		LobbyScreen,
		PollViewer,
		TopBar,
	},

	props: {
		token: {
			type: String,
			required: true,
		},
	},

	setup() {
		const isInCall = useIsInCall()
		return { isInCall }
	},

	computed: {
		conversation() {
			return this.$store.getters.conversation(this.token)
		},

		isInLobby() {
			return this.$store.getters.isInLobby
		},

		connectionFailed() {
			return this.$store.getters.connectionFailed(this.token)
		},
	},

	watch: {
		isInLobby(isInLobby) {
			// User is now blocked by the lobby
			if (isInLobby && this.isInCall) {
				this.$store.dispatch('leaveCall', {
					token: this.token,
					participantIdentifier: this.$store.getters.getParticipantIdentifier(),
				})
			}
		},
	},

	mounted() {
		const handleRouteHashChange = (token, route) => {
			if (route?.hash === '#direct-call') {
				emit('talk:media-settings:show')
				Router.replace({ ...route, hash: '' })
			} else if (route?.hash === '#settings') {
				emit('show-conversation-settings', { token })
				Router.replace({ ...route, hash: '' })
			}
		}

		handleRouteHashChange(this.token, Router.currentRoute)
		Router.afterEach((to) => handleRouteHashChange(this.token, to))
	},
}
</script>

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
