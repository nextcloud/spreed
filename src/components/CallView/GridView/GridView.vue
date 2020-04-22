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
		<EmptyCallView v-if="videosCount <= 1" />
		<div
			v-else
			class="grid"
			:style="gridStyle">
			<template v-if="!devMode">
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
					:use-constrained-layout="false"
					@switchScreenToId="1" />
			</template>
			<!-- Grid developer mode -->
			<template v-else>
				<div
					v-for="video in displayedVideos"
					:key="video"
					class="dev-mode-video video"
					v-text="video" />
				<h1 class="dev-mode__title">
					Dev mode on ;-)
				</h1>
				<div class="dev-mode__data">
					<p>GRID INFO</p>
					<p>Videos (total): {{ videosCount }}</p>
					<p>Displayed videos: {{ displayedVideos.length }}</p>
					<p>Max per page: ~{{ videosCap }}</p>
					<p>Grid width: {{ gridWidth }}</p>
					<p>Grid height: {{ gridHeight }}</p>
					<p>Min video width: {{ minWidth }} </p>
					<p>Min video Height: {{ minHeight }} </p>
					<p>Grid aspect ratio: {{ gridAspectRatio }}</p>
					<p>Number of pages: {{ numberOfPages }}</p>
					<p>Current page: {{ currentPage }}</p>
				</div>
			</template>
		<!-- Grid pagination -->
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
		<div
			v-if="numberOfPages !== 0 && hasPagination"
			class="pages-indicator">
			<div v-for="(page, index) in numberOfPages"
				:key="index"
				class="pages-indicator__dot"
				:class="{'pages-indicator__dot--active': index === currentPage }" />
		</div>
	</div>
</template>

<script>
import debounce from 'debounce'
import call from '../../../mixins/call'
import Video from '../shared/Video'
import LocalVideo from '../shared/LocalVideo'
import { EventBus } from '../../../services/EventBus'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import EmptyCallView from '../shared/EmptyCallView'

export default {
	name: 'GridView',

	components: {
		Video,
		LocalVideo,
		EmptyCallView,
	},

	mixins: [call],

	props: {
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
			default: 150,
		},
		/**
		 * Max number of videos per page. `0`, the default value, means no cap
		 */
		videosCap: {
			type: Number,
			default: 0,
		},
		targetAspectRatio: {
			type: Number,
			default: 1.5,
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
		/**
		 * Display the overflow of videos in separate pages;
		 */
		hasPagination: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			gridWidth: 0,
			gridHeight: 0,
			// Array of videos that are being displayed in the grid at any
			// given moment
			displayedVideos: [],
			// Columns of the grid at any given moment
			columns: 0,
			// Rows of the grid at any given moment
			rows: 0,
			// Grid pages at any given moment
			numberOfPages: 0,
			// The current page
			currentPage: 0,
		}
	},

	computed: {
		// The videos array. This is the total number of grid elements.
		// Depending on `gridWidthm`, `gridHeight`, `minWidth`, `minHeight` and
		// `videosCap`, these videos are shown in one or more grid 'pages'.
		videos() {
			if (this.devMode) {
				return Array.from(Array(this.dummies).keys())
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
			return (this.gridWidth / this.gridHeight).toPrecision([2])
		},

		// Max number of columns possible
		columnsMax() {
			if (Math.floor(this.gridWidth / this.minWidth) < 1) {
				// Return at least 1 column
				return 1
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

		// Number of grid slots at any given moment
		slots() {
			return this.rows * this.columns
		},

		// Hides or displays the `grid-navigation next` button
		hasNextPage() {
			if (this.displayedVideos !== [] && this.hasPagination) {
				return this.displayedVideos[this.displayedVideos.length - 1] !== this.videos[this.videos.length - 1]
			} else {
				return false
			}
		},

		// Hides or displays the `grid-navigation previous` button
		hasPreviousPage() {
			if (this.displayedVideos !== [] && this.hasPagination) {
				return this.displayedVideos[0] !== this.videos[0]
			} else {
				return false
			}
		},

		isLastPage() {
			return !this.hasNextPage
		},

		// Computed css to reactively style the grid
		gridStyle() {
			return {
				gridTemplateColumns: `repeat(${this.columns}, minmax(${this.minWidth}px, 1fr))`,
				gridTemplateRows: `repeat(${this.rows}, minmax(${this.minHeight}px, 1fr))` }
		},

		// Check if there's an overflow of videos (videos that don't fit in the grid)
		hasVideoOverflow() {
			return this.videosCount > this.slots
		},

		sidebarStatus() {
			return this.$store.getters.getSidebarStatus()
		},

		mainView() {
			return document.getElementsByClassName('main-view')[0]
		},
	},

	watch: {
		// If the video array changes, rebuild the grid
		videos() {
			this.makeGrid()
			if (this.hasPagination) {
				this.setNumberOfPages()
				// Set the current page to 0
				// TODO: add support for keeping position in the videos array when resizing
				this.currentPage = 0
			}
		},
		// Exception for when navigating in and away from the last page of the
		// grid
		 isLastPage(newValue, oldValue) {
			 if (this.hasPagination) {
				 // If navigating into last page, make grid for last page
				if (newValue && this.currentPage !== 0) {
					this.makeGridForLastPage()
				} else if (!newValue) {
				// TODO: make a proper grid for when navigating away from last page
					this.makeGrid()
				}
			 }
		 },
		 sidebarStatus() {
			// Handle the resize after the sidebar animation has completed
			setTimeout(this.handleResize, 500)
		},
	},

	beforeMount() {
		// First build of the grid when mounting the component
		this.makeGrid()
		if (this.hasPagination) {
			this.setNumberOfPages()
			// Set the current page to 0
			// TODO: add support for keeping position in the videos array when resizing
			this.currentPage = 0
		}
	},

	// bind event handlers to the `handleResize` method
	mounted() {
		window.addEventListener('resize', this.handleResize)
		subscribe('navigation-toggled', this.handleResize)
		this.handleResize()

	},
	beforeDestroy() {
		window.removeEventListener('resize', this.handleResize)
		unsubscribe('navigation-toggled', this.handleResize)
	},

	methods: {

		// whenever the document is resized, re-set the 'clientWidth' variable
		handleResize(event) {
			this.gridWidth = this.mainView.clientWidth
			this.gridHeight = this.mainView.clientHeight
			// TODO: properly handle resizes when not on first page:
			// currently if the user is not on the 'first page', upon resize the
			// current position in the videos array is lost (first element
			// in the grid goes back to be first video)
			debounce(this.makeGrid(), 200)
			if (this.hasPagination) {
				this.setNumberOfPages()
				// Set the current page to 0
				// TODO: add support for keeping position in the videos array when resizing
				this.currentPage = 0
			}
		},

		// Find the right size if the grid in rows and columns (we already know
		// the size in px).
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
			// Send event to display hint in the topbar component if there's an
			// overflow of videos
			if (this.hasVideoOverflow) {
				EventBus.$emit('toggleLayoutHint', true)
			} else {
				// Remove the hint if user resizes
				EventBus.$emit('toggleLayoutHint', false)
			}
		},

		// Fine tune the number of rows and columns of the grid
		shrinkGrid(numberOfVideos) {
			// No need to shrink more if 1 row and 1 column
			if (this.rows === 1 && this.columns === 1) {
				return
			}
			// Run this code only if we don't have an 'overflow' of videos. If the
			// videos are populating the grid, there's no point in shrinking it.
			if (numberOfVideos < this.slots) {
				// Current video dimensions
				const videoWidth = this.gridWidth / this.columns
				const videoHeigth = this.gridHeight / this.rows
				// Hypotetical width with one column less than current
				const videoWidthWithOneColumnLess = this.gridWidth / (this.columns - 1)
				// Hypotetical height with one row less than current
				const videoHeightWithOneRowLess = this.gridHeight / (this.rows - 1)
				// Hypotetical aspect ratio with one column less than current
				const aspectRatioWithOneCoulumnLess = videoWidthWithOneColumnLess / videoHeigth
				// Hypotetical aspect ratio with one row less than current
				const aspectRatioWithOneRowLess = videoWidth / videoHeightWithOneRowLess
				// Deltas with target aspect ratio
				const deltaAspectRatioWithOneCoulumnLess = Math.abs(aspectRatioWithOneCoulumnLess - this.targetAspectRatio)
				const deltaAspectRatioWithOneRowLess = Math.abs(aspectRatioWithOneRowLess - this.targetAspectRatio)
				// Compare the deltas to find out whether we need to remove a column or a row
				if (deltaAspectRatioWithOneCoulumnLess <= deltaAspectRatioWithOneRowLess) {
					if (this.columns >= 2) {
						this.columns--
					}
					// Ceck that there are still enough slots available
					if (numberOfVideos > this.slots) {
						// If not, revert the changes and break the loop
						this.columns++
						return
					}
				} else {
					if (this.rows >= 2) {
						this.rows--
					}
					// Ceck that there are still enough slots available
					if (numberOfVideos > this.slots) {
					// If not, revert the changes and break the loop
						this.rows++
						return
					}
				}
				this.shrinkGrid(numberOfVideos)
			}
		},

		// Set the current number of pages
		setNumberOfPages() {
			this.numberOfPages = Math.ceil(this.videosCount / this.slots)
		},

		// The last grid page is very likely not to have the same number of
		// elements as the previous pages so the grid needs to be tweaked
		// accordingly
		makeGridForLastPage() {
			this.columns = this.columnsMax
			this.rows = this.rowsMax
			// The displayed videos for the last page have already been set
			// in `handleClickNext`
			this.shrinkGrid(this.displayedVideos.length)
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
			this.currentPage++
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
			this.currentPage--
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
	position:relative;
	height: 100%;
	width: 100%;
	overflow: hidden;
	display: flex;
	border: 1px solid white;
}

.dev-mode-video {
	border: 1px solid #00FF41;
	color: #00FF41;
	font-size: 30px;
	text-align: center;
	vertical-align: middle;
	padding-top: 80px;
}
.dev-mode__title {
	position: absolute;
	top: 44px;
	left: 44px;
	color: #00FF41;
	z-index: 100;
	font-size: 30px;
	line-height: 120px;
	font-weight: 900;
	font-size: 100px !important;
	top: 88px;
	opacity: 25%;
}

.dev-mode__data {
	font-family: monospace;
	position: absolute;
	color: #00FF41;
	left: 20px;
	bottom: 20px;
	padding: 20px;
	background: rgba(0,0,0,0.8);
	border: 1px solid #00FF41;
	width: 212px;
	font-size: 12px;
	& p {
		text-overflow: ellipsis;
		overflow: hidden;
		white-space: nowrap;
	}
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
	right: 20px;
}
.previous {
	left: 20px;
}

.pages-indicator {
	position: absolute;
	right: 50%;
	top: 4px;
	display: flex;
	background-color: var(--color-bakground-hover);
	height: 44px;
	padding: 0 22px;
	border-radius: 22px;
	&__dot {
		width: 8px;
		height: 8px;
		margin: auto 4px;
		border-radius: 4px;
		background-color: white;
		opacity: 80%;
		box-shadow: 0px 0px 4px black;
	&--active {
			opacity: 100%;
		}
	}
}

</style>
