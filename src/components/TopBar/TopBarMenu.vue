<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="top-bar-menu">
		<TransitionExpand v-if="isInCall" :show="isHandRaised" direction="horizontal">
			<NcButton :title="raiseHandButtonLabel"
				:aria-label="raiseHandButtonLabel"
				type="tertiary"
				@click.stop="toggleHandRaised">
				<template #icon>
					<!-- The following icon is much bigger than all the others
						so we reduce its size -->
					<IconHandBackLeft :size="18" />
				</template>
			</NcButton>
		</TransitionExpand>

		<NcActions v-if="!isSidebar"
			:title="t('spreed', 'Conversation actions')"
			:aria-label="t('spreed', 'Conversation actions')"
			type="tertiary">
			<!-- Menu icon: white if in call -->
			<template v-if="isInCall" #icon>
				<IconDotsHorizontal :size="20" />
			</template>

			<template v-if="showActions && isInCall">
				<!-- Raise hand -->
				<NcActionButton close-after-click
					@click="toggleHandRaised">
					<!-- The following icon is much bigger than all the others
					so we reduce its size -->
					<template #icon>
						<IconHandBackLeft :size="16" />
					</template>
					{{ raiseHandButtonLabel }}
				</NcActionButton>

				<!-- Moderator actions -->
				<template v-if="!isOneToOneConversation && canFullModerate">
					<NcActionButton close-after-click
						@click="forceMuteOthers">
						<template #icon>
							<IconMicrophoneOff :size="20" />
						</template>
						{{ t('spreed', 'Mute others') }}
					</NcActionButton>
				</template>

				<!-- Device settings -->
				<NcActionButton close-after-click
					@click="showMediaSettingsDialog">
					<template #icon>
						<IconVideo :size="20" />
					</template>
					{{ t('spreed', 'Media settings') }}
				</NcActionButton>
				<NcActionSeparator />
				<!-- Call layout switcher -->
				<NcActionButton v-if="showCallLayoutSwitch"
					close-after-click
					@click="changeView">
					<template #icon>
						<IconViewGrid v-if="!isGrid" :size="20" />
						<IconViewGallery v-else :size="20" />
					</template>
					{{ changeViewText }}
				</NcActionButton>
			</template>

			<!-- Fullscreen -->
			<NcActionButton :aria-label="t('spreed', 'Toggle full screen')"
				close-after-click
				@click="toggleFullscreen">
				<template #icon>
					<IconFullscreen v-if="!isFullscreen" :size="20" />
					<IconFullscreenExit v-else :size="20" />
				</template>
				{{ labelFullscreen }}
			</NcActionButton>

			<!-- Go to file -->
			<NcActionLink v-if="isFileConversation"
				:href="linkToFile">
				<template #icon>
					<IconFile :size="20" />
				</template>
				{{ t('spreed', 'Go to file') }}
			</NcActionLink>

			<!-- Call recording -->
			<template v-if="canModerateRecording">
				<NcActionButton v-if="!isRecording && !isStartingRecording && isInCall"
					close-after-click
					@click="startRecording">
					<template #icon>
						<IconRecordCircle :size="20" />
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
						<IconStop :size="20" />
					</template>
					{{ t('spreed', 'Stop recording') }}
				</NcActionButton>
			</template>

			<!-- Breakout rooms -->
			<NcActionButton v-if="canConfigureBreakoutRooms"
				close-after-click
				@click="$emit('open-breakout-rooms-editor')">
				<template #icon>
					<IconDotsCircle :size="20" />
				</template>
				{{ t('spreed', 'Set up breakout rooms') }}
			</NcActionButton>

			<!-- Conversation settings -->
			<NcActionButton close-after-click
				@click="openConversationSettings">
				<template #icon>
					<IconCog :size="20" />
				</template>
				{{ t('spreed', 'Conversation settings') }}
			</NcActionButton>
			<NcActionLink v-if="isInCall && canDownloadCallParticipants"
				:href="downloadCallParticipantsLink"
				target="_blank">
				<template #icon>
					<IconDownload :size="20" />
				</template>
				{{ t('spreed', 'Download attendance list') }}
			</NcActionLink>
		</NcActions>
	</div>
</template>

<script>
import IconCog from 'vue-material-design-icons/Cog.vue'
import IconDotsCircle from 'vue-material-design-icons/DotsCircle.vue'
import IconDotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import IconDownload from 'vue-material-design-icons/Download.vue'
import IconFile from 'vue-material-design-icons/File.vue'
import IconFullscreen from 'vue-material-design-icons/Fullscreen.vue'
import IconFullscreenExit from 'vue-material-design-icons/FullscreenExit.vue'
import IconHandBackLeft from 'vue-material-design-icons/HandBackLeft.vue'
import IconMicrophoneOff from 'vue-material-design-icons/MicrophoneOff.vue'
import IconRecordCircle from 'vue-material-design-icons/RecordCircle.vue'
import IconStop from 'vue-material-design-icons/Stop.vue'
import IconVideo from 'vue-material-design-icons/Video.vue'
import IconViewGallery from 'vue-material-design-icons/ViewGallery.vue'
import IconViewGrid from 'vue-material-design-icons/ViewGrid.vue'

import { showWarning } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionLink from '@nextcloud/vue/components/NcActionLink'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { useHotKey } from '@nextcloud/vue/composables/useHotKey'

import TransitionExpand from '../MediaSettings/TransitionExpand.vue'

import {
	useDocumentFullscreen,
	enableFullscreen,
	disableFullscreen,
} from '../../composables/useDocumentFullscreen.ts'
import { useIsInCall } from '../../composables/useIsInCall.js'
import { CALL, CONVERSATION, PARTICIPANT } from '../../constants.ts'
import { getTalkConfig, hasTalkFeature } from '../../services/CapabilitiesManager.ts'
import { useBreakoutRoomsStore } from '../../stores/breakoutRooms.ts'
import { useCallViewStore } from '../../stores/callView.ts'
import { generateAbsoluteUrl } from '../../utils/handleUrl.ts'
import { callParticipantCollection } from '../../utils/webrtc/index.js'

const AUTO_LOWER_HAND_THRESHOLD = 3000
const disableKeyboardShortcuts = OCP.Accessibility.disableKeyboardShortcuts()

export default {
	name: 'TopBarMenu',

	components: {
		TransitionExpand,
		NcActionButton,
		NcActionLink,
		NcActionSeparator,
		NcActions,
		NcButton,
		NcLoadingIcon,
		// Icons
		IconCog,
		IconDotsCircle,
		IconDotsHorizontal,
		IconDownload,
		IconFile,
		IconFullscreen,
		IconFullscreenExit,
		IconHandBackLeft,
		IconMicrophoneOff,
		IconRecordCircle,
		IconStop,
		IconVideo,
		IconViewGallery,
		IconViewGrid,
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
		return {
			isInCall: useIsInCall(),
			isFullscreen: useDocumentFullscreen(),
			breakoutRoomsStore: useBreakoutRoomsStore(),
			callViewStore: useCallViewStore(),
		}
	},

	data() {
		return {
			boundaryElement: document.querySelector('.main-view'),
			lowerHandTimeout: null,
			speakingTimestamp: null,
			lowerHandDelay: AUTO_LOWER_HAND_THRESHOLD,
		}
	},

	computed: {
		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		labelFullscreen() {
			return this.isFullscreen
				? t('spreed', 'Exit full screen (F)')
				: t('spreed', 'Full screen (F)')
		},

		isFileConversation() {
			return this.conversation.objectType === CONVERSATION.OBJECT_TYPE.FILE && this.conversation.objectId
		},

		linkToFile() {
			return this.isFileConversation
				? generateAbsoluteUrl('/f/{objectId}', { objectId: this.conversation.objectId })
				: ''
		},

		isOneToOneConversation() {
			return this.conversation.type === CONVERSATION.TYPE.ONE_TO_ONE
				|| this.conversation.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER
		},

		changeViewText() {
			return this.isGrid
				? t('spreed', 'Speaker view')
				: t('spreed', 'Grid view')
		},

		isGrid() {
			return this.callViewStore.isGrid
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
				return disableKeyboardShortcuts
					? t('spreed', 'Raise hand')
					: t('spreed', 'Raise hand (R)')
			}
			return disableKeyboardShortcuts
				? t('spreed', 'Lower hand')
				: t('spreed', 'Lower hand (R)')
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
			return this.canFullModerate && (getTalkConfig(this.token, 'call', 'recording') || false)
		},

		canConfigureBreakoutRooms() {
			if (this.conversation.type !== CONVERSATION.TYPE.GROUP || !this.canFullModerate) {
				return false
			}

			if (this.conversation.objectType === CONVERSATION.OBJECT_TYPE.BREAKOUT_ROOM
				|| this.conversation.breakoutRoomMode !== CONVERSATION.BREAKOUT_ROOM_MODE.NOT_CONFIGURED) {
				return false
			}

			return !!getTalkConfig(this.token, 'call', 'breakout-rooms')
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
			return this.conversation.objectType === CONVERSATION.OBJECT_TYPE.BREAKOUT_ROOM
				&& this.isInCall
		},

		showCallLayoutSwitch() {
			return !this.callViewStore.isEmptyCallView
		},

		canDownloadCallParticipants() {
			return hasTalkFeature(this.token, 'download-call-participants') && this.canModerate
		},

		downloadCallParticipantsLink() {
			return generateOcsUrl('apps/spreed/api/v4/call/{token}/download', { token: this.token })
		},
	},

	watch: {
		'model.attributes.speaking'(speaking) {
			// user stops speaking in lowerHandTimeout
			if (this.lowerHandTimeout !== null && !speaking) {
				this.lowerHandDelay = Math.max(0, this.lowerHandDelay - (Date.now() - this.speakingTimestamp))
				clearTimeout(this.lowerHandTimeout)
				this.lowerHandTimeout = null

				return
			}

			// user is not speaking OR timeout is already running OR hand is not raised
			if (!speaking || this.lowerHandTimeout !== null || !this.isHandRaised) {
				return
			}

			this.speakingTimestamp = Date.now()
			this.lowerHandTimeout = setTimeout(() => {
				this.lowerHandTimeout = null
				this.speakingTimestamp = null
				this.lowerHandDelay = AUTO_LOWER_HAND_THRESHOLD

				if (this.isHandRaised) {
					this.toggleHandRaised()
				}
			}, this.lowerHandDelay)
		},
	},

	created() {
		useHotKey('r', this.toggleHandRaised)
		useHotKey('f', this.toggleFullscreen)
	},

	methods: {
		t,
		forceMuteOthers() {
			callParticipantCollection.callParticipantModels.value.forEach(callParticipantModel => {
				callParticipantModel.forceMute()
			})
		},

		toggleFullscreen() {
			if (this.isSidebar) {
				return
			}

			// Don't toggle fullscreen if there is an open modal
			// FIXME won't be needed without Fulscreen API
			if (Array.from(document.body.childNodes).filter(child => {
				return child.nodeName === 'DIV' && child.classList.contains('modal-mask')
					&& window.getComputedStyle(child).display !== 'none'
			}).length !== 0) {
				showWarning(t('spreed', 'You need to close a dialog to toggle full screen'))
				return
			}

			if (this.isFullscreen) {
				disableFullscreen()
			} else {
				emit('toggle-navigation', { open: false })
				enableFullscreen()
			}
		},

		changeView() {
			this.callViewStore.setCallViewMode({ token: this.token, isGrid: !this.isGrid, clearLast: false })
			this.callViewStore.setSelectedVideoPeerId(null)
		},

		showMediaSettingsDialog() {
			emit('talk:media-settings:show')
		},

		toggleHandRaised() {
			if (!this.isInCall) {
				return
			}
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
				const hasRaisedHands = Object.keys(this.$store.getters.participantRaisedHandList)
					.filter(sessionId => sessionId !== this.$store.getters.getSessionId())
					.length !== 0
				if (hasRaisedHands) {
					return // Assistance is already requested by someone in the room
				}
				const hasAssistanceRequested = this.conversation.breakoutRoomStatus === CONVERSATION.BREAKOUT_ROOM_STATUS.STATUS_ASSISTANCE_REQUESTED
				if (newState && !hasAssistanceRequested) {
					this.breakoutRoomsStore.requestAssistance(this.token)
				} else if (!newState && hasAssistanceRequested) {
					this.breakoutRoomsStore.dismissRequestAssistance(this.token)
				}
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
.top-bar-menu {
	display: flex;
}
</style>
