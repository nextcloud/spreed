<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcButton v-shortkey.once="disableKeyboardShortcuts ? null : ['v']"
		v-tooltip="videoButtonTooltip"
		:type="type"
		:aria-label="videoButtonAriaLabel"
		:class="{ 'no-video-available': !isVideoAllowed || !model.attributes.videoAvailable }"
		@shortkey="toggleVideo"
		@click.stop="toggleVideo">
		<template #icon>
			<VideoIcon v-if="showVideoOn" :size="20" />
			<VideoOff v-else :size="20" />
		</template>
	</NcButton>
</template>

<script>
import VideoIcon from 'vue-material-design-icons/Video.vue'
import VideoOff from 'vue-material-design-icons/VideoOff.vue'

import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip.js'

import { PARTICIPANT } from '../../../constants.js'
import BrowserStorage from '../../../services/BrowserStorage.js'

export default {
	name: 'LocalVideoControlButton',

	components: {
		NcButton,
		VideoIcon,
		VideoOff,
	},

	directives: {
		Tooltip,
	},

	props: {
		conversation: {
			type: Object,
			required: true,
		},

		model: {
			type: Object,
			required: true,
		},

		disableKeyboardShortcuts: {
			type: Boolean,
			default: OCP.Accessibility.disableKeyboardShortcuts(),
		},

		type: {
			type: String,
			default: 'tertiary-no-background',
		},

		token: {
			type: String,
			required: true,
		},
	},

	computed: {
		isVideoAllowed() {
			return this.conversation.permissions & PARTICIPANT.PERMISSIONS.PUBLISH_VIDEO
		},

		showVideoOn() {
			return this.model.attributes.videoAvailable && this.model.attributes.videoEnabled
		},

		videoButtonTooltip() {
			if (!this.isVideoAllowed) {
				return t('spreed', 'You are not allowed to enable video')
			}

			if (!this.model.attributes.videoAvailable) {
				return t('spreed', 'No video. Click to select device')
			}

			if (this.model.attributes.videoEnabled) {
				return this.disableKeyboardShortcuts
					? t('spreed', 'Disable video')
					: t('spreed', 'Disable video (V)')
			}

			if (!this.model.getWebRtc() || !this.model.getWebRtc().connection || this.model.getWebRtc().connection.getSendVideoIfAvailable()) {
				return this.disableKeyboardShortcuts
					? t('spreed', 'Enable video')
					: t('spreed', 'Enable video (V)')
			}

			return this.disableKeyboardShortcuts
				? t('spreed', 'Enable video - Your connection will be briefly interrupted when enabling the video for the first time')
				: t('spreed', 'Enable video (V) - Your connection will be briefly interrupted when enabling the video for the first time')
		},

		videoButtonAriaLabel() {
			if (!this.model.attributes.videoAvailable) {
				return t('spreed', 'No video. Click to select device')
			}

			if (this.model.attributes.videoEnabled) {
				return t('spreed', 'Disable video')
			}

			if (!this.model.getWebRtc() || !this.model.getWebRtc().connection || this.model.getWebRtc().connection.getSendVideoIfAvailable()) {
				return t('spreed', 'Enable video')
			}

			return t('spreed', 'Enable video. Your connection will be briefly interrupted when enabling the video for the first time')
		},
	},

	mounted() {
		subscribe('local-video-control-button:toggle-video', this.updateDeviceState)
	},

	beforeUnmount() {
		unsubscribe('local-video-control-button:toggle-video', this.updateDeviceState)
	},

	methods: {
		toggleVideo() {
			/**
			 * Abort toggling the video if the 'v' key is lifted when pasting an
			 * image in the new message form.
			 */
			if (document.getElementsByClassName('upload-editor').length !== 0) {
				return
			}

			if (!this.model.attributes.videoAvailable) {
				emit('talk:media-settings:show')
				return
			}

			if (this.model.attributes.videoEnabled) {
				this.model.disableVideo()
			} else {
				this.model.enableVideo()
			}
		},

		updateDeviceState() {
			if (BrowserStorage.getItem('videoDisabled_' + this.token)) {
				this.model.disableVideo()
			} else {
				this.model.enableVideo()
			}
		},
	},
}
</script>

<style scoped lang="scss">
.no-video-available {
	opacity: .7;
}
</style>
