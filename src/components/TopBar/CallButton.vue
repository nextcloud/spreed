<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
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
			:disabled="startCallButtonDisabled || loading || blockCalls"
			:type="startCallButtonType"
			@click="handleClick">
			<template #icon>
				<VideoIcon :size="20" />
			</template>
			{{ startCallLabel }}
		</NcButton>
		<NcButton v-else-if="showLeaveCallButton && !canEndForAll && !isBreakoutRoom"
			id="call_button"
			type="error"
			:disabled="loading"
			@click="leaveCall(false)">
			<template #icon>
				<VideoOff :size="20" />
			</template>
			{{ leaveCallLabel }}
		</NcButton>
		<NcActions v-else-if="showLeaveCallButton && (canEndForAll || isBreakoutRoom)"
			:disabled="loading"
			:menu-name="leaveCallCombinedLabel"
			force-name
			:container="container"
			type="error">
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
import VideoIcon from 'vue-material-design-icons/Video.vue'
import VideoBoxOff from 'vue-material-design-icons/VideoBoxOff.vue'
import VideoOff from 'vue-material-design-icons/VideoOff.vue'

import { emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip.js'

import { useIsInCall } from '../../composables/useIsInCall.js'
import { CALL, CONVERSATION, PARTICIPANT } from '../../constants.js'
import browserCheck from '../../mixins/browserCheck.js'
import isInLobby from '../../mixins/isInLobby.js'
import participant from '../../mixins/participant.js'
import { EventBus } from '../../services/EventBus.js'
import { useSettingsStore } from '../../stores/settings.js'

export default {
	name: 'CallButton',

	directives: {
		Tooltip,
	},

	components: {
		NcActions,
		NcActionButton,
		VideoBoxOff,
		VideoIcon,
		VideoOff,
		NcButton,
		ArrowLeft,
	},

	mixins: [
		browserCheck,
		isInLobby,
		participant,
	],

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
	},

	setup() {
		const isInCall = useIsInCall()
		const settingsStore = useSettingsStore()
		return { isInCall, settingsStore }
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
			return this.$store.getters.isNextcloudTalkHashDirty
		},
		container() {
			return this.$store.getters.getMainContainerSelector()
		},
		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		isStartingRecording() {
			return this.conversation.callRecording === CALL.RECORDING.VIDEO_STARTING
				|| this.conversation.callRecording === CALL.RECORDING.AUDIO_STARTING
		},

		isRecording() {
			return this.conversation.callRecording === CALL.RECORDING.VIDEO
				|| this.conversation.callRecording === CALL.RECORDING.AUDIO
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
			return this.conversation.hasCall || this.conversation.hasCallOverwrittenByChat
		},

		startCallButtonDisabled() {
			return this.disabled
				|| (!this.conversation.canStartCall && !this.hasCall)
				|| this.isInLobby
				|| this.conversation.readOnly
				|| this.isNextcloudTalkHashDirty
				|| !this.currentConversationIsJoined
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
			return this.conversation.objectType === 'room'
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
			if (this.conversation.permissions & PARTICIPANT.PERMISSIONS.PUBLISH_VIDEO) {
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

			if (this.isMediaSettings) {
				emit('talk:media-settings:hide')
				this.joinCall()
				return
			}

			if (this.isStartingRecording || this.isRecording || this.showMediaSettings) {
				emit('talk:media-settings:show')
			} else {
				emit('talk:media-settings:hide')
				this.joinCall()
			}
		},

		async switchToParentRoom() {
			const parentRoomToken = this.$store.getters.parentRoomToken(this.token)
			EventBus.$emit('switch-to-conversation', {
				token: parentRoomToken,
			})
		},
	},
}
</script>

<style lang="scss" scoped>
#call_button {
	margin: 0 auto;
}
</style>
