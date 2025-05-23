<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<TransitionWrapper name="slide-right">
		<aside v-if="isOpen" id="talk-sidebar">
			<div v-if="!token" class="emptycontent">
				<div class="icon icon-talk" />
				<h2>{{ t('spreed', 'This conversation has ended') }}</h2>
			</div>
			<template v-else>
				<TopBar is-in-call is-sidebar />
				<CallView :token="token" is-sidebar />
				<InternalSignalingHint />
				<ChatView is-sidebar />
				<PollManager />
				<PollViewer />
				<MediaSettings :recording-consent-given.sync="recordingConsentGiven" />
			</template>
		</aside>
	</TransitionWrapper>
</template>

<script>
import { getCurrentUser, getGuestNickname } from '@nextcloud/auth'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'

import CallView from './components/CallView/CallView.vue'
import ChatView from './components/ChatView.vue'
import MediaSettings from './components/MediaSettings/MediaSettings.vue'
import PollManager from './components/PollViewer/PollManager.vue'
import PollViewer from './components/PollViewer/PollViewer.vue'
import InternalSignalingHint from './components/RightSidebar/InternalSignalingHint.vue'
import TopBar from './components/TopBar/TopBar.vue'
import TransitionWrapper from './components/UIShared/TransitionWrapper.vue'

import { useHashCheck } from './composables/useHashCheck.js'
import { useSessionIssueHandler } from './composables/useSessionIssueHandler.ts'
import { EventBus } from './services/EventBus.ts'
import {
	leaveConversationSync,
	setGuestUserName
} from './services/participantsService.js'
import { signalingKill } from './utils/webrtc/index.js'

export default {

	name: 'PublicShareAuthSidebar',

	components: {
		InternalSignalingHint,
		CallView,
		ChatView,
		MediaSettings,
		PollManager,
		PollViewer,
		TopBar,
		TransitionWrapper,
	},

	setup() {
		useHashCheck()

		return {
			isLeavingAfterSessionIssue: useSessionIssueHandler(),
		}
	},

	data() {
		return {
			fetchCurrentConversationIntervalId: null,
			isWaitingToClose: false,
			recordingConsentGiven: false
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
			return this.conversation || this.isWaitingToClose
		},
	},

	watch: {
		token(token) {
			if (token) {
				this.joinConversation()
			}
		},

		conversation(conversation) {
			if (!conversation) {
				this.isWaitingToClose = true
				window.setTimeout(() => { this.isWaitingToClose = false }, 5000)
			}
		},
	},

	beforeMount() {
		window.addEventListener('unload', () => {
			console.info('Navigating away, leaving conversation')
			if (this.token) {
				// We have to do this synchronously, because in unload and beforeunload
				// Promises, async and await are prohibited.
				signalingKill()
				if (!this.isLeavingAfterSessionIssue) {
					leaveConversationSync(this.token)
				}
			}
		})
	},

	methods: {
		t,

		async joinConversation() {
			const currentUser = getCurrentUser()
			const guestNickname = getGuestNickname()

			if (currentUser) {
				this.$store.dispatch('setCurrentUser', currentUser)
			} else if (guestNickname) {
				this.$store.dispatch('setDisplayName', guestNickname)
			} else {
				subscribe('talk:guest-name:added', this.showGuestMediaSettings)
			}

			await this.$store.dispatch('joinConversation', { token: this.token })

			// Add guest name to the store, only possible after joining the conversation
			if (guestNickname) {
				await setGuestUserName(this.token, guestNickname)
			}

			// Fetching the conversation needs to be done once the user has
			// joined the conversation (otherwise only limited data would be
			// received if the user was not a participant of the conversation
			// yet).
			await this.fetchCurrentConversation()

			// FIXME The participant will not be updated with the server data
			// when the conversation is got again (as "addParticipantOnce" is
			// used), although that should not be a problem given that only the
			// "inCall" flag (which is locally updated when joining and leaving
			// a call) is currently used.
			if (loadState('spreed', 'signaling_mode') !== 'internal') {
				EventBus.on('should-refresh-conversations', this.fetchCurrentConversation)
				EventBus.on('signaling-participant-list-changed', this.fetchCurrentConversation)
			} else {
				// The "should-refresh-conversations" event is triggered only when
				// the external signaling server is used; when the internal
				// signaling server is used periodic polling has to be used
				// instead.
				this.fetchCurrentConversationIntervalId = window.setInterval(this.fetchCurrentConversation, 30000)
			}

			if (currentUser || guestNickname) {
				// Joining the call needs to be done once the participant identifier
				// has been set, which is done once the conversation has been
				// fetched. MediaSettings are called to set up audio and video devices
				// and also to give a consent to recording, if set up
				emit('talk:media-settings:show', 'video-verification')
			}
		},

		async fetchCurrentConversation() {
			if (!this.token) {
				return
			}

			try {
				await this.$store.dispatch('fetchConversation', { token: this.token })

				// Although the current participant is automatically added to
				// the participants store it must be explicitly set in the
				// actors store.
				if (!this.$store.getters.getUserId()) {
					// Set the current actor/participant for guests
					const conversation = this.$store.getters.conversation(this.token)

					// Setting a guest only uses "sessionId" and "participantType".
					this.$store.dispatch('setCurrentParticipant', conversation)
				}
			} catch (exception) {
				window.clearInterval(this.fetchCurrentConversationIntervalId)

				this.$store.dispatch('deleteConversation', this.token)
				this.$store.dispatch('updateToken', '')
			}
		},

		async showGuestMediaSettings() {
			// Guest needs to add their display name right after joining conversation,
			// before fetching and showing media settings. Then, this latter will be triggered
			// by the guest name addition event.
			emit('talk:media-settings:show', 'video-verification')
			unsubscribe('talk:guest-name:added', this.showGuestMediaSettings)
		}
	},
}
</script>

<style lang="css">
#talk-sidebar,
#talk-sidebar *,
#talk-sidebar *::before,
#talk-sidebar *::after {
	box-sizing: border-box;
}

 /* FIXME: remove after https://github.com/nextcloud-libraries/nextcloud-vue/pull/4959 is released */
body .modal-wrapper * {
	box-sizing: border-box;
}
</style>

<style lang="scss" scoped>
@import './assets/variables';

/* Styles based on the NcAppSidebar */
#talk-sidebar {
	position: relative;
	flex-shrink: 0;
	width: clamp(300px, 27vw, 500px);
	height: 100%;

	background: var(--color-main-background);
	border-inline-start: 1px solid var(--color-border);

	overflow-x: hidden;
	overflow-y: auto;
	z-index: 1500;

	display: flex;
	flex-direction: column;
	justify-content: center;

	/* Unset conflicting rules from guest.css for the sidebar. */
	text-align: start;

	& > .emptycontent {
		/* Remove default margin-top as it is unneeded when showing only the empty
		 * content in a flex sidebar. */
		margin-top: 0;
	}

	& #call-container {
		position: relative;

		flex-grow: 1;

		/* Prevent shadows of videos from leaking on other elements. */
		overflow: hidden;

		/* Distribute available height between call container and chat view. */
		height: 40%;

		/* Ensure that the background will be black also in voice only calls. */
		background-color: $color-call-background;

		:deep(.videoContainer.promoted video) {
			/* Base the size of the video on its width instead of on its height;
			 * otherwise the video could appear in full height but cropped on the sides
			 * due to the space available in the sidebar being typically larger in
			 * vertical than in horizontal. */
			width: 100%;
			height: auto;
		}
	}

	& .chatView {
		display: flex;
		flex-direction: column;
		overflow: hidden;
		position: relative;

		flex-grow: 1;

		/* Distribute available height between call container and chat view. */
		height: 60%;
	}

	& :deep(.wrapper) {
		margin-top: 0;
	}

	/* Restore rules from style.scss overwritten by guest.css for the sidebar. */
	& :deep(a) {
		color: var(--color-main-text);
		font-weight: inherit;
	}
}
</style>
