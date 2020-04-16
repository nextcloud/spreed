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
	<div class="wrapper">
		<div
			class="grid"
			:style="{ gridTemplateColumns: `repeat(${columns},  minmax(${minWidth}px, 1fr))`, gridTemplateRows: `repeat(${rows}, minmax(${minHeight}px, 1fr)))`}">
			<div
				v-for="video in displayedVideos"
				:key="video"
				class="video"
				v-text="video" />
		</div>
		<button v-if="hasNextPage"
			class="grid-navigation next"
			@click="handleClickNext">
			Next
		</button>
		<button v-if="hasPreviousPage"
			class="grid-navigation previous"
			@click="handleClickPrevious">
			Previous
		</button>
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
		gridHeight: {
			type: Number,
			required: true,
		},
		/**
		 * Max number of videos per page. `0`, the default value, means no cap
		 */
		videosCap: {
			type: Number,
			default: 0,
		},
	},

	data() {
		return {
			videos: Array.from(Array(99).keys()),
			// Min width and height of the video components
			minWidth: 200,
			minHeight: 200,
			// Array of currently displayed videos
			displayedVideos: [],
			columns: 0,
			rows: 0,
		}
	},

	computed: {

		// Number of videos TODO: link it to the real one
		videosCount() {
			return this.videos.length
		},

		// The aspect ratio of main-view and of the grid itself
		gridAspectRatio() {
			return this.gridWidth / this.gridHeight
		},

		// Max number of columns given the size of the parent
		columnsMax() {
			if (Math.floor(this.gridWidth / this.minWidth) < 1) {
				// Return at least 1 column
				return 1.2
			} else {
				return Math.floor(this.gridWidth / this.minWidth)
			}
		},

		// Max number of rows given the size of the parent
		rowsMax() {
			if (Math.floor(this.gridHeight / this.minHeight) < 1) {
				// Return at least 1 row
				return 1
			} else {
				return Math.floor(this.gridHeight / this.minHeight)
			}
		},

		hasNextPage() {
			if (this.displayedVideos !== []) {
				return this.displayedVideos[this.displayedVideos.length - 1] !== this.videos[this.videos.length - 1]
			} else {
				return false
			}
		},

		hasPreviousPage() {
			if (this.displayedVideos !== []) {
				return this.displayedVideos[0] !== this.videos[0]
			} else {
				return false
			}
		},
	},

	watch: {
		gridAspectRatio() {
		// If the aspect ratio changes, rebuild the grid
			this.makeGrid()
		},
	},

	beforeMount() {
		this.makeGrid()
	},

	methods: {
		makeGrid() {
		// Start by assigning the max possible value to rows and columns. This
		// will fit as many video components as possible given the parent's
		// dimensions.
			this.columns = this.columnsMax
			this.rows = this.rowsMax
			// However, if we have only a couple of videos to display and a very big
			// window, we now have a lot of columns and rows, and our video components
			// would be too small. To solve this, we shrink this 'virtual grid' we've
			// just created to fit the number of elements that we have.
			if (this.videosCap !== 0) {
				this.shrinkGrid(this.videosCap)
			}
			this.shrinkGrid(this.videosCount)
			// Once the grid is done, populate it with video components
			this.displayedVideos = this.videos.slice(0, this.rows * this.columns)

		},

		shrinkGrid(videos) {
		// Max available grid slots given parent's dimensions and the minimum video
		// dimensions
			let slots = this.columns * this.rows
			// Run this code only if we don't have an 'overflow' of videos.
			if (videos < slots) {
			// The aspect ratio of the virtual grid while shrinking
				const currentAspectRatio = this.columns / this.rows
				// Compare the current aspect ratio to the grid aspectratio
				if (currentAspectRatio <= this.gridAspectRatio) {
					this.rows--
					// Ceck that there are still enough slots available
					slots = this.columns * this.rows
					if (videos > slots) {
						this.rows++
						return
					}
				} else {
					this.columns--
					// Ceck that there are still enough slots available
					slots = this.columns * this.rows
					if (videos > slots) {
						this.rows++
						return
					}
				}
				this.shrinkGrid(videos)
			}
		},

		handleClickNext() {
			const currentLastDisplayedElement = this.displayedVideos[this.displayedVideos.length - 1]
			const firstElementOfNextPage = this.videos.indexOf(currentLastDisplayedElement) + 1
			this.displayedVideos = this.videos.slice(firstElementOfNextPage, firstElementOfNextPage + this.rows * this.columns)

		},
		handleClickPrevious() {
			const currentFirstDisplayedElement = this.displayedVideos[0]
			const lastElementOfPreviousPage = this.videos.indexOf(currentFirstDisplayedElement)
			this.displayedVideos = this.videos.slice(lastElementOfPreviousPage - this.rows * this.columns, lastElementOfPreviousPage)

		},
	},
}

</script>

<style lang="scss" scoped>
.wrapper {
	height: 100%;
	width: 100%;
}

.grid {
	display: grid;
	height: 100%;
	width: 100%;
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

.grid-navigation {
	position: absolute;
	top: 50%;
	margin-top: -17px

}

.next {
	right: 40px;
}
.previous {
	left: 40px;
}

</style>
