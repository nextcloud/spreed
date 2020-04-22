<!--
  - @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@pm.me>
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
	<div class="call-view">
		<transition name="fade">
			<GridView v-if="isGrid"
				v-bind="$attrs"
				:dev-mode="false"
				:dummies="2" />
		</transition>
		<transition name="fade">
			<PromotedView v-if="!isGrid" v-bind="$attrs" />
		</transition>
	</div>
</template>

<script>
import GridView from './GridView/GridView'
import PromotedView from './PromotedView/PromotedView'
export default {
	name: 'CallView',

	components: {
		GridView,
		PromotedView,
	},

	props: {
		isGrid: {
			type: Boolean,
			default: true,
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../assets/variables.scss';

.call-view {
	width: 100%;
	height: 100%;
	overflow: hidden;
	background-color: black,
}
</style>
