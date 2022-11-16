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
	<div class="call-time">
		<NcPopover class="top-bar__button"
			close-after-click="true"
			:menu-title="callTime"
			:shown.sync="showPopover"
			:triggers="[]"
			:container="container">
			<template #trigger>
				<NcButton :disabled="!isRecording" type="tertiary" @click="showPopover = true">
					<template v-if="isRecording" #icon>
						<RecordCircle :size="20"
							fill-color="#e9322d" />
					</template>
					{{ callTime }}
				</ncbutton>
			</template>
			<NcButton type="tertiary-no-background"
				@click="stopRecording">
				<template #icon>
					<StopIcon :size="20" />
				</template>
				{{ t('spreed', 'Stop recording') }}
			</NcButton>
		</NcPopover>
	</div>
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
		 * The date object, if undefined the stopwatch is back to the initial
		 * state.
		 */
		start: {
			type: Date,
			default: undefined,
		},

		isRecording: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
			hours: undefined,
			minutes: undefined,
			seconds: undefined,
			showPopover: false,
		}
	},

	computed: {
		hasTime() {
			return this.start !== undefined
		},

		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		callTime() {
			return '11:11'
		},
	},

	methods: {
		stopRecording() {
			this.$emit('stop-recording')
			this.showPopover = false
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
}

::v-deep .button-vue:disabled {
	opacity: 1 !important;
}
</style>
