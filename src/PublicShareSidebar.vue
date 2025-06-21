<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<TransitionWrapper name="slide-right">
		<aside v-if="isOpen" id="talk-sidebar">
			<div v-if="!conversation" class="emptycontent room-not-joined">
				<div class="icon icon-talk" />
				<h2>{{ t('spreed', 'Discuss this file') }}</h2>
				<NcButton variant="primary"
					class="button-centered"
					:disabled="joiningConversation"
					@click="joinConversation">
					<template #icon>
						<NcLoadingIcon v-if="joiningConversation" />
					</template>
					{{ t('spreed', 'Join conversation') }}
				</NcButton>
			</div>
			<template v-else>
				<TopBar v-if="isInCall" is-in-call is-sidebar />
				<CallView v-if="isInCall" :token="token" is-sidebar />
				<InternalSignalingHint />
				<CallButton v-if="!isInCall" class="call-button" />
				<CallFailedDialog v-if="connectionFailed" :token="token" />
				<ChatView is-sidebar />
				<PollManager />
				<PollViewer />
				<MediaSettings v-model:recording-consent-given="recordingConsentGiven" />
			</template>
		</aside>
	</TransitionWrapper>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import CallFailedDialog from './components/CallView/CallFailedDialog.vue'
import CallView from './components/CallView/CallView.vue'
import ChatView from './components/ChatView.vue'
import MediaSettings from './components/MediaSettings/MediaSettings.vue'
import PollManager from './components/PollViewer/PollManager.vue'
import PollViewer from './components/PollViewer/PollViewer.vue'
import InternalSignalingHint from './components/RightSidebar/InternalSignalingHint.vue'
import CallButton from './components/TopBar/CallButton.vue'
import TopBar from './components/TopBar/TopBar.vue'
import TransitionWrapper from './components/UIShared/TransitionWrapper.vue'
import { useGetToken } from './composables/useGetToken.ts'
import { useHashCheck } from './composables/useHashCheck.js'
import { useIsInCall } from './composables/useIsInCall.js'
import { useSessionIssueHandler } from './composables/useSessionIssueHandler.ts'
import { EventBus } from './services/EventBus.ts'
import { getPublicShareConversationData } from './services/filesIntegrationServices.ts'
import {
	leaveConversationSync,
} from './services/participantsService.js'
import { useActorStore } from './stores/actor.ts'
import { useTokenStore } from './stores/token.ts'
import { checkBrowser } from './utils/browserCheck.ts'
import { signalingKill } from './utils/webrtc/index.js'

export default {
	name: 'PublicShareSidebar',

	components: {
		InternalSignalingHint,
		CallButton,
		CallFailedDialog,
		CallView,
		ChatView,
		MediaSettings,
		NcButton,
		NcLoadingIcon,
		PollManager,
		PollViewer,
		TopBar,
		TransitionWrapper,
	},

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

	setup() {
		useHashCheck()

		return {
			isInCall: useIsInCall(),
			isLeavingAfterSessionIssue: useSessionIssueHandler(),
			actorStore: useActorStore(),
			token: useGetToken(),
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
	},

	beforeUnmount() {
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
				await this.getPublicShareConversationData()

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
		},

		async getPublicShareConversationData() {
			const response = await getPublicShareConversationData(this.shareToken)

			this.tokenStore.updateToken(response.data.ocs.data.token)

			if (response.data.ocs.data.userId) {
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
				this.actorStore.setCurrentUser({
					uid: response.data.ocs.data.userId,
					displayName: response.data.ocs.data.userDisplayName,
				})
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
/* FIXME: remove after https://github.com/nextcloud-libraries/nextcloud-vue/pull/4959 is released */
body .modal-wrapper * {
	box-sizing: border-box;
}

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
	position: relative;
	flex-shrink: 0;
	width: clamp(300px, 27vw, 500px);

	background: var(--color-main-background);
	border-inline-start: 1px solid var(--color-border);

	overflow-x: hidden;
	overflow-y: auto;
	z-index: 1500;

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
