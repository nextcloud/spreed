<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div id="talk-sidebar">
		<TopBar v-if="isInCall" isInCall isSidebar />
		<CallView v-if="isInCall" :token="token" isSidebar />
		<div v-else>Loading...</div>
		<CallFailedDialog v-if="connectionFailed" :token="token" />
		<MediaSettings v-model:recordingConsentGiven="recordingConsentGiven" />
	</div>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import { showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import CallFailedDialog from './components/CallView/CallFailedDialog.vue'
import CallView from './components/CallView/CallView.vue'
import MediaSettings from './components/MediaSettings/MediaSettings.vue'
import CallButton from './components/TopBar/CallButton.vue'
import TopBar from './components/TopBar/TopBar.vue'
import { useGetMessagesProvider } from './composables/useGetMessages.ts'
import { useHashCheck } from './composables/useHashCheck.js'
import { useIsInCall } from './composables/useIsInCall.js'
import { useSessionIssueHandler } from './composables/useSessionIssueHandler.ts'
import { EventBus } from './services/EventBus.ts'
import {
	leaveConversationSync,
} from './services/participantsService.js'
import { useActorStore } from './stores/actor.ts'
import { useTokenStore } from './stores/token.ts'
import { checkBrowser } from './utils/browserCheck.ts'
import { signalingKill } from './utils/webrtc/index.js'
import {PARTICIPANT} from "./constants";

export default {
	name: 'FloatingCallOverlay',

	components: {
		CallButton,
		CallFailedDialog,
		CallView,
		MediaSettings,
		TopBar,
	},

	props: {
		token: {
			type: String,
			required: true,
		},

		state: {
			type: Object,
			required: true,
		},
	},

	setup() {
		useHashCheck()
		useGetMessagesProvider()

		return {
			isInCall: useIsInCall(),
			isLeavingAfterSessionIssue: useSessionIssueHandler(),
			actorStore: useActorStore(),
			tokenStore: useTokenStore(),
		}
	},

	data() {
		return {
			fetchCurrentConversationIntervalId: null,
			joiningConversation: false,
			recordingConsentGiven: false,
		}
	},

	computed: {
		conversation() {
			console.log(this.$store, this.token, this.$store.getters.conversation(this.token))
			return this.$store.getters.conversation(this.token)
		},

		isOpen() {
			return this.state.isOpen
		},

		warnLeaving() {
			return !this.isLeavingAfterSessionIssue && this.isInCall
		},

		connectionFailed() {
			return this.$store.getters.connectionFailed(this.token)
		},
	},

	watch: {
		isInCall(newValue) {
			if (!newValue) {
				// end the call, unmount app
				window.OCA.Talk.unmountFloatingApp()
			}
		},
	},

	created() {
		window.addEventListener('beforeunload', this.preventUnload)
	},

	beforeMount() {
		window.addEventListener('unload', () => {
			if (this.token) {
				// We have to do this synchronously, because in unload and beforeunload
				// Promises, async and await are prohibited.
				signalingKill()
				if (!this.isLeavingAfterSessionIssue) {
					leaveConversationSync(this.token)
				}
			}
		})
		this.joinConversation()
	},

	beforeUnmount() {
		window.clearInterval(this.fetchCurrentConversationIntervalId)
		EventBus.off('should-refresh-conversations', this.fetchCurrentConversation)
		EventBus.off('signaling-participant-list-changed', this.fetchCurrentConversation)
		this.fetchCurrentConversationIntervalId = null
		window.removeEventListener('beforeunload', this.preventUnload)
	},

	methods: {
		t,
		preventUnload(event) {
			if (!this.warnLeaving) {
				return
			}

			event.preventDefault()
		},

		async joinConversation() {
			checkBrowser()

			this.joiningConversation = true

			try {
				this.tokenStore.updateToken(this.token)
				this.actorStore.setCurrentUser(getCurrentUser())

				await this.$router.push({ name: 'conversation', params: { token: this.token } })
				await this.$store.dispatch('joinConversation', { token: this.token })
			} catch (exception) {
				this.joiningConversation = false

				showError(t('spreed', 'Error occurred when joining the conversation'))

				console.error(exception)

				return
			}

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
			if (loadState('spreed', 'signaling_mode', 'external') !== 'internal') {
				EventBus.on('should-refresh-conversations', this.fetchCurrentConversation)
				EventBus.on('signaling-participant-list-changed', this.fetchCurrentConversation)
			} else {
				// The "should-refresh-conversations" event is triggered only when
				// the external signaling server is used; when the internal
				// signaling server is used periodic polling has to be used
				// instead.
				this.fetchCurrentConversationIntervalId = window.setInterval(this.fetchCurrentConversation, 30000)
			}

			let flags = PARTICIPANT.CALL_FLAG.IN_CALL
			if (this.conversation.permissions & PARTICIPANT.PERMISSIONS.PUBLISH_AUDIO) {
				flags |= PARTICIPANT.CALL_FLAG.WITH_AUDIO
			}
			if (this.conversation.permissions & PARTICIPANT.PERMISSIONS.PUBLISH_VIDEO) {
				flags |= PARTICIPANT.CALL_FLAG.WITH_VIDEO
			}

			await this.$store.dispatch('joinCall', {
				token: this.token,
				participantIdentifier: this.actorStore.participantIdentifier,
				flags,
				silent: false,
				recordingConsent: false,
			})
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
				if (!this.actorStore.userId) {
					// Set the current actor/participant for guests
					const conversation = this.$store.getters.conversation(this.token)

					// Setting a guest only uses "sessionId" and "participantType".
					this.actorStore.setCurrentParticipant(conversation)
				}
			} catch (exception) {
				window.clearInterval(this.fetchCurrentConversationIntervalId)

				this.$store.dispatch('deleteConversation', this.token)
				this.tokenStore.updateToken('')
			}

			this.joiningConversation = false
		},
	},
}
</script>

<style>
footer {
	transition: width var(--animation-quick);
}

#content-vue:has(#talk-sidebar) ~ footer {
	width: calc(100% - 2 * var(--body-container-margin) - clamp(300px, 27vw, 500px));
}
</style>

<style lang="scss" scoped>
/* Properties based on the app-sidebar */
#talk-sidebar {
	height: 100%;
	position: relative;
	flex-shrink: 0;
	width: clamp(300px, 27vw, 500px);

	background: var(--color-main-background);
	border-inline-start: 1px solid var(--color-border);

	overflow-x: hidden;
	overflow-y: auto;
	z-index: 2000;

	display: flex;
	flex-direction: column;
	justify-content: center;
}

#talk-sidebar > .emptycontent {
	/* Remove default margin-top as it is unneeded when showing only the empty
	 * content in a flex sidebar. */
	margin-top: 0;
}

#talk-sidebar .call-button {
	margin: calc(var(--default-grid-baseline) * 2) auto;
}

#talk-sidebar .button-centered {
	/*
	 * When there is an icon the servers empty-content rule
	 * .emptycontent [class*="icon-"] is matching button-vue--icon-and-text
	 * setting the height to 64px, so we need to reset this.
	 */
	height: var(--default-clickable-area) !important;
	margin: 0 auto;
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

#talk-sidebar #call-container :deep(.videoContainer) {
	/* The video container has some small padding to prevent the video from
	 * reaching the edges, but it also uses "width: 100%", so the padding should
	 * be included in the full width of the element. */
	box-sizing: border-box;
}

#talk-sidebar #call-container :deep(.videoContainer.promoted video) {
	/* Base the size of the video on its width instead of on its height;
	 * otherwise the video could appear in full height but cropped on the sides
	 * due to the space available in the sidebar being typically larger in
	 * vertical than in horizontal. */
	width: 100%;
	height: auto;
}

#talk-sidebar #call-container :deep(.nameIndicator) {
	/* The name indicator has some small padding to prevent the name from
	 * reaching the edges, but it also uses "width: 100%", so the padding should
	 * be included in the full width of the element. */
	box-sizing: border-box;
}

#talk-sidebar .chatView {
	display: flex;
	flex-direction: column;
	overflow: hidden;
	position: relative;

	flex-grow: 1;

	/* Distribute available height between call container and chat view. */
	height: 50%;
}
</style>
