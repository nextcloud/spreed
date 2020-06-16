<!--
  - @copyright Copyright (c) 2020, Daniel Calviño Sánchez <danxuliu@gmail.com>
  -
  - @author Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<transition name="slide-right">
		<aside v-if="isOpen" id="talk-sidebar">
			<div v-if="!conversation" class="emptycontent room-not-joined">
				<div class="icon icon-talk" />
				<h2>{{ t('spreed', 'Discuss this file') }}</h2>
				<button class="primary" :disabled="joiningConversation" @click="joinConversation">
					{{ t('spreed', 'Join conversation') }}
					<span v-if="joiningConversation" class="icon icon-loading-small" />
				</button>
			</div>
			<template v-else>
				<CallView v-if="isInCall"
					:token="token"
					:is-sidebar="true" />
				<PreventUnload :when="warnLeaving" />
				<CallButton class="call-button" />
				<ChatView :token="token" />
			</template>
		</aside>
	</transition>
</template>

<script>
import PreventUnload from 'vue-prevent-unload'
import { loadState } from '@nextcloud/initial-state'
import CallView from './components/CallView/CallView'
import ChatView from './components/ChatView'
import CallButton from './components/TopBar/CallButton'
import { PARTICIPANT } from './constants'
import { EventBus } from './services/EventBus'
import { fetchConversation } from './services/conversationsService'
import { getPublicShareConversationData } from './services/filesIntegrationServices'
import {
	joinConversation,
	leaveConversationSync,
} from './services/participantsService'
import { signalingKill } from './utils/webrtc/index'
import browserCheck from './mixins/browserCheck'
import duplicateSessionHandler from './mixins/duplicateSessionHandler'
import talkHashCheck from './mixins/talkHashCheck'
import SessionStorage from './services/SessionStorage'

export default {

	name: 'PublicShareSidebar',

	components: {
		CallButton,
		CallView,
		ChatView,
		PreventUnload,
	},

	mixins: [
		browserCheck,
		duplicateSessionHandler,
		talkHashCheck,
	],

	props: {
		shareToken: {
			type: String,
			required: true,
		},

		state: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			fetchCurrentConversationIntervalId: null,
			joiningConversation: false,
		}
	},

	computed: {
		token() {
			return this.$store.getters.getToken()
		},

		conversation() {
			return this.$store.getters.conversation(this.token)
		},

		isOpen() {
			return this.state.isOpen
		},

		isInCall() {
			const participantIndex = this.$store.getters.getParticipantIndex(this.token, this.$store.getters.getParticipantIdentifier())
			if (participantIndex === -1) {
				return false
			}

			const participant = this.$store.getters.getParticipant(this.token, participantIndex)

			return SessionStorage.getItem('joined_conversation') === this.token
				&& participant.inCall !== PARTICIPANT.CALL_FLAG.DISCONNECTED
		},

		warnLeaving() {
			return !this.isLeavingAfterSessionConflict && this.isInCall
		},
	},

	beforeMount() {
		window.addEventListener('unload', () => {
			if (this.token) {
				// We have to do this synchronously, because in unload and beforeunload
				// Promises, async and await are prohibited.
				signalingKill()
				leaveConversationSync(this.token)
			}
		})
	},

	mounted() {
		// see browserCheck mixin
		this.checkBrowser()
	},

	methods: {

		async joinConversation() {
			this.joiningConversation = true

			await this.getPublicShareConversationData()

			await joinConversation(this.token)

			// No need to wait for it, but fetching the conversation needs to be
			// done once the user has joined the conversation (otherwise only
			// limited data would be received if the user was not a participant
			// of the conversation yet).
			this.fetchCurrentConversation()

			// FIXME The participant will not be updated with the server data
			// when the conversation is got again (as "addParticipantOnce" is
			// used), although that should not be a problem given that only the
			// "inCall" flag (which is locally updated when joining and leaving
			// a call) is currently used.
			if (loadState('talk', 'signaling_mode') !== 'internal') {
				EventBus.$on('shouldRefreshConversations', this.fetchCurrentConversation)
				EventBus.$on('Signaling::participantListChanged', this.fetchCurrentConversation)
			} else {
				// The "shouldRefreshConversations" event is triggered only when
				// the external signaling server is used; when the internal
				// signaling server is used periodic polling has to be used
				// instead.
				this.fetchCurrentConversationIntervalId = window.setInterval(this.fetchCurrentConversation, 30000)
			}
		},

		async getPublicShareConversationData() {
			const data = await getPublicShareConversationData(this.shareToken)

			this.$store.dispatch('updateToken', data.token)

			if (data.userId) {
				// Instead of using "getCurrentUser()" the current user is set
				// from the data returned by the controller (as the public share
				// page uses the incognito mode, and thus it always returns an
				// anonymous user).
				//
				// When the external signaling server is used it should wait
				// until the current user is set before trying to connect, as
				// otherwise the connection would fail due to a mismatch between
				// the user ID given when connecting to the backend (an
				// anonymous user) and the user that fetched the signaling
				// settings (the actual user). However, if that happens the
				// signaling server will retry the connection again and again,
				// so at some point the anonymous user will have been overriden
				// with the current user and the connection will succeed.
				this.$store.dispatch('setCurrentUser', {
					uid: data.userId,
					displayName: data.displayName,
				})
			}
		},

		async fetchCurrentConversation() {
			if (!this.token) {
				return
			}

			try {
				const response = await fetchConversation(this.token)
				this.$store.dispatch('addConversation', response.data.ocs.data)
				this.$store.dispatch('markConversationRead', this.token)

				// Although the current participant is automatically added to
				// the participants store it must be explicitly set in the
				// actors store.
				if (!this.$store.getters.getUserId()) {
					// Setting a guest only uses "sessionId" and "participantType".
					this.$store.dispatch('setCurrentParticipant', response.data.ocs.data)
				}
			} catch (exception) {
				window.clearInterval(this.fetchCurrentConversationIntervalId)

				this.$store.dispatch('deleteConversationByToken', this.token)
				this.$store.dispatch('updateToken', '')
			}

			this.joiningConversation = false
		},
	},
}
</script>

<style lang="scss" scoped>
/* Properties based on the app-sidebar */
#talk-sidebar {
	position: relative;
	flex-shrink: 0;
	width: 27vw;
	min-width: 300px;
	max-width: 500px;

	background: var(--color-main-background);
	border-left: 1px solid var(--color-border);

	overflow-x: hidden;
	overflow-y: auto;
	z-index: 1500;

	display: flex;
	flex-direction: column;
	justify-content: center;
}

.slide-right-leave-active,
.slide-right-enter-active {
	transition-duration: var(--animation-quick);
	transition-property: min-width, max-width;
}

.slide-right-enter-to,
.slide-right-leave {
	min-width: 300px;
	max-width: 500px;
}

.slide-right-enter,
.slide-right-leave-to {
	min-width: 0 !important;
	max-width: 0 !important;
}

#talk-sidebar > .emptycontent {
	/* Remove default margin-top as it is unneeded when showing only the empty
	 * content in a flex sidebar. */
	margin-top: 0;
}

#talk-sidebar .emptycontent button .icon {
	/* Override rules set for the main icon of an empty content area when an
	 * icon is shown in a button. */
	background-size: unset;
	width: unset;
	height: unset;
	margin: unset;

	/* Frame the loading icon on the right border of the button. */
	top: -3px;
	right: -5px;
}

#talk-sidebar .call-button {
	/* Center button horizontally. */
	margin-left: auto;
	margin-right: auto;

	margin-top: 10px;
	margin-bottom: 10px;
}

#talk-sidebar #call-container {
	position: relative;

	flex-grow: 1;

	/* Prevent shadows of videos from leaking on other elements. */
	overflow: hidden;

	/* Show the call container in a 16/9 proportion based on the sidebar
	 * width. This is the same proportion used for previews of images by the
	 * SidebarPreviewManager. */
	padding-bottom: 56.25%;
	max-height: 56.25%;

	/* Override the call container height so it properly adjusts to the 16/9
	 * proportion. */
	height: unset;
}

#talk-sidebar #call-container ::v-deep .videoContainer {
	/* The video container has some small padding to prevent the video from
	 * reaching the edges, but it also uses "width: 100%", so the padding should
	 * be included in the full width of the element. */
	box-sizing: border-box;
}

#talk-sidebar #call-container ::v-deep .videoContainer.promoted video {
	/* Base the size of the video on its width instead of on its height;
	 * otherwise the video could appear in full height but cropped on the sides
	 * due to the space available in the sidebar being typically larger in
	 * vertical than in horizontal. */
	width: 100%;
	height: auto;
}

#talk-sidebar #call-container ::v-deep .nameIndicator {
	/* The name indicator has some small padding to prevent the name from
	 * reaching the edges, but it also uses "width: 100%", so the padding should
	 * be included in the full width of the element. */
	box-sizing: border-box;
}

#talk-sidebar .chatView {
	display: flex;
	flex-direction: column;
	overflow: hidden;

	flex-grow: 1;

	/* Distribute available height between call container and chat view. */
	height: 50%;
}
</style>
