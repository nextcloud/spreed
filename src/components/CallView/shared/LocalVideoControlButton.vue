<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="local-video-control-wrapper">
		<NcButton :title="videoButtonTitle"
			:variant="variant"
			:aria-label="videoButtonAriaLabel"
			:class="{
				'no-video-available': !model.attributes.videoAvailable,
				'video-control-button': showDevices,
			}"
			:disabled="!isVideoAllowed"
			@click.stop="toggleVideo">
			<template #icon>
				<VideoIcon v-if="showVideoOn" :size="20" />
				<VideoOff v-else :size="20" />
			</template>
		</NcButton>

		<NcPopover v-if="showDevices"
			close-on-click-outside>
			<template #trigger>
				<NcButton class="video-selector-button"
					:title="t('spreed', 'Select video input device')"
					:aria-label="t('spreed', 'Select video input device')"
					:variant="variant">
					<template #icon>
						<IconChevronUp :size="16" />
					</template>
				</NcButton>
			</template>
			<div class="video-selector-popover">
				<MediaDevicesSelector kind="videoinput"
					:devices="devices"
					:device-id="videoInputId"
					@refresh="updateDevices"
					@update:device-id="handleVideoInputIdChange" />
				<NcButton class="video-background-button"
					variant="tertiary"
					:title="t('spreed', 'Replace background')"
					:aria-label="t('spreed', 'Replace background')"
					@click="showMediaSettings">
					<template #icon>
						<NcIconSvgWrapper :svg="IconBackground" :size="20" />
					</template>
				</NcButton>
			</div>
		</NcPopover>
	</div>
</template>

<script>
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { useHotKey } from '@nextcloud/vue/composables/useHotKey'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import IconChevronUp from 'vue-material-design-icons/ChevronUp.vue'
import VideoIcon from 'vue-material-design-icons/Video.vue'
import VideoOff from 'vue-material-design-icons/VideoOff.vue'
import MediaDevicesSelector from '../../MediaSettings/MediaDevicesSelector.vue'
import IconBackground from '../../../../img/icon-replace-background.svg?raw'
import { useDevices } from '../../../composables/useDevices.js'
import { PARTICIPANT } from '../../../constants.ts'
import BrowserStorage from '../../../services/BrowserStorage.js'

export default {
	name: 'LocalVideoControlButton',

	components: {
		NcIconSvgWrapper,
		MediaDevicesSelector,
		NcButton,
		NcPopover,
		VideoIcon,
		VideoOff,
		IconChevronUp,
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

		variant: {
			type: String,
			default: 'tertiary-no-background',
		},

		token: {
			type: String,
			required: true,
		},

		showDevices: {
			type: Boolean,
			default: false,
		},
	},

	setup() {
		const {
			devices,
			videoInputId,
			updateDevices,
			updatePreferences,
		} = useDevices(undefined, false)
		return {
			devices,
			videoInputId,
			updateDevices,
			updatePreferences,
			IconBackground,
		}
	},

	computed: {
		isVideoAllowed() {
			return this.conversation.permissions & PARTICIPANT.PERMISSIONS.PUBLISH_VIDEO
		},

		showVideoOn() {
			return this.model.attributes.videoAvailable && this.model.attributes.videoEnabled
		},

		videoButtonTitle() {
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

	created() {
		useHotKey('v', this.toggleVideo)
	},

	mounted() {
		subscribe('local-video-control-button:toggle-video', this.updateDeviceState)
	},

	beforeUnmount() {
		unsubscribe('local-video-control-button:toggle-video', this.updateDeviceState)
	},

	methods: {
		t,
		toggleVideo() {
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

		handleVideoInputIdChange(videoInputId) {
			this.videoInputId = videoInputId
			this.updatePreferences('videoinput')
		},

		showMediaSettings() {
			emit('talk:media-settings:show', 'video-background')
		},
	},
}
</script>

<style scoped lang="scss">
.no-video-available {
	opacity: .7;
}

.video-selector-button {
	--button-size: 24px;
	height: var(--default-clickable-area);
	border-end-start-radius: 2px;
	border-start-start-radius: 2px;
}

.video-selector-popover {
	display: flex;
	flex-direction: row;
	gap: calc(2 * var(--default-grid-baseline));
	width: calc(328px + var(--default-grid-baseline) * 6 + var(--default-clickable-area));
	padding-inline: calc(var(--default-grid-baseline) * 2);
	align-items: center;
}

.video-control-button {
	border-start-end-radius: 2px;
	border-end-end-radius: 2px;
}

.local-video-control-wrapper {
	display: flex;
	align-items: center;
	gap: calc(var(--default-grid-baseline) / 2);
}

.video-background-button {
	height: var(--default-clickable-area);
}

:deep(.v-popper__inner) {
	width: 346px;
	padding-inline: var(--default-grid-baseline);
}

:deep(.v-select.select) {
	width: 300px !important;
}
</style>
