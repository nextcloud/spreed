<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

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

	<NcButton
		v-else-if="showLeaveCallButton && canEndForAll && isPhoneRoom"
		:aria-label="endCallLabel"
		class="leave-call"
		variant="error"
		:disabled="loading"
		@click="leaveCall(true)">
		<template #icon>
			<NcLoadingIcon v-if="loading" :size="20" />
			<IconPhoneHangupOutline v-else :size="20" />
		</template>
		<template v-if="showButtonText" #default>
			{{ endCallLabel }}
		</template>
	</NcButton>
	<NcButton
		v-else-if="showLeaveCallButton && !canEndForAll && !isBreakoutRoom"
		:aria-label="leaveCallLabel"
		class="leave-call"
		:variant="isScreensharing ? 'tertiary' : 'error'"
		:disabled="loading"
		@click="leaveCall(false)">
		<template #icon>
			<NcLoadingIcon v-if="loading" :size="20" />
			<IconPhoneHangupOutline v-else :size="20" />
		</template>
		<template v-if="showButtonText" #default>
			{{ leaveCallLabel }}
		</template>
	</NcButton>
	<NcActions
		v-else-if="showLeaveCallButton && (canEndForAll || isBreakoutRoom)"
		class="leave-call leave-call-actions--split"
		:disabled="loading"
		:force-name="showButtonText"
		placement="top-end"
		:aria-label="leaveCallActionsLabel"
		:inline="1"
		:variant="leaveCallButtonVariant">
		<template #icon>
			<IconChevronUp :size="20" />
		</template>
		<NcActionButton
			v-if="isBreakoutRoom"
			:aria-label="backToMainRoomLabel"
			@click="switchToParentRoom">
			<template #icon>
				<IconArrowLeft class="bidirectional-icon" :size="20" />
			</template>
			<template v-if="showButtonText" #default>
				{{ backToMainRoomLabel }}
			</template>
		</NcActionButton>
		<NcActionButton
			class="leave-call-button--split"
			:aria-label="leaveCallLabel"
			@click="leaveCall(false)">
			<template #icon>
				<NcLoadingIcon v-if="loading" :size="20" />
				<IconPhoneHangupOutline v-else :size="20" />
			</template>
			<template v-if="showButtonText || isBreakoutRoom" #default>
				{{ leaveCallLabel }}
			</template>
		</NcActionButton>
		<NcActionButton v-if="canEndForAll" @click="leaveCall(true)">
			<template #icon>
				<IconPhoneOffOutline :size="20" />
			</template>
			{{ t('spreed', 'End call for everyone') }}
		</NcActionButton>
	</NcActions>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import IconArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import IconChevronUp from 'vue-material-design-icons/ChevronUp.vue'
import IconPhone from 'vue-material-design-icons/Phone.vue' // Filled used for non-silent calls
import IconPhoneDialOutline from 'vue-material-design-icons/PhoneDialOutline.vue'
import IconPhoneHangupOutline from 'vue-material-design-icons/PhoneHangupOutline.vue'
import IconPhoneOffOutline from 'vue-material-design-icons/PhoneOffOutline.vue'
import IconPhoneOutline from 'vue-material-design-icons/PhoneOutline.vue'
import { useGetToken } from '../../composables/useGetToken.ts'
import { useIsInCall } from '../../composables/useIsInCall.js'
import { ATTENDEE, CALL, CONVERSATION, PARTICIPANT } from '../../constants.ts'
import { callSIPDialOut } from '../../services/callsService.ts'
import { hasTalkFeature } from '../../services/CapabilitiesManager.ts'
import { EventBus } from '../../services/EventBus.ts'
import { useActorStore } from '../../stores/actor.ts'
import { useBreakoutRoomsStore } from '../../stores/breakoutRooms.ts'
import { useCallViewStore } from '../../stores/callView.ts'
import { useSettingsStore } from '../../stores/settings.ts'
import { useSoundsStore } from '../../stores/sounds.js'
import { useTalkHashStore } from '../../stores/talkHash.js'
import { useTokenStore } from '../../stores/token.ts'
import { blockCalls, unsupportedWarning } from '../../utils/browserCheck.ts'
import { messagePleaseReload } from '../../utils/talkDesktopUtils.ts'

export default {
	name: 'CallButton',

	components: {
		NcActions,
		NcActionButton,
		NcButton,
		// Icons
		IconArrowLeft,
		IconChevronUp,
		IconPhone,
		IconPhoneDialOutline,
		IconPhoneHangupOutline,
		IconPhoneOffOutline,
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

		isScreensharing: {
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
		return {
			actorStore: useActorStore(),
			tokenStore: useTokenStore(),
			token: useGetToken(),
			isInCall: useIsInCall(),
			breakoutRoomsStore: useBreakoutRoomsStore(),
			callViewStore: useCallViewStore(),
			talkHashStore: useTalkHashStore(),
			settingsStore: useSettingsStore(),
			soundsStore: useSoundsStore(),
			isMobile: useIsMobile(),
		}
	},

	data() {
		return {
			loading: false,
			callEnabled: false,
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
			return [CALL.RECORDING.VIDEO_STARTING,
				CALL.RECORDING.AUDIO_STARTING,
				CALL.RECORDING.VIDEO,
				CALL.RECORDING.AUDIO].includes(this.conversation.callRecording)
				|| this.conversation.recordingConsent === CALL.RECORDING_CONSENT.ENABLED
		},

		showMediaSettings() {
			return this.settingsStore.showMediaSettings
		},

		participantType() {
			return this.conversation.participantType
		},

		canEndForAll() {
			return (this.participantType === PARTICIPANT.TYPE.OWNER
				|| this.participantType === PARTICIPANT.TYPE.MODERATOR
				|| this.participantType === PARTICIPANT.TYPE.GUEST_MODERATOR)
			&& !this.isBreakoutRoom
		},

		hasCall() {
			return this.conversation.hasCall
		},

		startCallButtonDisabled() {
			return this.disabled
				|| (this.callViewStore.callHasJustEnded && !this.hasCall)
				|| (!this.conversation.canStartCall && !this.hasCall)
				|| this.isInLobby
				|| this.conversation.readOnly
				|| this.isNextcloudTalkHashDirty
				|| !this.tokenStore.currentConversationIsJoined
				|| blockCalls
		},

		leaveCallLabel() {
			return t('spreed', 'Leave call')
		},

		backToMainRoomLabel() {
			return t('spreed', 'Back to main room')
		},

		leaveCallActionsLabel() {
			return t('spreed', 'More actions')
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

		endCallLabel() {
			return t('spreed', 'End call')
		},

		startCallTitle() {
			if (this.isNextcloudTalkHashDirty) {
				return t('spreed', 'Nextcloud Talk was updated, you cannot start or join a call.') + ' ' + messagePleaseReload
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
			return this.callEnabled
				&& this.conversation.type !== CONVERSATION.TYPE.NOTE_TO_SELF
				&& this.conversation.readOnly === CONVERSATION.STATE.READ_WRITE
				&& (!this.conversation.remoteServer || hasTalkFeature(this.token, 'federation-v2'))
				&& !this.isInCall
		},

		showLeaveCallButton() {
			return this.conversation.readOnly === CONVERSATION.STATE.READ_WRITE
				&& this.isInCall
		},

		isBreakoutRoom() {
			return this.conversation.objectType === CONVERSATION.OBJECT_TYPE.BREAKOUT_ROOM
		},

		isPhoneRoom() {
			return this.conversation.objectId === CONVERSATION.OBJECT_ID.PHONE_OUTGOING
				&& (this.conversation.objectType === CONVERSATION.OBJECT_TYPE.PHONE_LEGACY
					|| this.conversation.objectType === CONVERSATION.OBJECT_TYPE.PHONE_PERSISTENT
					|| this.conversation.objectType === CONVERSATION.OBJECT_TYPE.PHONE_TEMPORARY)
		},

		isInLobby() {
			return this.$store.getters.isInLobby
		},

		isJoiningCall() {
			return this.$store.getters.isJoiningCall(this.token)
		},

		leaveCallButtonVariant() {
			if (this.isScreensharing) {
				return 'tertiary'
			}
			return this.isBreakoutRoom ? 'primary' : 'error'
		},
	},

	watch: {
		token(newValue, oldValue) {
			this.callViewStore.resetCallHasJustEnded()
			this.talkHashStore.resetTalkProxyHashDirty(oldValue)
		},
	},

	mounted() {
		this.callEnabled = loadState('spreed', 'call_enabled')
	},

	methods: {
		t,
		isParticipantTypeModerator(participantType) {
			return [PARTICIPANT.TYPE.OWNER, PARTICIPANT.TYPE.MODERATOR, PARTICIPANT.TYPE.GUEST_MODERATOR].includes(participantType)
		},

		/**
		 * Starts or joins a call
		 */
		async joinCall() {
			let flags = PARTICIPANT.CALL_FLAG.IN_CALL
			if (this.conversation.permissions & PARTICIPANT.PERMISSIONS.PUBLISH_AUDIO) {
				flags |= PARTICIPANT.CALL_FLAG.WITH_AUDIO
			}
			if (this.conversation.permissions & PARTICIPANT.PERMISSIONS.PUBLISH_VIDEO && !this.isPhoneRoom) {
				flags |= PARTICIPANT.CALL_FLAG.WITH_VIDEO
			}

			console.info('Joining call')
			this.loading = true
			// Close navigation
			emit('toggle-navigation', {
				open: false,
			})
			await this.$store.dispatch('joinCall', {
				token: this.token,
				participantIdentifier: this.actorStore.participantIdentifier,
				flags,
				silent: this.hasCall ? true : this.silentCall,
				recordingConsent: this.recordingConsentGiven,
			})
			this.loading = false

			if (this.isRecordingFromStart) {
				this.$store.dispatch('startCallRecording', {
					token: this.token,
					callRecording: CALL.RECORDING.VIDEO,
				})
			}

			if (this.isPhoneRoom) {
				const attendeeId = this.$store.getters.participantsList(this.token)
					.find((participant) => participant.actorType === ATTENDEE.ACTOR_TYPE.PHONES)
					?.attendeeId
				if (attendeeId) {
					this.dialOutPhoneNumber(attendeeId)
				}
			}
		},

		async leaveCall(endMeetingForAll = false) {
			if (endMeetingForAll) {
				console.info('End meeting for everyone')
			} else {
				console.info('Leaving call')
			}

			// Remove selected participant
			this.callViewStore.setSelectedVideoPeerId(null)
			this.loading = true

			// Open navigation
			if (!this.isMobile) {
				emit('toggle-navigation', {
					open: true,
				})
			}
			await this.$store.dispatch('leaveCall', {
				token: this.token,
				participantIdentifier: this.actorStore.participantIdentifier,
				all: endMeetingForAll,
			})
			this.loading = false
		},

		handleClick() {
			// Create audio objects as a result of a user interaction to allow playing sounds in Safari
			this.soundsStore.initAudioObjects()

			if (this.isMediaSettings || this.isPhoneRoom) {
				emit('talk:media-settings:hide')
				this.joinCall()
				return
			}

			if (this.showRecordingWarning || this.showMediaSettings) {
				emit('talk:media-settings:show')
			} else {
				emit('talk:media-settings:hide')
				this.joinCall()
			}
		},

		async switchToParentRoom() {
			EventBus.emit('switch-to-conversation', {
				token: this.breakoutRoomsStore.getParentRoomToken(this.token),
			})
		},

		async dialOutPhoneNumber(attendeeId) {
			try {
				await callSIPDialOut(this.token, attendeeId)
			} catch (error) {
				if (error?.response?.data?.ocs?.data?.message) {
					showError(t('spreed', 'Phone number could not be called: {error}', {
						error: error?.response?.data?.ocs?.data?.message,
					}))
				} else {
					console.error(error)
					showError(t('spreed', 'Phone number could not be called'))
				}
			}
		},
	},
}
</script>

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

.leave-call.button-vue--error,
.leave-call :deep(.button-vue--error) {
	// Overwrite default button colors for leaving call
	background-color: #FF3333 !important; // Nextcloud 31 --color-error
	color: var(--color-primary-text) !important;

	&:hover:not(:disabled) {
		background-color: var(--color-error-hover) !important;
	}
}

.leave-call-actions--split {
	gap: 1px !important;
}

.leave-call-actions--split :deep(.action-item--single) {
	border-start-end-radius: 2px;
	border-end-end-radius: 2px;
}

.leave-call-actions--split :deep(.action-item__menutoggle) {
	--button-size: var(--clickable-area-small);
	height: var(--default-clickable-area);
	border-start-start-radius: 2px;
	border-end-start-radius: 2px;
}

</style>
