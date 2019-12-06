<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @author Marco Ambrosini <marcoambrosini@pm.me>
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
	<div class="top-bar">
		<CallButton />
		<Actions v-if="showOpenSidebarButton" class="top-bar__button" close-after-click="true">
			<ActionButton :icon="iconMenuPeople" @click="handleClick" />
		</Actions>
	</div>
</template>

<script>
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import CallButton from './CallButton'

export default {
	name: 'TopBar',

	components: {
		ActionButton,
		Actions,
		CallButton,
	},

	props: {
		forceWhiteIcons: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		iconMenuPeople() {
			if (this.forceWhiteIcons) {
				return 'forced-white icon-menu-people'
			}
			return 'icon-menu-people'
		},

		showOpenSidebarButton() {
			return !this.$store.getters.getSidebarStatus()
		},
	},

	methods: {
		handleClick() {
			this.$store.dispatch('showSidebar')
		},
	},
}
</script>

<style lang="scss" scoped>

@import '../../assets/variables';

.top-bar {
	height: $top-bar-height;
	position: absolute;
	top: 0;
	right: 0;
	display: flex;
	z-index: 10;
	justify-content: flex-end;
	padding: 0 6px;
	&__button {
		align-self: center;
	}

}
</style>
