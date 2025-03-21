<!--
  - @copyright Copyright (c) 2021 Marco Ambrosini <marcoambrosini@icloud.com>
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
	<div class="audio-recorder">
		<NcButton v-if="!isRecording"
			v-tooltip.auto="{
				content: startRecordingTooltip,
				delay: tooltipDelay,
			}"
			:aria-label="startRecordingTooltip"
			type="tertiary"
			:disabled="!canStartRecording"
			@click="start">
			<template #icon>
				<Microphone :size="16" />
			</template>
		</NcButton>
		<div v-else class="wrapper">
			<NcButton v-tooltip.auto="{
					content: abortRecordingTooltip,
					delay: tooltipDelay,
				}"
				type="error"
				:aria-label="abortRecordingTooltip"
				@click="abortRecording">
				<template #icon>
					<Close :size="16" />
				</template>
			</NcButton>
			<div class="audio-recorder__info">
				<div class="recording-indicator fadeOutIn" />
				<span class="time">
					{{ parsedRecordTime }}</span>
			</div>
			<NcButton v-tooltip.auto="{
					content: stopRecordingTooltip,
					delay: tooltipDelay,
				}"
				type="success"
				:aria-label="stopRecordingTooltip"
				:class="{'audio-recorder__trigger--recording': isRecording}"
				@click="stop">
				<template #icon>
					<Check :size="16" />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script>
import { MediaRecorder } from 'extendable-media-recorder'

import Check from 'vue-material-design-icons/Check.vue'
import Close from 'vue-material-design-icons/Close.vue'
import Microphone from 'vue-material-design-icons/Microphone.vue'

import { showError } from '@nextcloud/dialogs'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import { mediaDevicesManager } from '../../utils/webrtc/index.js'

export default {
	name: 'NewMessageAudioRecorder',

	components: {
		Microphone,
		Close,
		Check,
		NcButton,
	},

	props: {
		disabled: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['recording', 'audio-file'],

	data() {
		return {
			// The audio stream object
			audioStream: null,
			// The media recorder which generate the recorded chunks
			mediaRecorder: null,
			// The chunks array
			chunks: [],
			// The final audio file blob
			blob: null,
			// Switched to true if the recording is aborted
			aborted: false,
			// recordTimer
			recordTimer: null,
			// the record timer
			recordTime: {
				minutes: 0,
				seconds: 0,
			},
		}
	},

	computed: {
		// Recording state of the mediaRecorder
		isRecording() {
			if (this.mediaRecorder) {
				return this.mediaRecorder.state === 'recording'
			} else {
				return false
			}
		},

		parsedRecordTime() {
			const seconds = this.recordTime.seconds.toString().length === 2 ? this.recordTime.seconds : `0${this.recordTime.seconds}`
			const minutes = this.recordTime.minutes.toString().length === 2 ? this.recordTime.minutes : `0${this.recordTime.minutes}`
			return `${minutes}:${seconds}`
		},

		tooltipDelay() {
			return { show: 500, hide: 200 }
		},

		startRecordingTooltip() {
			return t('spreed', 'Record voice message')
		},

		stopRecordingTooltip() {
			return t('spreed', 'End recording and send')
		},

		abortRecordingTooltip() {
			return t('spreed', 'Dismiss recording')
		},

		encoderReady() {
			return this.$store.getters.encoderReady
		},

		canStartRecording() {
			if (this.disabled) {
				return false
			} else {
				return this.encoderReady
			}
		},
	},

	watch: {

		isRecording(newValue) {
			console.debug('isRecording', newValue)
		},
	},

	mounted() {
		this.$store.dispatch('initializeAudioEncoder')
	},

	beforeDestroy() {
		this.killStreams()
	},

	methods: {
		/**
		 * Initialize the media stream and start capturing the audio
		 */
		async start() {
			if (!this.canStartRecording) {
				return
			}
			// Create new audio stream
			try {
				this.audioStream = await mediaDevicesManager.getUserMedia({
					audio: true,
					video: false,
				})
			} catch (exception) {
				console.debug(exception)
				this.killStreams()
				if (exception.name === 'NotAllowedError') {
					showError(t('spreed', 'Access to the microphone was denied'))
				} else {
					showError(t('spreed', 'Microphone either not available or disabled in settings'))
				}
				return
			}

			// Create a media recorder to capture the stream
			try {
				this.mediaRecorder = new MediaRecorder(this.audioStream, {
					mimeType: 'audio/wav',
				})
			} catch (exception) {
				console.debug(exception)
				this.killStreams()
				this.audioStream = null
				showError(t('spreed', 'Error while recording audio'))
				return
			}

			// Add event handler to onstop
			this.mediaRecorder.onstop = this.generateFile

			// Add event handler to ondataavailable
			this.mediaRecorder.ondataavailable = (e) => {
				this.chunks.push(e.data)
			}

			try {
				// Start the recording
				this.mediaRecorder.start()
			} catch (exception) {
				console.debug(exception)
				this.aborted = true
				this.stop()
				this.killStreams()
				this.resetComponentData()
				showError(t('spreed', 'Error while recording audio'))
				return
			}

			console.debug(this.mediaRecorder.state)

			// Start the timer
			this.recordTimer = setInterval(() => {
				if (this.recordTime.seconds === 59) {
					this.recordTime.minutes++
					this.recordTime.seconds = 0
				}
				this.recordTime.seconds++
			}, 1000)
			// Forward an event to let the parent NewMessage component
			// that there's an undergoing recording operation
			this.$emit('recording', true)
		},

		/**
		 * Stop the mediaRecorder
		 */
		stop() {
			this.mediaRecorder.stop()
			clearInterval(this.recordTimer)
			this.$emit('recording', false)
		},

		/**
		 * Generate the file
		 */
		generateFile() {
			this.killStreams()
			if (!this.aborted) {
				this.blob = new Blob(this.chunks, { type: 'audio/wav' })
				// Generate file name
				const fileName = this.generateFileName()
				// Convert blob to file
				const audioFile = new File([this.blob], fileName)
				audioFile.localURL = window.URL.createObjectURL(this.blob)
				this.$emit('audio-file', audioFile)
				this.$emit('recording', false)
			}
			this.resetComponentData()
		},

		/**
		 * Aborts the recording operation.
		 */
		abortRecording() {
			this.aborted = true
			this.stop()
		},

		/**
		 * Resets this component to its initial state
		 */
		resetComponentData() {
			this.audioStream = null
			this.mediaRecorder = null
			this.chunks = []
			this.blob = null
			this.aborted = false
			this.recordTime = {
				minutes: 0,
				seconds: 0,
			}
		},

		generateFileName() {
			const token = this.$store.getters.getToken()
			const conversation = this.$store.getters.conversation(token).name
				.replace(/\/\\:%/gi, ' ') // Replace chars that are not allowed on the filesystem
				.replace(/ +/gi, ' ') // Replace multiple replacement spaces with 1
			const today = new Date()
			let time = today.getFullYear() + '-' + ('0' + today.getMonth()).slice(-2) + '-' + ('0' + today.getDay()).slice(-2)
			time += ' ' + ('0' + today.getHours()).slice(-2) + '-' + ('0' + today.getMinutes()).slice(-2) + '-' + ('0' + today.getSeconds()).slice(-2)
			const name = t('spreed', 'Talk recording from {time} ({conversation})', { time, conversation })
			return name.substring(0, 146) + '.wav'
		},

		/**
		 * Stop the audio streams
		 */
		killStreams() {
			this.audioStream?.getTracks().forEach(track => track.stop())
		},
	},

}
</script>

<style lang="scss" scoped>

.audio-recorder {
	display: flex;
	// Audio record button

	&__info {
		width: 86px;
		display: flex;
		justify-content: center;
		align-items: center;
		.time {
			flex: 0 0 50px;
		}
		.recording-indicator {
			width: 16px;
			height: 16px;
			flex: 0 0 16px;
			border-radius: 8px;
			background-color: var(--color-error);
			margin: 8px;
		}
	}
}

.wrapper {
	display: flex;
}

@keyframes fadeOutIn {
	0% { opacity:1; }
	50% { opacity:.3; }
	100% { opacity:1; }
}

.fadeOutIn {
	animation: fadeOutIn 3s infinite;
}

</style>
