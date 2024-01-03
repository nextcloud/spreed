<!--
  - @copyright Copyright (c) 2022 Marco Ambrosini <marcoambrosini@icloud.com>  -
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
	<NcPopover class="call-time"
		:shown.sync="showPopover"
		:focus-trap="isShowRecordingControls"
		:triggers="[]"
		:container="container">
		<template #trigger>
			<NcButton :disabled="isButtonDisabled"
				:wide="true"
				:title="recordingButtonTitle"
				type="tertiary"
				@click="showPopover = !showPopover">
				<template v-if="isRecording || isStartingRecording" #icon>
					<RecordCircle v-if="isRecording"
						:size="20"
						fill-color="#e9322d" />
					<RecordCircle v-else
						:size="20"
						fill-color="var(--color-loading-light)" />
				</template>
				{{ formattedTime(callTime) }}
			</NcButton>
		</template>

		<!--one hour hint-->
		<span v-if="isCallDurationHintShown" class="call-duration-hint">
			{{ t('spreed', 'The call has been running for one hour.') }}
		</span>

		<!--Moderator's buttons-->
		<template v-if="isShowRecordingControls">
			<hr v-if="isCallDurationHintShown" class="solid">
			<NcButton v-if="isStartingRecording"
				type="tertiary-no-background"
				:wide="true"
				@click="stopRecording">
				<template #icon>
					<NcLoadingIcon :size="20" />
				</template>
				{{ t('spreed', 'Cancel recording start') }}
			</NcButton>
			<NcButton v-else
				type="tertiary-no-background"
				:wide="true"
				@click="stopRecording">
				<template #icon>
					<StopIcon :size="20" />
				</template>
				{{ t('spreed', 'Stop recording') }}
			</NcButton>
		</template>
	</NcPopover>
</template>

<script>
import RecordCircle from 'vue-material-design-icons/RecordCircle.vue'
import StopIcon from 'vue-material-design-icons/Stop.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcPopover from '@nextcloud/vue/dist/Components/NcPopover.js'

import { CALL } from '../../constants.js'
import { formattedTime } from '../../utils/formattedTime.js'

export default {
	name: 'CallTime',

	components: {
		NcButton,
		NcLoadingIcon,
		NcPopover,
		RecordCircle,
		StopIcon,
	},

	props: {
		/**
		 * Unix timestamp representing the start of the call
		 */
		start: {
			type: Number,
			required: true,
		},
	},

	data() {
		return {
			callTime: undefined,
			showPopover: false,
			isCallDurationHintShown: false,
			timer: null,
			untilCallDurationHintShown: null,
		}
	},

	computed: {
		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		/**
		 * Create date object based on the unix time received from the API
		 *
		 * @return {Date} The date object
		 */
		callStart() {
			return new Date(this.start * 1000)
		},

		token() {
			return this.$store.getters.getToken()
		},

		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		isStartingRecording() {
			return this.conversation.callRecording === CALL.RECORDING.VIDEO_STARTING
				|| this.conversation.callRecording === CALL.RECORDING.AUDIO_STARTING
		},

		isRecording() {
			return this.conversation.callRecording === CALL.RECORDING.VIDEO
				|| this.conversation.callRecording === CALL.RECORDING.AUDIO
		},

		isShowRecordingControls() {
			return this.$store.getters.isModerator && (this.isStartingRecording || this.isRecording)
		},

		isButtonDisabled() {
			return !this.isShowRecordingControls && !this.isCallDurationHintShown
		},

		recordingButtonTitle() {
			if (this.isStartingRecording) {
				return t('spreed', 'Starting the recording')
			} else if (this.isRecording) {
				return t('spreed', 'Recording')
			}

			return ''
		},
	},

	watch: {
		callTime(value) {
			if (value && !this.untilCallDurationHintShown) {
				this.untilCallDurationHintShown = (1000 * 60 * 60) - value + 1000
				setTimeout(() => {
					this.showCallDurationHint()
				}, this.untilCallDurationHintShown)
			}
		},
	},

	mounted() {
		// Start the timer when mounted
		this.timer = setInterval(this.computeElapsedTime, 1000)
	},

	beforeDestroy() {
		clearInterval(this.timer)
	},

	methods: {
		/**
		 * Calculates the stopwatch string given the callTime (ms)
		 *
		 */
		formattedTime,

		stopRecording() {
			this.$store.dispatch('stopCallRecording', {
				token: this.token,
			})
			this.showPopover = false
		},

		computeElapsedTime() {
			if (this.start === 0) {
				return
			}
			this.callTime = new Date() - this.callStart
		},

		showCallDurationHint() {

			this.showPopover = true
			this.isCallDurationHintShown = true

			// close the popover after 10 seconds
			if (this.$store.getters.windowIsVisible()) {
				setTimeout(() => {
					this.showPopover = false
				}, 10000)
			} else {
				// add event listener if the call view is not visible
				window.onfocus = () => setTimeout(() => {
					this.showPopover = false
				}, 10000)
			}
		},
	},
}
</script>

<style lang="scss" scoped>

.solid {
	margin: 0;
}
.call-duration-hint {
	display: flex;
	padding: calc(var(--default-grid-baseline) * 2);
}
.call-time {
	display: flex;
	justify-content: center;
	align-items: center;
	height: var(--default-clickable-area);
	font-weight: bold;
}

:deep(.button-vue) {
	justify-content: left !important;
	color: #fff !important;

	&:disabled {
		opacity: 1 !important;
		pointer-events: none;
	}
}

</style>
