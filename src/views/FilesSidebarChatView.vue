<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="talk-tab__wrapper">
		<CallButton class="call-button" />
		<ChatView />
		<PollViewer />
		<MediaSettings v-model:recording-consent-given="recordingConsentGiven" />
	</div>
</template>
<script>

import ChatView from '../components/ChatView.vue'
import MediaSettings from '../components/MediaSettings/MediaSettings.vue'
import PollViewer from '../components/PollViewer/PollViewer.vue'
import CallButton from '../components/TopBar/CallButton.vue'

export default {

	name: 'FilesSidebarChatView',

	components: {
		CallButton,
		ChatView,
		MediaSettings,
		PollViewer,
	},

	data() {
		return {
			recordingConsentGiven: false,
		}
	},
}

</script>

<style scoped>
.talk-tab__wrapper{
	height: 100%;
}

.call-button {
	/* Center button horizontally. */
	margin-left: auto;
	margin-right: auto;

	margin-top: 10px;
	margin-bottom: 10px;
}

.chatView {
	overflow: hidden;
	/* Considering the call button height (44px + 10px * 2) */
	height: calc(100% - 64px);
}
</style>
