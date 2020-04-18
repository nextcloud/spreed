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
		devMode: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
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

		gridStyle() {
			return { gridTemplateColumns: `repeat(${this.columns},  minmax(${this.minWidth}px, 1fr))`, gridTemplateRows: `repeat(${this.rows}, minmax(${this.minHeight}px, 1fr)))` }
		},
	},

	watch: {
		gridAspectRatio() {
		// If the aspect ratio changes, rebuild the grid
		// TODO: properly handle resizes when not on first page:
		// currently if the user is not on the 'first page', upon resize the
		// current position in the videos array is lost (first element
		// in the grid goes back to be first video)
			debounce(this.makeGrid(), 200)
		},
		videos() {
			this.makeGrid()
		},
	},

	beforeMount() {
		this.makeGrid()
	},

	methods: {
		makeGrid() {
			// Start by assigning the max possible value to rows and columns. This
			// would fit as many video components as possible given the parent's
			// dimensions.
			this.columns = this.columnsMax
			this.rows = this.rowsMax
			// However, if we have only a couple of videos to display and a very big
			// window, we now have a lot of columns and rows, and our video components
			// would be too small. To solve this, we shrink this 'virtual grid' we've
			// just created to fit the number of elements that we have.
			if (this.videosCap !== 0) {
				this.shrinkGrid(this.videosCap)
			} else {
				this.shrinkGrid(this.videosCount)
			}
			// Once the grid is done, populate it with video components
			if (this.devMode) {
				this.displayedVideos = this.videos.slice(0, this.rows * this.columns)
			} else {
				// Account for the localVideo component
				this.displayedVideos = this.videos.slice(0, this.rows * this.columns - 1)
			}
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
			if (this.devMode) {
				this.displayedVideos = this.videos.slice(firstElementOfNextPage, firstElementOfNextPage + this.rows * this.columns)
			} else {
				this.displayedVideos = this.videos.slice(firstElementOfNextPage, firstElementOfNextPage + this.rows * this.columns - 1)
			}
		},
		handleClickPrevious() {
			const currentFirstDisplayedElement = this.displayedVideos[0]
			const lastElementOfPreviousPage = this.videos.indexOf(currentFirstDisplayedElement)
			if (this.devMode) {
				this.displayedVideos = this.videos.slice(lastElementOfPreviousPage - this.rows * this.columns, lastElementOfPreviousPage)
			} else {
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
