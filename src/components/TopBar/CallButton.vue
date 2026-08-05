<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script>
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import IconPhone from 'vue-material-design-icons/Phone.vue' // Filled used for non-silent calls
import IconPhoneDialOutline from 'vue-material-design-icons/PhoneDialOutline.vue'
import IconPhoneOutline from 'vue-material-design-icons/PhoneOutline.vue'
import { useGetToken } from '../../composables/useGetToken.ts'
import { useIsInCall } from '../../composables/useIsInCall.js'
import { useJoinCall } from '../../composables/useJoinCall.ts'
import { CALL, CONVERSATION } from '../../constants.ts'
import { getTalkConfig, hasTalkFeature } from '../../services/CapabilitiesManager.ts'
import { useCallViewStore } from '../../stores/callView.ts'
import { useSettingsStore } from '../../stores/settings.ts'
import { useSoundsStore } from '../../stores/sounds.js'
import { useTalkHashStore } from '../../stores/talkHash.js'
import { useTokenStore } from '../../stores/token.ts'
import { blockCalls, unsupportedWarning } from '../../utils/browserCheck.ts'
import { hasExternalCallService, isConversationPhoneRoom } from '../../utils/conversation.ts'
import { messagePleaseReload } from '../../utils/talkDesktopUtils.ts'

export default {
	name: 'CallButton',

	components: {
		NcButton,
		// Icons
		IconPhone,
		IconPhoneDialOutline,
		IconPhoneOutline,
		NcLoadingIcon,
	},

	props: {
		disabled: {
			type: Boolean,
			default: false,
		},

		/**
		 * Whether the component is used in MediaSettings or not
		 * (when click will directly start a call)
		 */
		isMediaSettings: {
			type: Boolean,
			default: false,
		},

		/**
		 * Whether the call should trigger a notifications and sound
		 * for other participants or not
		 */
		silentCall: {
			type: Boolean,
			default: false,
		},

		isRecordingFromStart: {
			type: Boolean,
			default: false,
		},

		recordingConsentGiven: {
			type: Boolean,
			default: false,
		},

		/**
		 * Whether to use text on button (e.g. at sidebar)
		 */
		hideText: {
			type: Boolean,
			default: false,
		},

		/**
		 * Whether to use text on button at mobile view
		 */
		shrinkOnMobile: {
			type: Boolean,
			default: false,
		},
	},

	setup() {
		const { joinCall } = useJoinCall()
		return {
			tokenStore: useTokenStore(),
			token: useGetToken(),
			isInCall: useIsInCall(),
			callViewStore: useCallViewStore(),
			talkHashStore: useTalkHashStore(),
			settingsStore: useSettingsStore(),
			soundsStore: useSoundsStore(),
			isMobile: useIsMobile(),
			joinCall,
		}
	},

	data() {
		return {
			loading: false,
		}
	},

	computed: {
		isNextcloudTalkHashDirty() {
			return this.talkHashStore.isNextcloudTalkHashDirty
				|| this.talkHashStore.isNextcloudTalkProxyHashDirty[this.token]
		},

		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		showButtonText() {
			return !this.hideText && (!this.isMobile || !this.shrinkOnMobile)
		},

		showRecordingWarning() {
			return [
				CALL.RECORDING.VIDEO_STARTING,
				CALL.RECORDING.AUDIO_STARTING,
				CALL.RECORDING.VIDEO,
				CALL.RECORDING.AUDIO,
			].includes(this.conversation.callRecording)
			|| this.conversation.recordingConsent === CALL.RECORDING_CONSENT.ENABLED
		},

		showMediaSettings() {
			return this.settingsStore.showMediaSettings
		},

		hasCall() {
			return this.conversation.hasCall
		},

		startCallButtonDisabled() {
			return this.disabled
				|| (this.callViewStore.callHasJustEnded && !this.hasCall)
				|| (!this.conversation.canStartCall && !hasExternalCallService(this.conversation) && !this.hasCall)
				|| this.isInLobby
				|| this.conversation.readOnly
				|| this.isNextcloudTalkHashDirty
				|| !this.tokenStore.currentConversationIsJoined
				|| blockCalls
		},

		startCallLabel() {
			if (this.hasCall && !this.isInLobby) {
				return t('spreed', 'Join call')
			}

			if (this.isJoiningCall) {
				return t('spreed', 'Connecting …')
			}

			return this.silentCall ? t('spreed', 'Start call silently') : t('spreed', 'Start call')
		},

		startCallTitle() {
			if (this.isNextcloudTalkHashDirty) {
				return t('spreed', 'The server was updated, you cannot start or join a call.') + ' ' + messagePleaseReload
			}

			if (this.callViewStore.callHasJustEnded) {
				return t('spreed', 'This call has just ended')
			}

			if (blockCalls) {
				return unsupportedWarning
			}

			if (!this.conversation.canStartCall && !this.hasCall) {
				return t('spreed', 'You will be able to join the call only after a moderator starts it.')
			}

			return ''
		},

		showStartCallButton() {
			return (getTalkConfig(this.token, 'call', 'enabled') || hasExternalCallService(this.conversation))
				&& this.conversation.type !== CONVERSATION.TYPE.NOTE_TO_SELF
				&& this.conversation.readOnly === CONVERSATION.STATE.READ_WRITE
				&& (!this.conversation.remoteServer || hasTalkFeature(this.token, 'federation-v2'))
				&& !this.isInCall
		},

		isPhoneRoom() {
			return isConversationPhoneRoom(this.conversation)
		},

		isInLobby() {
			return this.$store.getters.isInLobby
		},

		isJoiningCall() {
			return this.$store.getters.isJoiningCall(this.token)
		},
	},

	watch: {
		token(newValue, oldValue) {
			this.callViewStore.resetCallHasJustEnded()
			this.talkHashStore.resetTalkProxyHashDirty(oldValue)
		},
	},

	methods: {
		t,

		async handleJoinCall() {
			this.loading = true
			await this.joinCall(this.token, {
				silent: this.hasCall ? true : this.silentCall,
				recordingConsent: this.recordingConsentGiven,
				shouldStartRecording: this.isRecordingFromStart,
			})
			this.loading = false
		},

		handleClick() {
			if (hasExternalCallService(this.conversation)) {
				// Another service is in charge, trigger iframe rendering in MainView
				this.handleExternalCall()
				return
			}

			// Create audio objects as a result of a user interaction to allow playing sounds in Safari
			this.soundsStore.initAudioObjects()

			if (this.isMediaSettings || this.isPhoneRoom) {
				this.handleJoinCall()
				return
			}

			if (this.showRecordingWarning || this.showMediaSettings) {
				emit('talk:media-settings:show')
			} else {
				this.handleJoinCall()
			}
		},

		async handleExternalCall() {
			try {
				this.loading = true
				const response = await axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/external-call', { token: this.token }))

				// Check for successful response (200, 302, 303)
				if ([200, 302, 303].includes(response.status)) {
					const callUrl = response.data.ocs.data.url
					if (callUrl) {
						this.callViewStore.setExternalCallServiceUrl(callUrl)
					}
					this.callViewStore.setForceCallView(true)
				}
			} catch (error) {
				if (error.response?.status === 403) {
					this.skipLeaveWarning = true
					this.$router.push({ name: 'forbidden' })
					return
				}
				console.error('Failed to initialize external call service:', error)
				showError(t('spreed', 'Connection failed'))
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<template>
	<NcButton
		v-if="showStartCallButton"
		:title="startCallTitle"
		:aria-label="startCallLabel"
		:disabled="startCallButtonDisabled || loading || isJoiningCall"
		class="join-call"
		:variant="hasCall ? 'success' : 'primary'"
		@click="handleClick">
		<template #icon>
			<NcLoadingIcon v-if="isJoiningCall || loading" :size="20" />
			<IconPhoneDialOutline v-else-if="isPhoneRoom" :size="20" />
			<IconPhoneOutline v-else-if="silentCall" :size="20" />
			<IconPhone v-else :size="20" />
		</template>
		<template v-if="showButtonText" #default>
			{{ startCallLabel }}
		</template>
	</NcButton>
</template>

<style lang="scss" scoped>
.join-call.button-vue--success {
	// Overwrite default button colors for joining call
	--join-call-background-color: var(--color-border-success);
	--join-call-border-color: var(--color-success-text);
	border-color: var(--join-call-border-color);
	background-color: var(--join-call-background-color);
	color: var(--color-primary-element-text) !important;

	// Do not overwrite for dark theme
	body[data-theme-dark] & {
		--join-call-border-color: var(--color-success-hover);
	}
	@media (prefers-color-scheme: dark) {
		body[data-theme-default] & {
			--join-call-border-color: var(--color-success-hover);
		}
	}

	&:hover:not(:disabled) {
		background-color: var(--join-call-border-color);
	}
}
</style>
