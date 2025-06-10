<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcPopover class="call-time"
		:shown.sync="showPopover"
		:focus-trap="isShowRecordingControls"
		:triggers="[]">
		<template #trigger>
			<NcButton :disabled="isButtonDisabled"
				:wide="true"
				:title="recordingButtonTitle"
				variant="tertiary"
				@click="showPopover = !showPopover">
				<template v-if="isRecording || isStartingRecording" #icon>
					<IconRecordCircle v-if="isRecording"
						:size="20"
						fill-color="#e9322d" />
					<IconRecordCircle v-else
						:size="20"
						fill-color="var(--color-loading-light)" />
				</template>
				<span class="call-time__text">
					<span class="call-time__placeholder">{{ placeholderCallTime }}</span>
					<span>{{ formattedCallTime }}</span>
				</span>
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
				variant="tertiary-no-background"
				:wide="true"
				@click="stopRecording">
				<template #icon>
					<NcLoadingIcon :size="20" />
				</template>
				{{ t('spreed', 'Cancel recording start') }}
			</NcButton>
			<NcButton v-else
				variant="tertiary-no-background"
				:wide="true"
				@click="stopRecording">
				<template #icon>
					<IconStop :size="20" />
				</template>
				{{ t('spreed', 'Stop recording') }}
			</NcButton>
		</template>
	</NcPopover>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import IconRecordCircle from 'vue-material-design-icons/RecordCircle.vue'
import IconStop from 'vue-material-design-icons/Stop.vue'
import { useDocumentVisibility } from '../../composables/useDocumentVisibility.ts'
import { CALL } from '../../constants.ts'
import { formattedTime } from '../../utils/formattedTime.ts'

const ONE_HOUR_MS = 60 * 60 * 1000

export default {
	name: 'CallTime',

	components: {
		NcButton,
		NcLoadingIcon,
		NcPopover,
		IconRecordCircle,
		IconStop,
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

	setup() {
		return {
			isDocumentVisible: useDocumentVisibility(),
		}
	},

	data() {
		return {
			callTime: undefined,
			showPopover: false,
			isCallDurationHintShown: false,
			timer: null,
		}
	},

	computed: {
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

		formattedCallTime() {
			return formattedTime(this.callTime)
		},

		placeholderCallTime() {
			return this.formattedCallTime.replace(/\d/g, '0')
		},
	},

	watch: {
		callTime(value) {
			if (value > ONE_HOUR_MS && value < (ONE_HOUR_MS + 10000) && !this.isCallDurationHintShown) {
				this.showCallDurationHint()
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
		t,

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
			if (this.isDocumentVisible) {
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

	&__text {
		display: flex;
		flex-direction: column;
		align-items: center;
	}

	&__placeholder {
		height: 0;
		overflow: hidden;
	}
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
