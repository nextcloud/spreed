<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="top-bar-menu">
		<NcActions v-if="!isSidebar"
			force-menu
			:title="t('spreed', 'Conversation actions')"
			:aria-label="t('spreed', 'Conversation actions')"
			variant="tertiary">
			<!-- Menu icon: white if in call -->
			<template v-if="isInCall" #icon>
				<IconDotsHorizontal :size="20" />
			</template>

			<template v-if="isInCall && canFullModerate">
				<!-- Moderator actions -->
				<template v-if="!isOneToOneConversation">
					<NcActionButton close-after-click
						@click="forceMuteOthers">
						<template #icon>
							<NcIconSvgWrapper :svg="IconMicrophoneOffOutline" :size="20" />
						</template>
						{{ t('spreed', 'Mute others') }}
					</NcActionButton>
				</template>

				<!-- Call recording -->
				<template v-if="canModerateRecording">
					<NcActionButton v-if="!isRecording && !isStartingRecording && isInCall"
						close-after-click
						@click="startRecording">
						<template #icon>
							<IconRecordCircleOutline :size="20" />
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

				<NcActionSeparator v-if="!isOneToOneConversation || canModerateRecording" />
			</template>

			<!-- Go to file -->
			<NcActionLink v-if="isFileConversation"
				target="_blank"
				rel="noopener noreferrer"
				:href="linkToFile">
				<template #icon>
					<IconFile :size="20" />
				</template>
				{{ t('spreed', 'Go to file') }}
			</NcActionLink>

			<!-- Device settings -->
			<NcActionButton v-if="isInCall"
				close-after-click
				@click="showMediaSettingsDialog">
				<template #icon>
					<IconVideoOutline :size="20" />
				</template>
				{{ t('spreed', 'Check devices') }}
			</NcActionButton>

			<!-- Breakout rooms -->
			<NcActionButton v-if="canConfigureBreakoutRooms"
				close-after-click
				@click="$emit('openBreakoutRoomsEditor')">
				<template #icon>
					<IconDotsCircle :size="20" />
				</template>
				{{ t('spreed', 'Set up breakout rooms') }}
			</NcActionButton>

			<NcActionLink v-if="isInCall && canDownloadCallParticipants"
				:href="downloadCallParticipantsLink"
				target="_blank">
				<template #icon>
					<NcIconSvgWrapper :svg="IconFileDownload" :size="20" />
				</template>
				{{ t('spreed', 'Download attendance list') }}
			</NcActionLink>
			<!-- Fullscreen -->
			<NcActionButton v-if="!isInCall"
				:aria-label="t('spreed', 'Toggle full screen')"
				close-after-click
				@click="toggleFullscreen">
				<template #icon>
					<IconFullscreen v-if="!isFullscreen" :size="20" />
					<IconFullscreenExit v-else :size="20" />
				</template>
				{{ labelFullscreen }}
			</NcActionButton>

			<!-- Conversation settings -->
			<NcActionButton close-after-click
				@click="openConversationSettings">
				<template #icon>
					<IconCogOutline :size="20" />
				</template>
				{{ t('spreed', 'Conversation settings') }}
			</NcActionButton>
		</NcActions>

		<NcButton v-else
			class="top-bar__icon-wrapper"
			:aria-label="t('spreed', 'Check devices')"
			:title="t('spreed', 'Check devices')"
			variant="tertiary"
			@click="showMediaSettingsDialog">
			<template #icon>
				<IconCogOutline :size="20" />
			</template>
		</NcButton>
	</div>
</template>

<script>
import { showWarning } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionLink from '@nextcloud/vue/components/NcActionLink'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import IconCogOutline from 'vue-material-design-icons/CogOutline.vue'
import IconDotsCircle from 'vue-material-design-icons/DotsCircle.vue'
import IconDotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import IconFile from 'vue-material-design-icons/File.vue'
import IconFullscreen from 'vue-material-design-icons/Fullscreen.vue'
import IconFullscreenExit from 'vue-material-design-icons/FullscreenExit.vue'
import IconRecordCircleOutline from 'vue-material-design-icons/RecordCircleOutline.vue'
import IconStop from 'vue-material-design-icons/Stop.vue'
import IconVideoOutline from 'vue-material-design-icons/VideoOutline.vue'
import IconFileDownload from '../../../img/material-icons/file-download.svg?raw'
import IconMicrophoneOffOutline from '../../../img/material-icons/microphone-off-outline.svg?raw'
import {
	disableFullscreen,
	enableFullscreen,
	useDocumentFullscreen,
} from '../../composables/useDocumentFullscreen.ts'
import { useIsInCall } from '../../composables/useIsInCall.js'
import { CALL, CONVERSATION, PARTICIPANT } from '../../constants.ts'
import { getTalkConfig, hasTalkFeature } from '../../services/CapabilitiesManager.ts'
import { generateAbsoluteUrl } from '../../utils/handleUrl.ts'
import { callParticipantCollection } from '../../utils/webrtc/index.js'

export default {
	name: 'TopBarMenu',

	components: {
		NcActionButton,
		NcActionLink,
		NcActionSeparator,
		NcActions,
		NcButton,
		NcLoadingIcon,
		NcIconSvgWrapper,
		// Icons
		IconCogOutline,
		IconDotsCircle,
		IconDotsHorizontal,
		IconFile,
		IconFullscreen,
		IconFullscreenExit,
		IconRecordCircleOutline,
		IconStop,
		IconVideoOutline,
	},

	props: {
		/**
		 * The conversation token
		 */
		token: {
			type: String,
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

	emits: ['openBreakoutRoomsEditor'],

	setup() {
		return {
			IconFileDownload,
			IconMicrophoneOffOutline,
			isFullscreen: useDocumentFullscreen(),
			isInCall: useIsInCall(),
		}
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
			return getTalkConfig(this.token, 'call', 'recording') || false
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

		canDownloadCallParticipants() {
			return hasTalkFeature(this.token, 'download-call-participants') && this.canModerate
		},

		downloadCallParticipantsLink() {
			return generateOcsUrl('apps/spreed/api/v4/call/{token}/download', { token: this.token })
		},
	},

	methods: {
		t,
		forceMuteOthers() {
			callParticipantCollection.callParticipantModels.forEach((callParticipantModel) => {
				callParticipantModel.forceMute()
			})
		},

		toggleFullscreen() {
			if (this.isSidebar) {
				return
			}

			// Don't toggle fullscreen if there is an open modal
			// FIXME won't be needed without Fulscreen API
			if (Array.from(document.body.childNodes).filter((child) => {
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

		showMediaSettingsDialog() {
			emit('talk:media-settings:show')
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
