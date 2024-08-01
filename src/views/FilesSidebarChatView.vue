<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="talk-tab__wrapper">
		<CallButton v-if="!isInCall" class="talk-tab__call-button" />
		<ChatView class="talk-tab__chat-view" />
		<PollViewer />
		<MediaSettings :recording-consent-given.sync="recordingConsentGiven" />
	</div>
</template>
<script>

import ChatView from '../components/ChatView.vue'
import MediaSettings from '../components/MediaSettings/MediaSettings.vue'
import PollViewer from '../components/PollViewer/PollViewer.vue'
import CallButton from '../components/TopBar/CallButton.vue'

import { useIsInCall } from '../composables/useIsInCall.js'

export default {

	name: 'FilesSidebarChatView',

	components: {
		CallButton,
		ChatView,
		MediaSettings,
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
