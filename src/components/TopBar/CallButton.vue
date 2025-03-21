<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
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
	<div>
		<NcButton v-if="showStartCallButton"
			id="call_button"
			v-tooltip="{
				placement: 'auto',
				trigger: 'hover',
				content: startCallToolTip,
				autoHide: false,
				html: true
			}"
			:aria-label="startCallLabel"
			:disabled="startCallButtonDisabled || loading"
			:type="startCallButtonType"
			@click="handleClick">
			<template #icon>
				<PhoneIcon v-if="isPhoneRoom" :size="20" />
				<VideoOutlineIcon v-else-if="silentCall" :size="20" />
				<VideoIcon v-else :size="20" />
			</template>
			<template v-if="showButtonText" #default>
				{{ startCallLabel }}
			</template>
		</NcButton>
		<NcButton v-else-if="showLeaveCallButton && canEndForAll && isPhoneRoom"
			id="call_button"
			:aria-label="endCallLabel"
			type="error"
			:disabled="loading"
			@click="leaveCall(true)">
			<template #icon>
				<PhoneHangup :size="20" />
			</template>
			<template v-if="showButtonText" #default>
				{{ endCallLabel }}
			</template>
		</NcButton>
		<NcButton v-else-if="showLeaveCallButton && !canEndForAll && !isBreakoutRoom"
			id="call_button"
			:aria-label="leaveCallLabel"
			:type="isScreensharing ? 'tertiary' : 'error'"
			:disabled="loading"
			@click="leaveCall(false)">
			<template #icon>
				<VideoOff :size="20" />
			</template>
			<template v-if="showButtonText" #default>
				{{ leaveCallLabel }}
			</template>
		</NcButton>
		<NcActions v-else-if="showLeaveCallButton && (canEndForAll || isBreakoutRoom)"
			:disabled="loading"
			:aria-label="leaveCallCombinedLabel"
			:menu-name="showButtonText ? leaveCallCombinedLabel : undefined"
			force-name
			:container="container"
			:type="isScreensharing ? 'tertiary' : 'error'">
			<template #icon>
				<VideoOff v-if="!isBreakoutRoom" :size="20" />
				<ArrowLeft v-else :size="20" />
			</template>
			<NcActionButton v-if="isBreakoutRoom"
				@click="switchToParentRoom">
				<template #icon>
					<ArrowLeft :size="20" />
				</template>
				{{ backToMainRoomLabel }}
			</NcActionButton>
			<NcActionButton @click="leaveCall(false)">
				<template #icon>
					<VideoOff :size="20" />
				</template>
				{{ leaveCallLabel }}
			</NcActionButton>
			<NcActionButton v-if="canEndForAll" @click="leaveCall(true)">
				<template #icon>
					<VideoBoxOff :size="20" />
				</template>
				{{ t('spreed', 'End call for everyone') }}
			</NcActionButton>
		</NcActions>
	</div>
</template>

<script>
import ArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import PhoneIcon from 'vue-material-design-icons/Phone.vue'
import PhoneHangup from 'vue-material-design-icons/PhoneHangup.vue'
import VideoIcon from 'vue-material-design-icons/Video.vue'
import VideoBoxOff from 'vue-material-design-icons/VideoBoxOff.vue'
import VideoOff from 'vue-material-design-icons/VideoOff.vue'
import VideoOutlineIcon from 'vue-material-design-icons/VideoOutline.vue'

import { getCapabilities } from '@nextcloud/capabilities'
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import { useIsMobile } from '@nextcloud/vue/dist/Composables/useIsMobile.js'

import { useIsInCall } from '../../composables/useIsInCall.js'
import { ATTENDEE, CALL, CONVERSATION, PARTICIPANT } from '../../constants.js'
import { callSIPDialOut } from '../../services/callsService.js'
import { EventBus } from '../../services/EventBus.js'
import { useBreakoutRoomsStore } from '../../stores/breakoutRooms.ts'
import { useSettingsStore } from '../../stores/settings.js'
import { useTalkHashStore } from '../../stores/talkHash.js'
import { blockCalls, unsupportedWarning } from '../../utils/browserCheck.js'

const supportFederationV1 = getCapabilities()?.spreed?.features?.includes('federation-v1')

export default {
	name: 'CallButton',

	components: {
		NcActions,
		NcActionButton,
		NcButton,
		// Icons
		ArrowLeft,
		PhoneHangup,
		PhoneIcon,
		VideoBoxOff,
		VideoIcon,
		VideoOff,
		VideoOutlineIcon,
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
		 * Whether the to use text on button at mobile view
		 */
		shrinkOnMobile: {
			type: Boolean,
			default: false,
		},
	},

	setup() {
		return {
			isInCall: useIsInCall(),
			breakoutRoomsStore: useBreakoutRoomsStore(),
			talkHashStore: useTalkHashStore(),
			settingsStore: useSettingsStore(),
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
		token() {
			return this.$store.getters.getToken()
		},
		isNextcloudTalkHashDirty() {
			return this.talkHashStore.isNextcloudTalkHashDirty
		},
		container() {
			return this.$store.getters.getMainContainerSelector()
		},
		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},
		showButtonText() {
			return !this.isMobile || !this.shrinkOnMobile
		},
		showRecordingWarning() {
			return [CALL.RECORDING.VIDEO_STARTING, CALL.RECORDING.AUDIO_STARTING,
				CALL.RECORDING.VIDEO, CALL.RECORDING.AUDIO].includes(this.conversation.callRecording)
			|| this.conversation.recordingConsent === CALL.RECORDING_CONSENT.REQUIRED
		},

		showMediaSettings() {
			return this.settingsStore.getShowMediaSettings(this.token)
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
				|| (!this.conversation.canStartCall && !this.hasCall)
				|| this.isInLobby
				|| this.conversation.readOnly
				|| this.isNextcloudTalkHashDirty
				|| !this.currentConversationIsJoined
				|| blockCalls
		},

		leaveCallLabel() {
			return t('spreed', 'Leave call')
		},

		backToMainRoomLabel() {
			return t('spreed', 'Back to main room')
		},

		leaveCallCombinedLabel() {
			return this.leaveCallLabel + ' ▼'
		},

		startCallLabel() {
			if (this.hasCall && !this.isInLobby) {
				return t('spreed', 'Join call')
			}

			return this.silentCall ? t('spreed', 'Start call silently') : t('spreed', 'Start call')
		},

		endCallLabel() {
			return t('spreed', 'End call')
		},

		startCallToolTip() {
			if (this.isNextcloudTalkHashDirty) {
				return t('spreed', 'Nextcloud Talk was updated, you need to reload the page before you can start or join a call.')
			}

			if (this.callButtonTooltipText) {
				return this.callButtonTooltipText
			}

			if (!this.conversation.canStartCall && !this.hasCall) {
				return t('spreed', 'You will be able to join the call only after a moderator starts it.')
			}

			return ''
		},

		startCallButtonType() {
			if (!this.isInLobby) {
				if (!this.hasCall) {
					return 'primary'
				} else {
					return 'success'
				}
			}
			return ''
		},

		showStartCallButton() {
			return this.callEnabled
				&& this.conversation.type !== CONVERSATION.TYPE.NOTE_TO_SELF
				&& this.conversation.readOnly === CONVERSATION.STATE.READ_WRITE
				&& (!supportFederationV1 || !this.conversation.remoteServer)
				&& !this.isInCall
		},

		showLeaveCallButton() {
			return this.conversation.readOnly === CONVERSATION.STATE.READ_WRITE
				&& this.isInCall
		},

		currentConversationIsJoined() {
			return this.$store.getters.currentConversationIsJoined
		},

		isBreakoutRoom() {
			return this.conversation.objectType === CONVERSATION.OBJECT_TYPE.BREAKOUT_ROOM
		},

		isPhoneRoom() {
			return this.conversation.objectType === CONVERSATION.OBJECT_TYPE.PHONE
		},

		callButtonTooltipText() {
			if (blockCalls) {
				return unsupportedWarning
			} else {
				// Passing a falsy value into the content of the tooltip
				// is the only way to disable it conditionally.
				return false
			}
		},

		isInLobby() {
			return this.$store.getters.isInLobby
		},
	},

	mounted() {
		this.callEnabled = loadState('spreed', 'call_enabled')
	},

	methods: {
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
				participantIdentifier: this.$store.getters.getParticipantIdentifier(),
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
					.find(participant => participant.actorType === ATTENDEE.ACTOR_TYPE.PHONES)
					?.attendeeId
				this.dialOutPhoneNumber(attendeeId)
			}
		},

		async leaveCall(endMeetingForAll = false) {
			if (endMeetingForAll) {
				console.info('End meeting for everyone')
			} else {
				console.info('Leaving call')
			}

			// Remove selected participant
			this.$store.dispatch('selectedVideoPeerId', null)
			this.loading = true

			// Open navigation
			emit('toggle-navigation', {
				open: true,
			})
			await this.$store.dispatch('leaveCall', {
				token: this.token,
				participantIdentifier: this.$store.getters.getParticipantIdentifier(),
				all: endMeetingForAll,
			})
			this.loading = false
		},

		handleClick() {
			// Create audio objects as a result of a user interaction to allow playing sounds in Safari
			this.$store.dispatch('createAudioObjects')

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
			EventBus.$emit('switch-to-conversation', {
				token: this.breakoutRoomsStore.getParentRoomToken(this.token),
			})
		},

		async dialOutPhoneNumber(attendeeId) {
			try {
				await callSIPDialOut(this.token, attendeeId)
			} catch (error) {
				if (error?.response?.data?.ocs?.data?.message) {
					showError(t('spreed', 'Phone number could not be called: {error}', {
						error: error?.response?.data?.ocs?.data?.message
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
#call_button {
	margin: 0 auto;
}

</style>
