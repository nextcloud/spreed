<!--
  - @copyright Copyright (c) 2023 Dorra Jaouad <dorra.jaoued1@gmail.com>
  -
  - @author Dorra Jaouad <dorra.jaoued1@gmail.com>
  -
  - @license AGPL-3.0-or-later
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<div class="talk-tab__wrapper">
		<CallButton class="call-button" />
		<ChatView />
		<PollViewer />
		<MediaSettings :recording-consent-given.sync="recordingConsentGiven" />
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
