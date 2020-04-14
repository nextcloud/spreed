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
	<div
		class="grid"
		:style="{ gridTemplateColumns: `repeat(${displayedColumns}, minmax(${minWidth}px, 1fr))`, width: `${gridWidth}px`}">
		<div
			v-for="video in displayedVideos"
			:key="video"
			class="video"
			v-text="video" />
	</div>
</template>

<script>
export default {
	name: 'GridView',

	props: {
		gridWidth: {
			type: Number,
			required: true,
		},
	},

	data() {
		return {
			videos: Array.from(Array(50).keys()),
		}
	},

	computed: {
		// Video components will need to accept this min width as a prop
		minWidth() {
			return 300
		},
		// The number of columns is always the square root of the number of videos
		columns() {
			return Math.ceil((Math.sqrt(this.videos.length)))
		},
		rows() {
			return Math.floor(this.columns / 2) + 1
		},
		minGridWidth() {
			return this.minWidth * this.columns
		},
		isSwipable() {
			return this.gridWidth < this.minGridWidth
		},
		displayedColumns() {
			return Math.floor(this.gridWidth / this.minWidth)
		},
		displayedVideos() {
			return this.videos.slice(0, this.rows * this.displayedColumns)
		},
	},
}
</script>

<style lang="scss" scoped>
.grid {
	display: grid;
	height: 100%;
	width: 100%;
	overflow: scroll;
}

.video {
	min-height: 50px;
	background-color: blue;
	border: 5px solid red;
	color: white;
}

.video:last-child {
	grid-column-end: -1;
}
</style>
