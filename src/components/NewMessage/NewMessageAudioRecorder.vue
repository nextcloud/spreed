<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="audio-recorder">
		<NcButton
			v-if="!isRecording"
			:title="startRecordingTitle"
			:aria-label="startRecordingTitle"
			variant="tertiary"
			:disabled="!canStartRecording"
			@click="start">
			<template #icon>
				<IconMicrophoneOutline :size="20" />
			</template>
		</NcButton>
		<div v-else class="wrapper">
			<NcButton
				variant="error"
				:title="abortRecordingTitle"
				:aria-label="abortRecordingTitle"
				@click="abortRecording">
				<template #icon>
					<IconClose :size="20" />
				</template>
			</NcButton>
			<div class="audio-recorder__info">
				<div class="recording-indicator fadeOutIn" />
				<span class="time">
					{{ parsedRecordTime }}</span>
			</div>
			<NcButton
				variant="success"
				:title="stopRecordingTitle"
				:aria-label="stopRecordingTitle"
				:class="{ 'audio-recorder__trigger--recording': isRecording }"
				@click="stop">
				<template #icon>
					<IconCheck :size="20" />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { MediaRecorder } from 'extendable-media-recorder'
import NcButton from '@nextcloud/vue/components/NcButton'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconClose from 'vue-material-design-icons/Close.vue'
import IconMicrophoneOutline from 'vue-material-design-icons/MicrophoneOutline.vue'
import { useAudioEncoder } from '../../composables/useAudioEncoder.ts'
import { useGetToken } from '../../composables/useGetToken.ts'
import { mediaDevicesManager } from '../../utils/webrtc/index.js'

export default {
	name: 'NewMessageAudioRecorder',

	components: {
		IconMicrophoneOutline,
		IconClose,
		IconCheck,
		NcButton,
	},

	props: {
		disabled: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['recording', 'audioFile'],

	setup() {
		const encoderReady = useAudioEncoder()
		return {
			encoderReady,
			token: useGetToken(),
		}
	},

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

		startRecordingTitle() {
			return t('spreed', 'Record voice message')
		},

		stopRecordingTitle() {
			return t('spreed', 'End recording and send')
		},

		abortRecordingTitle() {
			return t('spreed', 'Dismiss recording')
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

	beforeUnmount() {
		this.killStreams()
	},

	methods: {
		t,
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
				const audioFile = new File([this.blob], fileName, { type: 'audio/wav' })
				this.$emit('audioFile', audioFile)
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
			const conversation = this.$store.getters.conversation(this.token).name
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
			this.audioStream?.getTracks().forEach((track) => track.stop())
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
			background-color: var(--color-border-error);
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
