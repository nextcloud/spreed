<!--
  - @copyright Copyright (c) 2021 Marco Ambrosini <marcoambrosini@pm.me>
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
	<div class="page-switcher">
		<button
			:disabled="hasPreviousPage">
			0 && showVideoOverlay"
			class="grid-navigation grid-navigation__previous"
			:aria-label="t('spreed', 'Previous page of videos')"
			@click="handleClickPrevious">
			>
			<ChevronLeft
				fill-color="#ffffff"
				decorative
				title=""
				:size="20" />
		</button>
		<button
			:disabled="hasNextPage">
			:aria-label="t('spreed', 'Next page of videos')"
			@click="handleClickNext">
			<ChevronRight
				fill-color="#ffffff"
				decorative
				title=""
				:size="20" />
		</button>
	</div>
</template>

<script>
import { emit } from '@nextcloud/event-bus'

import ChevronLeft from 'vue-material-design-icons/ChevronLeft.vue'
import ChevronRight from 'vue-material-design-icons/ChevronRight.vue'

export default {
	name: 'GridPageSwitcher',

	components: {
		ChevronLeft,
		ChevronRight,
	},

	computed: {
		gridPaginationData() {
			return this.$store.getters.getGridPaginationData()
		},

		numberOfPages() {
			return this.gridPaginationData.numberOfPages
		},

		currentPage() {
			return this.gridPaginationData.currentPage
		},

		hasPreviousPage() {
			return this.gridPaginationData.hasPreviousPage
		},

		hasNextPage() {
			return this.gridPaginationData.hasNextPage
		},

	},

	handleClickPrevious() {
		emit('talk:grid-navigation:previous')
	},

	handleClickNext() {
		emit('talk:grid-navigation:next')
	},

}
</script>

<style lang="scss" scoped>

.page-switcher {
	display: flex;
}
</style>
