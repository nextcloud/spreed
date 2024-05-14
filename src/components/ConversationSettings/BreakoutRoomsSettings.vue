<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="breakout-rooms-settings">
		<p class="breakout-rooms-settings__hint">
			{{ hintText }}
		</p>
		<NcButton type="secondary"
			@click="openBreakoutRoomsEditor">
			<template #icon>
				<DotsCircle :size="20" />
			</template>
			{{ t('spreed', 'Set up breakout rooms for this conversation') }}
		</NcButton>
	</div>
	<!-- Breakout rooms editor -->
	<BreakoutRoomsEditor v-if="showBreakoutRoomsEditor"
		:token="token"
		@close="showBreakoutRoomsEditor = false" />
</template>

<script>
import DotsCircle from 'vue-material-design-icons/DotsCircle.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import BreakoutRoomsEditor from '../BreakoutRoomsEditor/BreakoutRoomsEditor.vue'

export default {
	name: 'BreakoutRoomsSettings',

	components: {
		NcButton,
		BreakoutRoomsEditor,
		DotsCircle,
	},

	props: {
		/**
		 * The conversation's token
		 */
		token: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			showBreakoutRoomsEditor: false,
		}
	},

	computed: {
		hintText() {
			return t('spreed', 'Breakout rooms') // FIXME
		},
	},

	methods: {
		openBreakoutRoomsEditor() {
			this.showBreakoutRoomsEditor = true
		},
	},
}
</script>

<style lang="scss" scoped>
.breakout-rooms-settings {
	&__hint{
		margin-bottom: calc(var(--default-grid-baseline) * 2);
		color: var(--color-text-maxcontrast);
	}
}

</style>
