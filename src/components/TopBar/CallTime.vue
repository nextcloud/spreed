<!--
  - @copyright Copyright (c) 2021 Marco Ambrosini <marcoambrosini@icloud.com>  -
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
	<NcPopover class="top-bar__button call-time"
		close-after-click="true"
		:menu-title="callTime"
		:shown.sync="showPopover"
		:class="{ 'call-time--wide': isWide }"
		:triggers="[]"
		:container="container">
		<template #trigger>
			<NcButton :disabled="!isRecording"
				:wide="true"
				type="tertiary"
				@click="showPopover = true">
				<template v-if="isRecording" #icon>
					<RecordCircle :size="20"
						fill-color="#e9322d" />
				</template>
				{{ formattedTime }}
			</ncbutton>
		</template>
		<NcButton type="tertiary-no-background"
			:wide="true"
			@click="stopRecording">
			<template #icon>
				<StopIcon :size="20" />
			</template>
			{{ t('spreed', 'Stop recording') }}
		</NcButton>
	</NcPopover>
</template>

<script>

import RecordCircle from 'vue-material-design-icons/RecordCircle.vue'
import StopIcon from 'vue-material-design-icons/Stop.vue'
import NcPopover from '@nextcloud/vue/dist/Components/NcPopover.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

export default {
	name: 'CallTime',

	components: {
		RecordCircle,
		StopIcon,
		NcPopover,
		NcButton,
	},

	props: {
		/**
		 * Unix timestamp representing the start of the call
		 */
		start: {
			type: Number,
			required: true,
		},

		isRecording: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
			callTime: undefined,
			showPopover: false,
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

		/**
		 * Calculates the stopwatch string given the callTime (ms)
		 *
		 * @return {string} The formatted time
		 */
		formattedTime() {
			let seconds = Math.floor((this.callTime / 1000) % 60)
			if (seconds < 10) {
				seconds = '0' + seconds
			}
			let minutes = Math.floor((this.callTime / (1000 * 60)) % 60)
			if (minutes < 10) {
				minutes = '0' + minutes
			}
			let hours = Math.floor((this.callTime / (1000 * 60 * 60)) % 24)
			if (hours < 10) {
				hours = '0' + hours
			}
			if (hours === '00') {
				return minutes + ' : ' + seconds
			} else {
				return hours + ' : ' + minutes + ' : ' + seconds
			}
		},

		isWide() {
			return this.formattedTime.length > 7
		},
	},

	mounted() {
		// Start the timer when mounted
		setInterval(this.computeElapsedTime, 1000)
	},

	beforeDestroy() {
		clearInterval(this.computeElapsedTime)
	},

	methods: {
		stopRecording() {
			this.$emit('stop-recording')
			this.showPopover = false
		},

		computeElapsedTime() {
			this.callTime = new Date() - this.callStart
		},
	},
}
</script>

<style lang="scss" scoped>
.call-time {
	display: flex;
	justify-content: center;
	align-items: center;
	height: 44px;
	font-weight: bold;
	color: #fff;
	width: 120px;

	&--wide {
		width: 180px;
	}
}

::v-deep .button-vue {
	justify-content: left !important;

	&:disabled {
		opacity: 1 !important;
	}
}

</style>
