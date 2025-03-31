<!--
  - @copyright Copyright (c) 2022 Daniel Calviño Sánchez <danxuliu@gmail.com>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div class="button-holder">
		<NcButton type="tertiary-on-primary"
			class="sidebar-button"
			:aria-label="ariaLabel"
			@click="$emit('click')">
			<template #icon>
				<MenuPeople :size="20" />
			</template>
		</NcButton>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import MenuPeople from './assets/missingMaterialDesignIcons/MenuPeople.vue'

export default {

	name: 'PublicShareSidebarTrigger',

	components: {
		NcButton,
		MenuPeople,
	},

	props: {
		sidebarState: {
			type: Object,
			required: true,
		},
	},

	emits: ['click'],

	computed: {
		ariaLabel() {
			if (this.sidebarState.isOpen) {
				return t('spreed', 'Close Talk sidebar')
			}

			return t('spreed', 'Open Talk sidebar')
		},
	},

}
</script>

<style scoped>
.button-holder {
	margin: 2px 5px 2px 2px;
	display: flex;
	justify-content: center;
	height: 44px !important;

	& .sidebar-button {
		color: #ffffff;
		filter: var(--background-image-invert-if-bright);
	}
}
</style>
