<!--
  - @copyright Copyright (c) 2022 Marco Ambrosini <marcoambrosini@icloud.com>
  -
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
	<Fragment>
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
	</Fragment>
</template>

<script>
import { Fragment } from 'vue-frag'

import DotsCircle from 'vue-material-design-icons/DotsCircle.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import BreakoutRoomsEditor from '../BreakoutRoomsEditor/BreakoutRoomsEditor.vue'

export default {
	name: 'BreakoutRoomsSettings',

	components: {
		NcButton,
		BreakoutRoomsEditor,
		Fragment,
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
