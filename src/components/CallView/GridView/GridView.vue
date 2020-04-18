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
			:style="gridStyle">
			<template v-for="callParticipantModel in displayedVideos">
				<Video
					:key="callParticipantModel.attributes.peerId"
					class="video"
					:token="token"
					:model="callParticipantModel"
					:shared-data="{videoEnabled: true}" />
			</template>
			<LocalVideo ref="localVideo"
				class="video"
				:local-media-model="localMediaModel"
				:local-call-participant-model="localCallParticipantModel"
				:use-constrained-layout="useConstrainedLayout"
				@switchScreenToId="_switchScreenToId" />
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
import debounce from 'debounce'
import call from '../../../mixins/call'
import Video from '../shared/Video'
import LocalVideo from '../shared/LocalVideo'

export default {
	name: 'GridView',

	components: {
		Video,
		LocalVideo,
	},

	mixins: [call],

	props: {
		/**
		 * The width of the grid
		 */
		gridWidth: {
			type: Number,
			required: true,
		},
		/**
		 * The height of the grid
		 */
		gridHeight: {
			type: Number,
			required: true,
		},
		/**
		 * Minimum width of the video components
		 */
		minWidth: {
			type: Number,
			default: 200,
		},
		/**
		 * Minimum height of the video components
		 */
		minHeight: {
			type: Number,
			default: 200,
		},
		/**
		 * Max number of videos per page. `0`, the default value, means no cap
		 */
		videosCap: {
			type: Number,
			default: 0,
		},
		/**
		 * Developer mode: If enabled it allows to debug the grid using dummy
		 * videos
		 */
		devMode: {
			type: Boolean,
			default: false,
		},
		/**
		 * The number of dummy videos in dev mode
		 */
		dummies: {
			type: Number,
			default: 10,
		},
	},

	data() {
		return {
			// Array of videos that are being displayed in the grid at any
			// given moment
			displayedVideos: [],
			// Columns of the grid at any given moment
			columns: 0,
			// Rows of the grid at any given moment
			rows: 0,
		}
	},

	computed: {
		// The videos array. This is the total number of grid elements.
		// Depending on `gridWidthm`, `gridHeight`, `minWidth`, `minHeight` and
		// `videosCap`, these videos are shown in one or more grid 'pages'.
		videos() {
			if (this.devMode) {
				return Array.from(Array(40).keys())
			} else {
				return this.callParticipantModels
			}
		},

		// Number of video components (includes localvideo if not in dev mode)
		videosCount() {
			if (this.devMode) {
				return this.videos.length
			} else {
				// Add the local video to the count
				return this.videos.length + 1
			}
		},

		// The aspect ratio of the grid (in terms of px)
		gridAspectRatio() {
			return this.gridWidth / this.gridHeight
		},

		// Max number of columns possible
		columnsMax() {
			if (Math.floor(this.gridWidth / this.minWidth) < 1) {
				// Return at least 1 column
				return 1.2
			} else {
				return Math.floor(this.gridWidth / this.minWidth)
			}
		},

		// Max number of rows possible
		rowsMax() {
			if (Math.floor(this.gridHeight / this.minHeight) < 1) {
				// Return at least 1 row
				return 1
			} else {
				return Math.floor(this.gridHeight / this.minHeight)
			}
		},

		// Hides or displays the `grid-navigation next` button
		hasNextPage() {
			if (this.displayedVideos !== []) {
				return this.displayedVideos[this.displayedVideos.length - 1]
				!== this.videos[this.videos.length - 1]
			} else {
				return false
			}
		},

		// Hides or displays the `grid-navigation previous` button
		hasPreviousPage() {
			if (this.displayedVideos !== []) {
				return this.displayedVideos[0] !== this.videos[0]
			} else {
				return false
			}
		},

		// Computed css to reactively style the grid
		gridStyle() {
			return {
				gridTemplateColumns: `repeat(${this.columns}, minmax(${this.minWidth}px, 1fr))`,
				gridTemplateRows: `repeat(${this.rows}, minmax(${this.minHeight}px, 1fr)))` }
		},
	},

	watch: {
		// If the aspect ratio changes, rebuild the grid
		gridAspectRatio() {
		// TODO: properly handle resizes when not on first page:
		// currently if the user is not on the 'first page', upon resize the
		// current position in the videos array is lost (first element
		// in the grid goes back to be first video)
			debounce(this.makeGrid(), 200)
		},
		// If the video array changes, rebuild the grid
		videos() {
			this.makeGrid()
		},
	},

	beforeMount() {
		// First build of the grid when mounting the component
		this.makeGrid()
	},

	methods: {
		/**
		 * Find the right size if the grid in rows and columns (we already know
		 * the size in px).
		 */
		makeGrid() {
			// We start by assigning the max possible value to our rows and columns
			// variables. These variables are kept in the data and represent how the
			// grid looks at any given moment. We do this based on `gridWidthm`,
			// `gridHeight`, `minWidth` and `minHeight`
			this.columns = this.columnsMax
			this.rows = this.rowsMax
			// This values would already work if the grid is entirely populated with
			// video elements. However, if we'd have only a couple of videos to display
			// and a very big screen, we'd now have a lot of columns and rows, and our
			// video components would occupy only the first 2 slots and be too small.
			// To solve this, we shrink this 'max grid' we've just created to fit the
			// number of videos that we have.
			if (this.videosCap !== 0) {
				this.shrinkGrid(this.videosCap)
			} else {
				this.shrinkGrid(this.videosCount)
			}
			// Once the grid is done, populate it with video components
			if (this.devMode) {
				this.displayedVideos = this.videos.slice(0, this.rows * this.columns)
			} else {
				// `- 1` because we a ccount for the localVideo component (see template)
				this.displayedVideos = this.videos.slice(0, this.rows * this.columns - 1)
			}
		},

		// Fine tune the number of rows and columns of the grid
		shrinkGrid(videos) {
			// Get the max available grid slots
			let slots = this.columns * this.rows
			// Run this code only if we don't have an 'overflow' of videos. If the
			// videos are populating the grid, there's no point in shrinking it.
			if (videos < slots) {
				// Get the aspect ratio (in terms of coulmns and rows)of the current grid
				// while shrinking
				const currentAspectRatio = this.columns / this.rows
				// At each iteration of this recursive method, we want to compare the
				// current aspect ratio of the grid (in terms of rows and columns) to
				// the aspect ratio of the grid (in terms of px).
				if (currentAspectRatio <= this.gridAspectRatio) {
					this.rows--
					// Ceck that there are still enough slots available
					slots = this.columns * this.rows
					if (videos > slots) {
						// If not, revert the changes and break the loop
						this.rows++
						return
					}
				} else {
					this.columns--
					// Ceck that there are still enough slots available
					slots = this.columns * this.rows
					if (videos > slots) {
						// If not, revert the changes and break the loop
						this.rows++
						return
					}
				}
				this.shrinkGrid(videos)
			}
		},

		// Slice the `videos` array to display the next set of videos
		handleClickNext() {
			const currentLastDisplayedElement = this.displayedVideos[this.displayedVideos.length - 1]
			const firstElementOfNextPage = this.videos.indexOf(currentLastDisplayedElement) + 1
			if (this.devMode) {
				this.displayedVideos = this.videos.slice(firstElementOfNextPage, firstElementOfNextPage + this.rows * this.columns)
			} else {
				// `- 1` because we a ccount for the localVideo component (see template)
				this.displayedVideos = this.videos.slice(firstElementOfNextPage, firstElementOfNextPage + this.rows * this.columns - 1)
			}
		},
		// Slice the `videos` array to display the previous set of videos
		handleClickPrevious() {
			const currentFirstDisplayedElement = this.displayedVideos[0]
			const lastElementOfPreviousPage = this.videos.indexOf(currentFirstDisplayedElement)
			if (this.devMode) {
				this.displayedVideos = this.videos.slice(lastElementOfPreviousPage - this.rows * this.columns, lastElementOfPreviousPage)
			} else {
				// `- 1` because we a ccount for the localVideo component (see template)
				this.displayedVideos = this.videos.slice(lastElementOfPreviousPage - this.rows * this.columns, lastElementOfPreviousPage - 1)
			}
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
	border: 2x solid white;
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
