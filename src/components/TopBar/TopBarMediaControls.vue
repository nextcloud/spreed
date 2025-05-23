<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="buttons-bar">
		<div class="network-connection-state">
			<NcPopover v-if="qualityWarningTooltip"
				:boundary="boundaryElement"
				:aria-label="qualityWarningAriaLabel"
				trigger="hover"
				:auto-hide="false"
				:focus-trap="false"
				:shown="showQualityWarningTooltip">
				<template #trigger>
					<NcButton id="quality_warning_button"
						type="tertiary-no-background"
						class="trigger"
						:aria-label="qualityWarningAriaLabel"
						@click="mouseover = !mouseover">
						<template #icon>
							<IconNetworkStrength2Alert fill-color="#e9322d" :size="20" />
						</template>
					</NcButton>
				</template>
				<div class="hint">
					<span>{{ qualityWarningTooltip.content }}</span>
					<div class="hint__actions">
						<NcButton v-if="qualityWarningTooltip.action"
							type="primary"
							class="hint__button"
							@click="executeQualityWarningTooltipAction">
							{{ qualityWarningTooltip.actionLabel }}
						</NcButton>
						<NcButton v-if="!isQualityWarningTooltipDismissed"
							type="tertiary"
							class="hint__button"
							@click="dismissQualityWarningTooltip">
							{{ t('spreed', 'Dismiss') }}
						</NcButton>
					</div>
				</div>
			</NcPopover>
		</div>

		<LocalAudioControlButton :token="token"
			:conversation="conversation"
			:model="model"
			type="tertiary" />

		<LocalVideoControlButton :token="token"
			:conversation="conversation"
			:model="model"
			type="tertiary" />

		<NcButton v-if="isVirtualBackgroundAvailable && isSidebar"
			:title="toggleVirtualBackgroundButtonLabel"
			type="tertiary"
			:aria-label="toggleVirtualBackgroundButtonLabel"
			:class="blurButtonClass"
			@click.stop="toggleVirtualBackground">
			<template #icon>
				<IconBlur v-if="isVirtualBackgroundEnabled" :size="20" />
				<IconBlurOff v-else :size="20" />
			</template>
		</NcButton>

		<NcActions v-if="!isSidebar && isScreensharing"
			id="screensharing-button"
			:title="screenSharingButtonTitle"
			type="error"
			:aria-label="screenSharingButtonAriaLabel"
			:class="screenSharingButtonClass"
			class="app-navigation-entry-utils-menu-button"
			:boundaries-element="boundaryElement"
			:disabled="!isScreensharingAllowed"
			:open.sync="screenSharingMenuOpen">
			<template #icon>
				<IconMonitorOff :size="20" />
			</template>
			<!-- Actions -->
			<NcActionButton close-after-click @click="showScreen">
				<template #icon>
					<IconMonitor :size="20" />
				</template>
				{{ t('spreed', 'Show your screen') }}
			</NcActionButton>
			<NcActionButton close-after-click @click="stopScreen">
				<template #icon>
					<IconMonitorOff :size="20" />
				</template>
				{{ t('spreed', 'Stop screensharing') }}
			</NcActionButton>
		</NcActions>
		<NcButton v-else-if="!isSidebar"
			:title="screenSharingButtonTitle"
			type="tertiary"
			:aria-label="screenSharingButtonAriaLabel"
			:disabled="!isScreensharingAllowed"
			@click.stop="toggleScreenSharingMenu">
			<template #icon>
				<IconMonitorShare :size="20" />
			</template>
		</NcButton>
	</div>
</template>

<script>
import escapeHtml from 'escape-html'

import IconBlur from 'vue-material-design-icons/Blur.vue'
import IconBlurOff from 'vue-material-design-icons/BlurOff.vue'
import IconMonitor from 'vue-material-design-icons/Monitor.vue'
import IconMonitorOff from 'vue-material-design-icons/MonitorOff.vue'
import IconMonitorShare from 'vue-material-design-icons/MonitorShare.vue'
import IconNetworkStrength2Alert from 'vue-material-design-icons/NetworkStrength2Alert.vue'

import { showMessage } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcPopover from '@nextcloud/vue/components/NcPopover'

import LocalAudioControlButton from '../CallView/shared/LocalAudioControlButton.vue'
import LocalVideoControlButton from '../CallView/shared/LocalVideoControlButton.vue'

import { useIsInCall } from '../../composables/useIsInCall.js'
import { PARTICIPANT } from '../../constants.ts'
import { CONNECTION_QUALITY } from '../../utils/webrtc/analyzers/PeerConnectionAnalyzer.js'
import { callAnalyzer } from '../../utils/webrtc/index.js'

export default {

	name: 'TopBarMediaControls',

	components: {
		LocalAudioControlButton,
		LocalVideoControlButton,
		NcActionButton,
		NcActions,
		NcButton,
		NcPopover,
		// Icons
		IconBlur,
		IconBlurOff,
		IconMonitor,
		IconMonitorOff,
		IconMonitorShare,
		IconNetworkStrength2Alert,
	},

	props: {
		token: {
			type: String,
			required: true,
		},

		model: {
			type: Object,
			required: true,
		},

		localCallParticipantModel: {
			type: Object,
			required: true,
		},

		isSidebar: {
			type: Boolean,
			default: false,
		},
	},

	setup() {
		return {
			isInCall: useIsInCall(),
			callAnalyzer,
		}
	},

	data() {
		return {
			screenSharingMenuOpen: false,
			boundaryElement: document.querySelector('.main-view'),
			mouseover: false,
			qualityWarningInGracePeriodTimeout: null,
			isQualityWarningTooltipDismissed: false,
		}
	},

	computed: {
		isVirtualBackgroundAvailable() {
			return this.model.attributes.virtualBackgroundAvailable
		},

		isVirtualBackgroundEnabled() {
			return this.model.attributes.virtualBackgroundEnabled
		},

		toggleVirtualBackgroundButtonLabel() {
			return this.isVirtualBackgroundEnabled
				? t('spreed', 'Disable background blur')
				: t('spreed', 'Blur background')
		},

		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		isScreensharingAllowed() {
			return this.conversation.permissions & PARTICIPANT.PERMISSIONS.PUBLISH_SCREEN
		},

		blurButtonClass() {
			return {
				'blur-disabled': this.isVirtualBackgroundEnabled,
			}
		},

		screenSharingButtonClass() {
			return {
				'screensharing-enabled': this.isScreensharingAllowed && this.isScreensharing,
				'no-screensharing-available': !this.isScreensharingAllowed,
			}
		},

		isScreensharing() {
			return this.model.attributes.localScreen
		},

		screenSharingButtonTitle() {
			if (!this.isScreensharingAllowed) {
				return t('spreed', 'You are not allowed to enable screensharing')
			}

			if (this.screenSharingMenuOpen) {
				return null
			}

			if (!this.isScreensharingAllowed) {
				return t('spreed', 'No screensharing')
			}

			return this.isScreensharing
				? t('spreed', 'Screensharing options')
				: t('spreed', 'Enable screensharing')
		},

		screenSharingButtonAriaLabel() {
			if (this.screenSharingMenuOpen) {
				return t('spreed', 'Screensharing options')
			}

			return this.isScreensharing
				? t('spreed', 'Screensharing options')
				: t('spreed', 'Enable screensharing')
		},

		showQualityWarningTooltip() {
			return this.qualityWarningTooltip && (!this.isQualityWarningTooltipDismissed || this.mouseover)
		},

		showQualityWarning() {
			return this.senderConnectionQualityIsBad || this.qualityWarningInGracePeriodTimeout
		},

		senderConnectionQualityIsBad() {
			return this.senderConnectionQualityAudioIsBad
				|| this.senderConnectionQualityVideoIsBad
				|| this.senderConnectionQualityScreenIsBad
		},

		senderConnectionQualityAudioIsBad() {
			return callAnalyzer
				&& (callAnalyzer.attributes.senderConnectionQualityAudio === CONNECTION_QUALITY.VERY_BAD
					|| callAnalyzer.attributes.senderConnectionQualityAudio === CONNECTION_QUALITY.NO_TRANSMITTED_DATA)
		},

		senderConnectionQualityVideoIsBad() {
			return callAnalyzer
				&& (callAnalyzer.attributes.senderConnectionQualityVideo === CONNECTION_QUALITY.VERY_BAD
					|| callAnalyzer.attributes.senderConnectionQualityVideo === CONNECTION_QUALITY.NO_TRANSMITTED_DATA)
		},

		senderConnectionQualityScreenIsBad() {
			return callAnalyzer
				&& (callAnalyzer.attributes.senderConnectionQualityScreen === CONNECTION_QUALITY.VERY_BAD
					|| callAnalyzer.attributes.senderConnectionQualityScreen === CONNECTION_QUALITY.NO_TRANSMITTED_DATA)
		},

		qualityWarningAriaLabel() {
			let label = ''
			if (!this.model.attributes.audioEnabled && this.model.attributes.videoEnabled && this.model.attributes.localScreen) {
				label = t('spreed', 'Bad sent video and screen quality.')
			} else if (!this.model.attributes.audioEnabled && this.model.attributes.localScreen) {
				label = t('spreed', 'Bad sent screen quality.')
			} else if (!this.model.attributes.audioEnabled && this.model.attributes.videoEnabled) {
				label = t('spreed', 'Bad sent video quality.')
			} else if (this.model.attributes.videoEnabled && this.model.attributes.localScreen) {
				label = t('spreed', 'Bad sent audio, video and screen quality.')
			} else if (this.model.attributes.localScreen) {
				label = t('spreed', 'Bad sent audio and screen quality.')
			} else if (this.model.attributes.videoEnabled) {
				label = t('spreed', 'Bad sent audio and video quality.')
			} else {
				label = t('spreed', 'Bad sent audio quality.')
			}

			return label
		},

		qualityWarningTooltip() {
			if (!this.showQualityWarning) {
				return null
			}

			const virtualBackgroundEnabled = this.isVirtualBackgroundAvailable && this.model.attributes.virtualBackgroundEnabled

			if (!this.model.attributes.audioEnabled && this.model.attributes.videoEnabled && virtualBackgroundEnabled && this.model.attributes.localScreen) {
				return {
					content: t('spreed', 'Your internet connection or computer are busy and other participants might be unable to see your screen. To improve the situation try to disable the background blur or your video while doing a screen share.'),
					actionLabel: t('spreed', 'Disable background blur'),
					action: 'disableVirtualBackground',
				}
			} else if (!this.model.attributes.audioEnabled && this.model.attributes.videoEnabled && this.model.attributes.localScreen) {
				return {
					content: t('spreed', 'Your internet connection or computer are busy and other participants might be unable to see your screen. To improve the situation try to disable your video while doing a screenshare.'),
					actionLabel: t('spreed', 'Disable video'),
					action: 'disableVideo',
				}
			} else if (!this.model.attributes.audioEnabled && this.model.attributes.localScreen) {
				return {
					content: t('spreed', 'Your internet connection or computer are busy and other participants might be unable to see your screen.'),
					actionLabel: '',
					action: '',
				}
			} else if (!this.model.attributes.audioEnabled && this.model.attributes.videoEnabled) {
				return {
					content: t('spreed', 'Your internet connection or computer are busy and other participants might be unable to see you.'),
					actionLabel: '',
					action: '',
				}
			} else if (this.model.attributes.videoEnabled && virtualBackgroundEnabled && this.model.attributes.localScreen) {
				return {
					content: t('spreed', 'Your internet connection or computer are busy and other participants might be unable to understand and see you. To improve the situation try to disable the background blur or your video while doing a screenshare.'),
					actionLabel: t('spreed', 'Disable background blur'),
					action: 'disableVirtualBackground',
				}
			} else if (this.model.attributes.videoEnabled && this.model.attributes.localScreen) {
				return {
					content: t('spreed', 'Your internet connection or computer are busy and other participants might be unable to understand and see you. To improve the situation try to disable your video while doing a screenshare.'),
					actionLabel: t('spreed', 'Disable video'),
					action: 'disableVideo',
				}
			} else if (this.model.attributes.localScreen) {
				return {
					content: t('spreed', 'Your internet connection or computer are busy and other participants might be unable to understand you and see your screen. To improve the situation try to disable your screenshare.'),
					actionLabel: t('spreed', 'Disable screenshare'),
					action: 'disableScreenShare',
				}
			} else if (this.model.attributes.videoEnabled && virtualBackgroundEnabled) {
				return {
					content: t('spreed', 'Your internet connection or computer are busy and other participants might be unable to understand and see you. To improve the situation try to disable the background blur or your video.'),
					actionLabel: t('spreed', 'Disable background blur'),
					action: 'disableVirtualBackground',
				}
			} else if (this.model.attributes.videoEnabled) {
				return {
					content: t('spreed', 'Your internet connection or computer are busy and other participants might be unable to understand and see you. To improve the situation try to disable your video.'),
					actionLabel: t('spreed', 'Disable video'),
					action: 'disableVideo',
				}
			} else {
				return {
					content: t('spreed', 'Your internet connection or computer are busy and other participants might be unable to understand you.'),
					actionLabel: '',
					action: '',
				}
			}
		},
	},

	watch: {
		senderConnectionQualityIsBad(senderConnectionQualityIsBad) {
			if (!senderConnectionQualityIsBad) {
				return
			}

			if (this.qualityWarningInGracePeriodTimeout) {
				window.clearTimeout(this.qualityWarningInGracePeriodTimeout)
			}

			this.qualityWarningInGracePeriodTimeout = window.setTimeout(() => {
				this.qualityWarningInGracePeriodTimeout = null
			}, 10000)
		},
	},

	methods: {
		t,

		toggleVirtualBackground() {
			if (this.model.attributes.virtualBackgroundEnabled) {
				this.model.disableVirtualBackground()
			} else {
				this.model.enableVirtualBackground()
			}
		},

		toggleScreenSharingMenu() {
			if (!this.isScreensharingAllowed) {
				return
			}

			// webrtcsupport considers screen share supported only via HTTPS, even if it is actually supported in the browser/desktop
			if (!this.model.getWebRtc().capabilities.supportScreenSharing && !IS_DESKTOP) {
				if (window.location.protocol === 'https:') {
					showMessage(t('spreed', 'Screen sharing is not supported by your browser.'))
				} else {
					showMessage(t('spreed', 'Screen sharing requires the page to be loaded through HTTPS.'))
				}
				return
			}

			if (!this.isScreensharing) {
				this.startShareScreen()
			}
		},

		showScreen() {
			if (this.isScreensharing) {
				emit('switch-screen-to-id', this.localCallParticipantModel.attributes.peerId)
			}
		},

		stopScreen() {
			this.model.stopSharingScreen()
		},

		startShareScreen(mode) {
			this.model.shareScreen(mode, function(err) {
				if (!err) {
					return
				}

				let extensionURL = null

				switch (err.name) {
					case 'HTTPS_REQUIRED':
						showMessage(t('spreed', 'Screensharing requires the page to be loaded through HTTPS.'))
						break
					case 'PERMISSION_DENIED':
					case 'NotAllowedError':
					case 'CEF_GETSCREENMEDIA_CANCELED': // Experimental, may go away in the future.
						break
					case 'FF52_REQUIRED':
						showMessage(t('spreed', 'Sharing your screen only works with Firefox version 52 or newer.'))
						break
					case 'EXTENSION_UNAVAILABLE':
						if (window.chrome) { // Chrome
							extensionURL = 'https://chrome.google.com/webstore/detail/screensharing-for-nextclo/kepnpjhambipllfmgmbapncekcmabkol'
						}

						if (extensionURL) {
							const text = t('spreed', 'Screensharing extension is required to share your screen.')
							const element = '<a href="' + extensionURL + '" target="_blank">' + escapeHtml(text) + '</a>'

							showMessage(element, { isHTML: true })
						} else {
							showMessage(t('spreed', 'Please use a different browser like Firefox or Chrome to share your screen.'))
						}
						break
					default:
						showMessage(t('spreed', 'An error occurred while starting screensharing.'))
						break
				}
			})
		},

		executeQualityWarningTooltipAction() {
			if (this.qualityWarningTooltip.action === '') {
				return
			}
			if (this.qualityWarningTooltip.action === 'disableScreenShare') {
				this.model.stopSharingScreen()
				this.dismissQualityWarningTooltip()
			} else if (this.qualityWarningTooltip.action === 'disableVirtualBackground') {
				this.model.disableVirtualBackground()
				this.dismissQualityWarningTooltip()
			} else if (this.qualityWarningTooltip.action === 'disableVideo') {
				this.model.disableVideo()
				this.dismissQualityWarningTooltip()
			}
		},

		dismissQualityWarningTooltip() {
			this.isQualityWarningTooltipDismissed = true
		},
	},
}
</script>

<style lang="scss" scoped>
.buttons-bar {
	display: flex;
	align-items: center;
	gap: 3px;
}

.buttons-bar #screensharing-menu button {
	width: 100%;
	height: auto;
}

/* Highlight the media buttons when enabled */
.buttons-bar button.screensharing-enabled {
	opacity: 1;
}

.buttons-bar button.no-screensharing-available {
	&, & * {
		opacity: .7;
	}
}

.hint {
	padding: 12px;
	max-width: 300px;
	text-align: start;
	&__actions {
		display: flex;
		flex-direction: row-reverse;
		justify-content: space-between;
		padding-top:4px;
	}
	&__button {
		height: var(--default-clickable-area);
	}
}

.trigger {
	display: flex;
	align-items: center;
	justify-content: center;
}
</style>
