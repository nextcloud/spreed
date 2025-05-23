<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="talk-tab__wrapper">
		<InternalSignalingHint />
		<CallButton v-if="!isInCall" class="talk-tab__call-button" />
		<CallFailedDialog v-if="connectionFailed" :token="token" />
		<ChatView class="talk-tab__chat-view" is-sidebar />
		<PollManager />
		<PollViewer />
		<MediaSettings :recording-consent-given.sync="recordingConsentGiven" />
	</div>
</template>

<script>

import CallFailedDialog from '../components/CallView/CallFailedDialog.vue'
import ChatView from '../components/ChatView.vue'
import MediaSettings from '../components/MediaSettings/MediaSettings.vue'
import PollManager from '../components/PollViewer/PollManager.vue'
import PollViewer from '../components/PollViewer/PollViewer.vue'
import InternalSignalingHint from '../components/RightSidebar/InternalSignalingHint.vue'
import CallButton from '../components/TopBar/CallButton.vue'

import { useIsInCall } from '../composables/useIsInCall.js'

export default {

	name: 'FilesSidebarChatView',

	components: {
		InternalSignalingHint,
		CallButton,
		CallFailedDialog,
		ChatView,
		MediaSettings,
		PollManager,
		PollViewer,
	},

	setup() {
		return {
			isInCall: useIsInCall(),
		}
	},

	data() {
		return {
			recordingConsentGiven: false,
		}
	},

	computed: {
		token() {
			return this.$store.getters.getToken()
		},

		connectionFailed() {
			return this.$store.getters.connectionFailed(this.token)
		},
	},
}

</script>

<style lang="scss" scoped>
.talk-tab {
	&__wrapper {
		display: flex;
		flex-direction: column;
		justify-content: flex-end;
		height: 100%;
		padding: var(--default-grid-baseline) 0;
	}

	&__call-button {
		margin: 0 auto calc(var(--default-grid-baseline) * 2);
	}

	&__chat-view {
		overflow: hidden;
	}
}
</style>
