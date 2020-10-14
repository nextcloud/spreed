<!--
  - @copyright Copyright (c) 2019, Daniel Calviño Sánchez (danxuliu@gmail.com)
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div v-shortkey.push="['space']"
		@shortkey="toggleAudio">
		<div class="buttons-bar">
			<div id="muteWrapper">
				<button
					id="mute"
					v-shortkey="['m']"
					v-tooltip="audioButtonTooltip"
					:aria-label="audioButtonAriaLabel"
					:class="audioButtonClass"
					class="forced-white"
					@shortkey="toggleAudio"
					@click="toggleAudio" />
				<span v-show="model.attributes.audioAvailable"
					ref="volumeIndicator"
					class="volume-indicator"
					:style="{ 'height': currentVolumeIndicatorHeight + 'px' }" />
			</div>
			<button
				id="hideVideo"
				v-shortkey="['v']"
				v-tooltip="videoButtonTooltip"
				:aria-label="videoButtonAriaLabel"
				:class="videoButtonClass"
				class="forced-white"
				@shortkey="toggleVideo"
				@click="toggleVideo" />
			<button
				v-if="!screenSharingButtonHidden"
				id="screensharing-button"
				v-tooltip="screenSharingButtonTooltip"
				:aria-label="screenSharingButtonAriaLabel"
				:class="screenSharingButtonClass"
				class="app-navigation-entry-utils-menu-button forced-white"
				@click="toggleScreenSharingMenu" />
			<div id="screensharing-menu" :class="{ open: screenSharingMenuOpen }" class="app-navigation-entry-menu">
				<ul>
					<li v-if="!model.attributes.localScreen && splitScreenSharingMenu" id="share-screen-entry">
						<button id="share-screen-button" @click="shareScreen">
							<span class="icon-screen" />
							<span>{{ t('spreed', 'Share whole screen') }}</span>
						</button>
					</li>
					<li v-if="!model.attributes.localScreen && splitScreenSharingMenu" id="share-window-entry">
						<button id="share-window-button" @click="shareWindow">
							<span class="icon-share-window" />
							<span>{{ t('spreed', 'Share a single window') }}</span>
						</button>
					</li>
					<li v-if="model.attributes.localScreen" id="show-screen-entry">
						<button id="show-screen-button" @click="showScreen">
							<span class="icon-screen" />
							<span>{{ t('spreed', 'Show your screen') }}</span>
						</button>
					</li>
					<li v-if="model.attributes.localScreen" id="stop-screen-entry">
						<button id="stop-screen-button" @click="stopScreen">
							<span class="icon-screen-off" />
							<span>{{ t('spreed', 'Stop screensharing') }}</span>
						</button>
					</li>
				</ul>
			</div>
		</div>
		<div class="network-connection-state">
			<Popover
				v-if="qualityWarningTooltip"
				:boundaries-element="boundaryElement"
				:aria-label="qualityWarningAriaLabel"
				trigger="hover"
				:auto-hide="false"
				:open="showQualityWarningTooltip">
				<NetworkStrength2Alert
					slot="trigger"
					decorative
					fill-color="#e9322d"
					title=""
					:size="24"
					@mouseover="mouseover = true"
					@mouseleave="mouseover = false" />
				<div class="hint">
					<span>{{ qualityWarningTooltip.content }}</span>
					<div class="hint__actions">
						<button
							v-if="qualityWarningTooltip.action"
							class="primary hint__button"
							@click="executeQualityWarningTooltipAction">
							{{ qualityWarningTooltip.actionLabel }}
						</button>
						<button
							v-if="!isQualityWarningTooltipDismissed"
							class="hint__button"
							@click="isQualityWarningTooltipDismissed = true">
							{{ t('spreed', 'Dismiss') }}
						</button>
					</div>
				</div>
			</Popover>
		</div>
	</div>
</template>

<script>
import escapeHtml from 'escape-html'
import { showMessage } from '@nextcloud/dialogs'
import Popover from '@nextcloud/vue/dist/Components/Popover'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'
import SpeakingWhileMutedWarner from '../../../utils/webrtc/SpeakingWhileMutedWarner'
import NetworkStrength2Alert from 'vue-material-design-icons/NetworkStrength2Alert'
import { callAnalyzer } from '../../../utils/webrtc/index'
import { CONNECTION_QUALITY } from '../../../utils/webrtc/analyzers/PeerConnectionAnalyzer'

export default {

	name: 'LocalMediaControls',

	directives: {
		tooltip: Tooltip,
	},

	components: {
		NetworkStrength2Alert,
		Popover,
	},

	props: {
		model: {
			type: Object,
			required: true,
		},
		localCallParticipantModel: {
			type: Object,
			required: true,
		},
		screenSharingButtonHidden: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			mounted: false,
			speakingWhileMutedNotification: null,
			screenSharingMenuOpen: false,
			splitScreenSharingMenu: false,
			boundaryElement: document.querySelector('.main-view'),
			isQualityWarningTooltipDismissed: false,
			mouseover: false,
			callAnalyzer: callAnalyzer,
			qualityWarningInGracePeriodTimeout: null,
		}
	},

	computed: {

		audioButtonClass() {
			return {
				'icon-audio': this.model.attributes.audioAvailable && this.model.attributes.audioEnabled,
				'audio-disabled': this.model.attributes.audioAvailable && !this.model.attributes.audioEnabled,
				'icon-audio-off': !this.model.attributes.audioAvailable || !this.model.attributes.audioEnabled,
				'no-audio-available': !this.model.attributes.audioAvailable,
			}
		},

		audioButtonTooltip() {
			if (!this.model.attributes.audioAvailable) {
				return {
					content: t('spreed', 'No audio'),
					show: false,
				}
			}

			if (this.speakingWhileMutedNotification && !this.screenSharingMenuOpen) {
				return {
					content: this.speakingWhileMutedNotification,
					show: true,
				}
			}

			return {
				content: this.model.attributes.audioEnabled ? t('spreed', 'Mute audio (m)') : t('spreed', 'Unmute audio (m)'),
				show: false,
			}
		},

		audioButtonAriaLabel() {
			if (!this.model.attributes.audioAvailable) {
				return t('spreed', 'No audio')
			}
			return this.model.attributes.audioEnabled ? t('spreed', 'Mute audio') : t('spreed', 'Unmute audio')
		},

		currentVolumeIndicatorHeight() {
			// refs can not be accessed on the initial render, only after the
			// component has been mounted.
			if (!this.mounted) {
				return 0
			}

			// WebRTC volume goes from -100 (silence) to 0 (loudest sound in the
			// system); for the volume indicator only sounds above the threshold
			// are taken into account.
			let currentVolumeProportion = 0
			if (this.model.attributes.currentVolume > this.model.attributes.volumeThreshold) {
				currentVolumeProportion = (this.model.attributes.volumeThreshold - this.model.attributes.currentVolume) / this.model.attributes.volumeThreshold
			}

			const volumeIndicatorStyle = window.getComputedStyle ? getComputedStyle(this.$refs.volumeIndicator, null) : this.$refs.volumeIndicator.currentStyle

			const maximumVolumeIndicatorHeight = this.$refs.volumeIndicator.parentElement.clientHeight - (parseInt(volumeIndicatorStyle.bottom, 10) * 2)

			return maximumVolumeIndicatorHeight * currentVolumeProportion
		},

		videoButtonClass() {
			return {
				'icon-video': this.model.attributes.videoAvailable && this.model.attributes.videoEnabled,
				'video-disabled': this.model.attributes.videoAvailable && !this.model.attributes.videoEnabled,
				'icon-video-off': !this.model.attributes.videoAvailable || !this.model.attributes.videoEnabled,
				'no-video-available': !this.model.attributes.videoAvailable,
			}
		},

		videoButtonTooltip() {
			if (!this.model.attributes.videoAvailable) {
				return t('spreed', 'No camera')
			}

			if (this.model.attributes.videoEnabled) {
				return t('spreed', 'Disable video (v)')
			}

			if (!this.model.getWebRtc() || !this.model.getWebRtc().connection || this.model.getWebRtc().connection.getSendVideoIfAvailable()) {
				return t('spreed', 'Enable video (v)')
			}

			return t('spreed', 'Enable video (v) - Your connection will be briefly interrupted when enabling the video for the first time')
		},

		videoButtonAriaLabel() {
			if (!this.model.attributes.videoAvailable) {
				return t('spreed', 'No camera')
			}

			if (this.model.attributes.videoEnabled) {
				return t('spreed', 'Disable video')
			}

			if (!this.model.getWebRtc() || !this.model.getWebRtc().connection || this.model.getWebRtc().connection.getSendVideoIfAvailable()) {
				return t('spreed', 'Enable video')
			}

			return t('spreed', 'Enable video. Your connection will be briefly interrupted when enabling the video for the first time')
		},

		screenSharingButtonClass() {
			return {
				'icon-screen': this.model.attributes.localScreen,
				'screensharing-disabled': !this.model.attributes.localScreen,
				'icon-screen-off': !this.model.attributes.localScreen,
			}
		},

		screenSharingButtonTooltip() {
			if (this.screenSharingMenuOpen) {
				return null
			}

			return (this.model.attributes.localScreen || this.splitScreenSharingMenu) ? t('spreed', 'Screensharing options') : t('spreed', 'Enable screensharing')
		},

		screenSharingButtonAriaLabel() {
			if (this.screenSharingMenuOpen) {
				return ''
			}

			return (this.model.attributes.localScreen || this.splitScreenSharingMenu) ? t('spreed', 'Screensharing options') : t('spreed', 'Enable screensharing')
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

			const tooltip = {}
			if (!this.model.attributes.audioEnabled && this.model.attributes.videoEnabled && this.model.attributes.localScreen) {
				tooltip.content = t('spreed', 'Your internet connection or computer are busy and other participants might be unable to see you. To improve the situation try to disable your video while doing a screenshare.')
				tooltip.actionLabel = t('spreed', 'Disable video')
				tooltip.action = 'disableVideo'
			} else if (!this.model.attributes.audioEnabled && this.model.attributes.localScreen) {
				tooltip.content = t('spreed', 'Your internet connection or computer are busy and other participants might be unable to see your screen.')
				tooltip.actionLabel = ''
				tooltip.action = ''
			} else if (!this.model.attributes.audioEnabled && this.model.attributes.videoEnabled) {
				tooltip.content = t('spreed', 'Your internet connection or computer are busy and other participants might be unable to see you.')
				tooltip.actionLabel = ''
				tooltip.action = ''
			} else if (this.model.attributes.videoEnabled && this.model.attributes.localScreen) {
				tooltip.content = t('spreed', 'Your internet connection or computer are busy and other participants might be unable to understand and see you. To improve the situation try to disable your video while doing a screenshare.')
				tooltip.actionLabel = t('spreed', 'Disable video')
				tooltip.action = 'disableVideo'
			} else if (this.model.attributes.localScreen) {
				tooltip.content = t('spreed', 'Your internet connection or computer are busy and other participants might be unable to understand and see your screen. To improve the situation try to disable your screenshare.')
				tooltip.actionLabel = t('spreed', 'Disable screenshare')
				tooltip.action = 'disableScreenShare'
			} else if (this.model.attributes.videoEnabled) {
				tooltip.content = t('spreed', 'Your internet connection or computer are busy and other participants might be unable to understand and see you. To improve the situation try to disable your video.')
				tooltip.actionLabel = t('spreed', 'Disable video')
				tooltip.action = 'disableVideo'
			} else {
				tooltip.content = t('spreed', 'Your internet connection or computer are busy and other participants might be unable to understand you.')
				tooltip.actionLabel = ''
				tooltip.action = ''
			}

			return tooltip
		},

	},

	watch: {
		senderConnectionQualityIsBad: function(senderConnectionQualityIsBad) {
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

	created() {
		// The standard "getDisplayMedia" does not support pre-filtering the
		// type of display sources, so the unified menu is used in that case
		// too.
		if (window.navigator.userAgent.match('Firefox') && !window.navigator.mediaDevices.getDisplayMedia) {
			const firefoxVersion = parseInt(window.navigator.userAgent.match(/Firefox\/(.*)/)[1], 10)
			this.splitScreenSharingMenu = (firefoxVersion >= 52)
		}
	},

	mounted() {
		this.mounted = true

		this.speakingWhileMutedWarner = new SpeakingWhileMutedWarner(this.model, this)
	},

	beforeDestroy() {
		this.speakingWhileMutedWarner.destroy()
	},

	methods: {

		toggleAudio() {
			if (!this.model.attributes.audioAvailable) {
				return
			}

			if (this.model.attributes.audioEnabled) {
				this.model.disableAudio()
			} else {
				this.model.enableAudio()
			}
		},

		setSpeakingWhileMutedNotification(message) {
			this.speakingWhileMutedNotification = message
		},

		toggleVideo() {
			if (!this.model.attributes.videoAvailable) {
				return
			}

			if (this.model.attributes.videoEnabled) {
				this.model.disableVideo()
			} else {
				this.model.enableVideo()
			}
		},

		toggleScreenSharingMenu() {
			if (!this.model.getWebRtc().capabilities.supportScreenSharing) {
				if (window.location.protocol === 'https:') {
					showMessage(t('spreed', 'Screen sharing is not supported by your browser.'))
				} else {
					showMessage(t('spreed', 'Screen sharing requires the page to be loaded through HTTPS.'))
				}
				return
			}

			if (this.model.attributes.localScreen || this.splitScreenSharingMenu) {
				this.screenSharingMenuOpen = !this.screenSharingMenuOpen
			}

			if (!this.model.attributes.localScreen && !this.splitScreenSharingMenu) {
				this.startShareScreen()
			}
		},

		shareScreen() {
			if (!this.model.attributes.localScreen) {
				this.startShareScreen('screen')
			}

			this.screenSharingMenuOpen = false
		},

		shareWindow() {
			if (!this.model.attributes.localScreen) {
				this.startShareScreen('window')
			}

			this.screenSharingMenuOpen = false
		},

		showScreen() {
			if (this.model.attributes.localScreen) {
				this.$emit('switchScreenToId', this.localCallParticipantModel.attributes.peerId)
			}

			this.screenSharingMenuOpen = false
		},

		stopScreen() {
			this.model.stopSharingScreen()

			this.screenSharingMenuOpen = false
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
				this.isQualityWarningTooltipDismissed = true
			} else if (this.qualityWarningTooltip.action === 'disableVideo') {
				this.model.disableVideo()
				this.isQualityWarningTooltipDismissed = true
			}
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../../assets/variables.scss';

.forced-white {
	filter: drop-shadow(1px 1px 4px var(--color-box-shadow));
}

#screensharing-menu {
	bottom: 44px;
	left: calc(50% - 40px);
	right: initial;
	color: initial;
	text-shadow: initial;
	font-size: 13px;
}

#screensharing-menu.app-navigation-entry-menu:after {
	top: 100%;
	left: calc(50% - 5px);
	border-top-color: #fff;
	border-bottom-color: transparent;
}

.buttons-bar button {
	background-color: transparent;
	border: none;
	margin: 0;
	width: 44px;
	height: 44px;
	background-size: 24px;
}

.buttons-bar #screensharing-menu button {
	width: 100%;
	height: auto;
}

.buttons-bar button.audio-disabled,
.buttons-bar button.video-disabled,
.buttons-bar button.screensharing-disabled {
	opacity: .7;
}

.buttons-bar button.audio-disabled:not(.no-audio-available),
.buttons-bar button.video-disabled:not(.no-video-available),
.buttons-bar button.screensharing-disabled {
	&:hover,
	&:focus {
		opacity: 1;
	}
}

.buttons-bar button.no-audio-available,
.buttons-bar button.no-video-available {
	opacity: .7;
	cursor: not-allowed;
}

.buttons-bar button.no-audio-available:active,
.buttons-bar button.no-video-available:active {
	background-color: transparent;
}

#muteWrapper {
	display: inline-block;

	/* Make the wrapper the positioning context of the volume indicator. */
	position: relative;
}

#muteWrapper .volume-indicator {
	position: absolute;

	width: 3px;
	right: 0;

	/* The button height is 44px; the volume indicator button is 36px at
	* maximum, but its value will be changed based on the current volume; the
	* height change will reveal more or less of the gradient, which has
	* absolute dimensions and thus does not change when the height changes. */
	height: 36px;
	bottom: 4px;

	background: linear-gradient(0deg, green, yellow, red 36px);

	opacity: 0.7;
}

#muteWrapper .icon-audio-off + .volume-indicator {
	background: linear-gradient(0deg, gray, white 36px);
}

.network-connection-state {
	position: absolute;
	bottom: 3px;
	right: 45px;
	width: 32px;
	height: 32px;
}

.hint {
	padding: 12px;
	max-width: 300px;
	text-align: left;
	&__actions {
		display: flex;
		flex-direction: row-reverse;
		justify-content: space-between;
		padding-top:4px;
	}
	&__button {
		height: $clickable-area;
	}
}
</style>
