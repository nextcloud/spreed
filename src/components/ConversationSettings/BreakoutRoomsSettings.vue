<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="breakout-rooms-settings">
		<p class="breakout-rooms-settings__hint">
			{{ hintText }}
		</p>
		<NcButton
			variant="secondary"
			@click="openBreakoutRoomsEditor">
			<template #icon>
				<IconDotsCircle :size="20" />
			</template>
			{{ t('spreed', 'Set up breakout rooms for this conversation') }}
		</NcButton>
	</div>
	<!-- Breakout rooms editor -->
	<BreakoutRoomsEditor
		v-if="showBreakoutRoomsEditor"
		:token="token"
		@close="showBreakoutRoomsEditor = false" />
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import IconDotsCircle from 'vue-material-design-icons/DotsCircle.vue'
import BreakoutRoomsEditor from '../BreakoutRoomsEditor/BreakoutRoomsEditor.vue'

export default {
	name: 'BreakoutRoomsSettings',

	components: {
		NcButton,
		BreakoutRoomsEditor,
		IconDotsCircle,
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
		t,
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
