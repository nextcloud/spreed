<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<div>
		<Button v-if="showStartCallButton"
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
				<span class="icon"
					:class="startCallIcon" />
			</template>
			{{ startCallLabel }}
		</Button>
		<Button v-else-if="showLeaveCallButton && !canEndForAll"
			id="call_button"
			type="error"
			:disabled="loading"
			@click="leaveCall(false)">
			<template #icon>
				<span class="icon"
					:class="leaveCallIcon" />
			</template>
			{{ leaveCallLabel }}
		</Button>
		<Actions v-else-if="showLeaveCallButton && canEndForAll"
			:disabled="loading">
			<template slot="icon">
				<VideoOff :size="16"
					decorative />
				<span class="label">{{ leaveCallLabel }}</span>
				<MenuDown :size="16"
					decorative />
			</template>
			<ActionButton @click="leaveCall(false)">
				<VideoOff slot="icon"
					:size="20"
					decorative />
				{{ leaveCallLabel }}
			</ActionButton>
			<ActionButton @click="leaveCall(true)">
				<VideoOff slot="icon"
					:size="20"
					decorative />
				{{ t('spreed', 'End meeting for all') }}
			</ActionButton>
		</Actions>
	</div>
</template>

<script>
import { CONVERSATION, PARTICIPANT } from '../../constants'
import browserCheck from '../../mixins/browserCheck'
import isInCall from '../../mixins/isInCall'
import isInLobby from '../../mixins/isInLobby'
import participant from '../../mixins/participant'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'
import { emit } from '@nextcloud/event-bus'
import BrowserStorage from '../../services/BrowserStorage'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import VideoOff from 'vue-material-design-icons/VideoOff'
import MenuDown from 'vue-material-design-icons/MenuDown'
import Button from '@nextcloud/vue/dist/Components/Button'
export default {
	name: 'CallButton',

	directives: {
		Tooltip,
	},

	components: {
		Actions,
		ActionButton,
		VideoOff,
		MenuDown,
		Button,
	},

	mixins: [
		browserCheck,
		isInCall,
		isInLobby,
		participant,
	],

	props: {
		/**
		 * Skips the device checker dialog and joins or starts the call
		 * upon clicking the button
		 */
		forceJoinCall: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			loading: false,
		}
	},

	computed: {
		token() {
			return this.$store.getters.getToken()
		},
		isNextcloudTalkHashDirty() {
			return this.$store.getters.isNextcloudTalkHashDirty
		},

		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		participantType() {
			return this.conversation.participantType
		},

		canEndForAll() {
			return ((this.conversation.callPermissions !== PARTICIPANT.PERMISSIONS.DEFAULT
					&& (this.conversation.callPermissions & PARTICIPANT.PERMISSIONS.CALL_START) === 0)
				|| (this.conversation.defaultPermissions !== PARTICIPANT.PERMISSIONS.DEFAULT
					&& (this.conversation.defaultPermissions & PARTICIPANT.PERMISSIONS.CALL_START) === 0))
			 && (this.participantType === PARTICIPANT.TYPE.OWNER
				|| this.participantType === PARTICIPANT.TYPE.MODERATOR
				|| this.participantType === PARTICIPANT.TYPE.GUEST_MODERATOR)
		},

		hasCall() {
			return this.conversation.hasCall || this.conversation.hasCallOverwrittenByChat
		},

		startCallButtonDisabled() {
			return (!this.conversation.canStartCall
					&& !this.hasCall)
				|| this.isInLobby
				|| this.conversation.readOnly
				|| this.isNextcloudTalkHashDirty
				|| !this.currentConversationIsJoined
		},

		leaveCallLabel() {
			return t('spreed', 'Leave call')
		},

		leaveCallIcon() {
			if (this.loading) {
				return 'icon-loading-small'
			}

			return 'icon-leave-call'
		},

		startCallLabel() {
			if (this.hasCall && !this.isInLobby) {
				return t('spreed', 'Join call')
			}

			return t('spreed', 'Start call')
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

		startCallIcon() {
			if (this.loading) {
				return 'icon-loading-small'
			}

			if (this.hasCall && !this.isInLobby) {
				return 'icon-incoming-call'
			}

			return 'icon-start-call'
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
			return this.conversation.readOnly === CONVERSATION.STATE.READ_WRITE
				&& !this.isInCall
		},

		showLeaveCallButton() {
			return this.conversation.readOnly === CONVERSATION.STATE.READ_WRITE
				&& this.isInCall
		},

		currentConversationIsJoined() {
			return this.$store.getters.currentConversationIsJoined
		},
	},

	methods: {
		isParticipantTypeModerator(participantType) {
			return [PARTICIPANT.TYPE.OWNER, PARTICIPANT.TYPE.MODERATOR, PARTICIPANT.TYPE.GUEST_MODERATOR].indexOf(participantType) !== -1
		},

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
			})
			this.loading = false
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
			const shouldShowDeviceCheckerScreen = (BrowserStorage.getItem('showDeviceChecker' + this.token) === null
				|| BrowserStorage.getItem('showDeviceChecker' + this.token) === 'true') && !this.forceJoinCall
			console.debug(shouldShowDeviceCheckerScreen)
			if (shouldShowDeviceCheckerScreen) {
				emit('talk:device-checker:show')
			} else {
				emit('talk:device-checker:hide')
				this.joinCall()
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.success {
	color: white;
	background-color: var(--color-success);
	border: 1px solid var(--color-success);

	&:hover,
	&:focus,
	&:active {
		border: 1px solid var(--color-success) !important;
	}
}
#call_button {
	margin: 0 auto;
}
/** Required to make the text on the Video Verification page white */
#call_button.success,
#call_button.error {
	color: white !important;
}

/* HACK: to override the default action button styles to make it look like a regular button */
::v-deep .trigger > button {
	&,
	&:hover,
	&:focus,
	&:active {
		color: white;
		background-color: var(--color-error) !important;
		border: 1px solid var(--color-error) !important;
		padding: 0 16px;
		opacity: 1;
	}

	& > .label {
		margin: 0 8px;
	}
}
</style>
