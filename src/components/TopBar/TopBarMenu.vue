<!--
  - @copyright Copyright (c) 2022 Marco Ambrosini <marcoambrosini@icloud.com>
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
	<div class="top-bar__wrapper">
		<NcButton v-show="isInCall && isHandRaised"
			v-shortkey.once="disableKeyboardShortcuts ? null : ['r']"
			v-tooltip="raiseHandButtonLabel"
			:aria-label="raiseHandButtonLabel"
			type="tertiary-no-background"
			@shortkey="toggleHandRaised"
			@click.stop="toggleHandRaised">
			<template #icon>
				<!-- The following icon is much bigger than all the others
					so we reduce its size -->
				<HandBackLeft :size="18" fill-color="#ffffff" />
			</template>
		</NcButton>

		<NcActions v-if="!isSidebar"
			v-shortkey.once="disableKeyboardShortcuts ? null : ['f']"
			v-tooltip="t('spreed', 'Conversation actions')"
			:aria-label="t('spreed', 'Conversation actions')"
			:container="container"
			@shortkey.native="toggleFullscreen">
			<!-- Menu icon: white if in call -->
			<template v-if="isInCall" #icon>
				<DotsHorizontal :size="20"
					fill-color="#ffffff" />
			</template>
			<template v-if="showActions && isInCall">
				<!-- Raise hand -->
				<NcActionButton close-after-click
					@click="toggleHandRaised">
					<!-- The following icon is much bigger than all the others
					so we reduce its size -->
					<template #icon>
						<HandBackLeft :size="16" />
					</template>
					{{ raiseHandButtonLabel }}
				</NcActionButton>

				<!-- Mute others -->
				<template v-if="!isOneToOneConversation && canFullModerate">
					<NcActionButton close-after-click
						@click="forceMuteOthers">
						<template #icon>
							<MicrophoneOff :size="20" />
						</template>
						{{ t('spreed', 'Mute others') }}
					</NcActionButton>
				</template>

				<!-- Device settings -->
				<NcActionButton close-after-click
					@click="showMediaSettingsDialog">
					<template #icon>
						<VideoIcon :size="20" />
					</template>
					{{ t('spreed', 'Media settings') }}
				</NcActionButton>
				<NcActionSeparator />
			</template>

			<!-- Call layout switcher -->
			<NcActionButton v-if="showActions && isInCall"
				close-after-click
				@click="changeView">
				<template #icon>
					<GridView v-if="!isGrid"
						:size="20" />
					<PromotedView v-else
						:size="20" />
				</template>
				{{ changeViewText }}
			</NcActionButton>

			<!-- Fullscreen -->
			<NcActionButton :aria-label="t('spreed', 'Toggle full screen')"
				close-after-click
				@click="toggleFullscreen">
				<template #icon>
					<Fullscreen v-if="!isFullscreen" :size="20" />
					<FullscreenExit v-else :size="20" />
				</template>
				{{ labelFullscreen }}
			</NcActionButton>

			<!-- Go to file -->
			<NcActionLink v-if="isFileConversation"
				:href="linkToFile">
				<template #icon>
					<File :size="20" />
				</template>
				{{ t('spreed', 'Go to file') }}
			</NcActionLink>
			<!-- Call recording -->
			<template v-if="canModerateRecording">
				<NcActionButton v-if="!isRecording && !isStartingRecording && isInCall"
					close-after-click
					@click="startRecording">
					<template #icon>
						<RecordCircle :size="20" />
					</template>
					{{ t('spreed', 'Start recording') }}
				</NcActionButton>
				<NcActionButton v-else-if="isStartingRecording && isInCall"
					close-after-click
					@click="stopRecording">
					<template #icon>
						<NcLoadingIcon :size="20" />
					</template>
					{{ t('spreed', 'Cancel recording start') }}
				</NcActionButton>
				<NcActionButton v-else-if="isRecording && isInCall"
					close-after-click
					@click="stopRecording">
					<template #icon>
						<StopIcon :size="20" />
					</template>
					{{ t('spreed', 'Stop recording') }}
				</NcActionButton>
			</template>

			<!-- Breakout rooms -->
			<NcActionButton v-if="canConfigureBreakoutRooms"
				close-after-click
				@click="$emit('open-breakout-rooms-editor')">
				<template #icon>
					<DotsCircle :size="20" />
				</template>
				{{ t('spreed', 'Set up breakout rooms') }}
			</NcActionButton>

			<!-- Conversation settings -->
			<NcActionButton close-after-click
				@click="openConversationSettings">
				<template #icon>
					<Cog :size="20" />
				</template>
				{{ t('spreed', 'Conversation settings') }}
			</NcActionButton>
		</NcActions>
	</div>
</template>

<script>
import Cog from 'vue-material-design-icons/Cog.vue'
import DotsCircle from 'vue-material-design-icons/DotsCircle.vue'
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import Fullscreen from 'vue-material-design-icons/Fullscreen.vue'
import FullscreenExit from 'vue-material-design-icons/FullscreenExit.vue'
import HandBackLeft from 'vue-material-design-icons/HandBackLeft.vue'
import MicrophoneOff from 'vue-material-design-icons/MicrophoneOff.vue'
import RecordCircle from 'vue-material-design-icons/RecordCircle.vue'
import StopIcon from 'vue-material-design-icons/Stop.vue'
import VideoIcon from 'vue-material-design-icons/Video.vue'

import { getCapabilities } from '@nextcloud/capabilities'
import { emit } from '@nextcloud/event-bus'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActionLink from '@nextcloud/vue/dist/Components/NcActionLink.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionSeparator from '@nextcloud/vue/dist/Components/NcActionSeparator.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'

import GridView from '../missingMaterialDesignIcons/GridView.vue'
import PromotedView from '../missingMaterialDesignIcons/PromotedView.vue'

import { useIsInCall } from '../../composables/useIsInCall.js'
import { CALL, CONVERSATION, PARTICIPANT } from '../../constants.js'
import { generateAbsoluteUrl } from '../../services/urlService.js'
import { callParticipantCollection } from '../../utils/webrtc/index.js'

export default {
	name: 'TopBarMenu',

	components: {
		NcButton,
		NcActions,
		NcActionSeparator,
		NcActionLink,
		NcActionButton,
		NcLoadingIcon,
		PromotedView,
		Cog,
		DotsHorizontal,
		Fullscreen,
		FullscreenExit,
		GridView,
		HandBackLeft,
		VideoIcon,
		MicrophoneOff,
		RecordCircle,
		StopIcon,
		DotsCircle,
	},

	props: {
		/**
			* The conversation token
			*/
		token: {
			type: String,
			required: true,
		},

		/**
			* The local media model
			*/
		model: {
			type: Object,
			required: true,
		},

		showActions: {
			type: Boolean,
			default: true,
		},

		/**
			* In the sidebar the conversation settings are hidden
			*/
		isSidebar: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['open-breakout-rooms-editor'],

	setup() {
		const isInCall = useIsInCall()
		return { isInCall }
	},

	data() {
		return {
			boundaryElement: document.querySelector('.main-view'),
		}
	},

	computed: {
		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		isFullscreen() {
			return this.$store.getters.isFullscreen()
		},

		labelFullscreen() {
			if (this.isFullscreen) {
				return t('spreed', 'Exit full screen (F)')
			}
			return t('spreed', 'Full screen (F)')
		},

		isFileConversation() {
			return this.conversation.objectType === CONVERSATION.OBJECT_TYPE.FILE && this.conversation.objectId
		},

		linkToFile() {
			if (this.isFileConversation) {
				return generateAbsoluteUrl('/f/{objectId}', { objectId: this.conversation.objectId })
			} else {
				return ''
			}
		},

		isOneToOneConversation() {
			return this.conversation.type === CONVERSATION.TYPE.ONE_TO_ONE
				|| this.conversation.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER
		},

		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		changeViewText() {
			if (this.isGrid) {
				return t('spreed', 'Speaker view')
			} else {
				return t('spreed', 'Grid view')
			}
		},

		isGrid() {
			return this.$store.getters.isGrid
		},

		isVirtualBackgroundAvailable() {
			return this.model.attributes.virtualBackgroundAvailable
		},

		isVirtualBackgroundEnabled() {
			return this.model.attributes.virtualBackgroundEnabled
		},

		isHandRaised() {
			return this.model.attributes.raisedHand?.state === true
		},

		raiseHandButtonLabel() {
			if (!this.isHandRaised) {
				if (this.disableKeyboardShortcuts) {
					return t('spreed', 'Raise hand')
				}
				return t('spreed', 'Raise hand (R)')
			}
			if (this.disableKeyboardShortcuts) {
				return t('spreed', 'Lower hand')
			}
			return t('spreed', 'Lower hand (R)')
		},

		disableKeyboardShortcuts() {
			return OCP.Accessibility.disableKeyboardShortcuts()
		},

		participantType() {
			return this.conversation.participantType
		},

		canFullModerate() {
			return this.participantType === PARTICIPANT.TYPE.OWNER || this.participantType === PARTICIPANT.TYPE.MODERATOR
		},

		canModerate() {
			return this.canFullModerate || this.participantType === PARTICIPANT.TYPE.GUEST_MODERATOR
		},

		canModerateRecording() {
			const recordingEnabled = getCapabilities()?.spreed?.config?.call?.recording || false
			return this.canFullModerate && recordingEnabled
		},

		canConfigureBreakoutRooms() {
			if (this.conversation.type !== CONVERSATION.TYPE.GROUP || !this.canFullModerate) {
				return false
			}

			const breakoutRoomsEnabled = getCapabilities()?.spreed?.config?.call?.['breakout-rooms']
			return !!breakoutRoomsEnabled
		},

		isStartingRecording() {
			return this.conversation.callRecording === CALL.RECORDING.VIDEO_STARTING
				|| this.conversation.callRecording === CALL.RECORDING.AUDIO_STARTING
		},

		isRecording() {
			return this.conversation.callRecording === CALL.RECORDING.VIDEO
				|| this.conversation.callRecording === CALL.RECORDING.AUDIO
		},

		// True if current conversation is a breakout room and the breakout room has started
		// And a call is in progress
		userIsInBreakoutRoomAndInCall() {
			return this.conversation.breakoutRoomMode === CONVERSATION.BREAKOUT_ROOM_MODE.NOT_CONFIGURED
				&& this.conversation.breakoutRoomStatus === CONVERSATION.BREAKOUT_ROOM_STATUS.STARTED
				&& this.isInCall
		},
	},

	methods: {
		forceMuteOthers() {
			callParticipantCollection.callParticipantModels.forEach(callParticipantModel => {
				callParticipantModel.forceMute()
			})
		},

		fullScreenChanged() {
			this.$store.dispatch(
				'setIsFullscreen',
				document.webkitIsFullScreen || document.mozFullScreen || document.msFullscreenElement
			)
		},

		toggleFullscreen() {
			if (this.isFullscreen) {
				this.disableFullscreen()
				this.$store.dispatch('setIsFullscreen', false)
			} else {
				this.enableFullscreen()
				this.$store.dispatch('setIsFullscreen', true)
			}
		},

		enableFullscreen() {
			const fullscreenElem = document.getElementById('content-vue')

			if (fullscreenElem.requestFullscreen) {
				fullscreenElem.requestFullscreen()
			} else if (fullscreenElem.webkitRequestFullscreen) {
				fullscreenElem.webkitRequestFullscreen(Element.ALLOW_KEYBOARD_INPUT)
			} else if (fullscreenElem.mozRequestFullScreen) {
				fullscreenElem.mozRequestFullScreen()
			} else if (fullscreenElem.msRequestFullscreen) {
				fullscreenElem.msRequestFullscreen()
			}
		},

		disableFullscreen() {
			if (document.exitFullscreen) {
				document.exitFullscreen()
			} else if (document.webkitExitFullscreen) {
				document.webkitExitFullscreen()
			} else if (document.mozCancelFullScreen) {
				document.mozCancelFullScreen()
			} else if (document.msExitFullscreen) {
				document.msExitFullscreen()
			}
		},

		toggleVirtualBackground() {
			if (this.model.attributes.virtualBackgroundEnabled) {
				this.model.disableVirtualBackground()
			} else {
				this.model.enableVirtualBackground()
			}
		},

		changeView() {
			this.$store.dispatch('setCallViewMode', { isGrid: !this.isGrid })
			this.$store.dispatch('selectedVideoPeerId', null)
		},

		showMediaSettingsDialog() {
			emit('talk:media-settings:show')
		},

		toggleHandRaised() {
			const newState = !this.isHandRaised
			this.model.toggleHandRaised(newState)
			this.$store.dispatch(
				'setParticipantHandRaised',
				{
					sessionId: this.$store.getters.getSessionId(),
					raisedHand: this.model.attributes.raisedHand,
				}
			)
			// If the current conversation is a break-out room and the user is not a moderator,
			// also send request for assistance to the moderators.
			if (this.userIsInBreakoutRoomAndInCall && !this.canModerate) {
				this.$store.dispatch('requestAssistanceAction', {
					token: this.token,
				})
			}
		},

		openConversationSettings() {
			emit('show-conversation-settings', { token: this.token })
		},

		startRecording() {
			this.$store.dispatch('startCallRecording', {
				token: this.token,
				callRecording: CALL.RECORDING.VIDEO,
			})
		},

		stopRecording() {
			this.$store.dispatch('stopCallRecording', {
				token: this.token,
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.top-bar__wrapper {
	display: flex;
}
</style>
