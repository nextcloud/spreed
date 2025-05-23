<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div ref="gridWrapper" class="grid-main-wrapper" :class="{ 'is-grid': !isStripe, 'transparent': isLessThanTwoVideos }">
		<NcButton v-if="isStripe && !isRecording"
			class="stripe--collapse"
			type="tertiary-no-background"
			:title="stripeButtonTitle"
			:aria-label="stripeButtonTitle"
			@click="handleClickStripeCollapse">
			<template #icon>
				<IconChevronDown v-if="stripeOpen"
					fill-color="#ffffff"
					:size="20" />
				<IconChevronUp v-else
					fill-color="#ffffff"
					:size="20" />
			</template>
		</NcButton>
		<TransitionWrapper :name="isStripe ? 'slide-down' : undefined">
			<div v-if="!isStripe || stripeOpen" class="wrapper" :style="wrapperStyle">
				<div :class="[isStripe ? 'stripe-wrapper' : 'grid-wrapper']">
					<NcButton v-if="hasPreviousPage && gridWidth > 0"
						type="tertiary-no-background"
						class="grid-navigation grid-navigation__previous"
						:aria-label="t('spreed', 'Previous page of videos')"
						@click="handleClickPrevious">
						<template #icon>
							<IconChevronLeft class="bidirectional-icon"
								fill-color="#ffffff"
								:size="20" />
						</template>
					</NcButton>
					<div ref="grid"
						class="grid"
						:class="{ stripe: isStripe }"
						:style="gridStyle"
						@mousemove="handleMovement"
						@wheel="debounceHandleWheelEvent"
						@keydown="handleMovement">
						<template v-if="!devMode && (!isLessThanTwoVideos || !isStripe)">
							<EmptyCallView v-if="videos.length === 0 && !isStripe" class="video" :is-grid="true" />
							<template v-for="callParticipantModel in displayedVideos">
								<VideoVue :key="callParticipantModel.attributes.peerId"
									:class="{ 'video': !isStripe }"
									:show-video-overlay="showVideoOverlay"
									:token="token"
									:model="callParticipantModel"
									:is-grid="true"
									:show-talking-highlight="!isStripe"
									:is-stripe="isStripe"
									:is-promoted="sharedDatas[callParticipantModel.attributes.peerId].promoted"
									:is-selected="isSelected(callParticipantModel)"
									:shared-data="sharedDatas[callParticipantModel.attributes.peerId]"
									@click-video="handleClickVideo($event, callParticipantModel.attributes.peerId)" />
							</template>
						</template>
						<!-- Grid developer mode -->
						<template v-if="devMode">
							<div v-for="key in displayedVideos"
								:key="key"
								class="dev-mode-video video"
								:class="{ 'dev-mode-screenshot': screenshotMode }">
								<img :alt="placeholderName(key)" :src="placeholderImage(key)">
								<VideoBottomBar :has-shadow="false"
									:model="placeholderModel(key)"
									:shared-data="placeholderSharedData(key)"
									:token="token"
									:participant-name="placeholderName(key, !screenshotMode)" />
							</div>
							<h1 v-if="!screenshotMode" class="dev-mode__title">
								Dev mode on ;-)
							</h1>
						</template>
						<LocalVideo v-if="!isStripe && !isRecording"
							ref="localVideo"
							class="video"
							is-grid
							:fit-video="false"
							:token="token"
							:local-media-model="localMediaModel"
							:local-call-participant-model="localCallParticipantModel"
							@click-video="handleClickLocalVideo" />
					</div>
					<NcButton v-if="hasNextPage && gridWidth > 0"
						type="tertiary-no-background"
						class="grid-navigation grid-navigation__next"
						:aria-label="t('spreed', 'Next page of videos')"
						@click="handleClickNext">
						<template #icon>
							<IconChevronRight class="bidirectional-icon"
								fill-color="#ffffff"
								:size="20" />
						</template>
					</NcButton>
				</div>
				<LocalVideo v-if="isStripe && !isRecording"
					ref="localVideo"
					class="video"
					:is-stripe="true"
					:show-controls="false"
					:token="token"
					:local-media-model="localMediaModel"
					:local-call-participant-model="localCallParticipantModel"
					@click-video="handleClickLocalVideo" />

				<template v-if="devMode">
					<NcButton type="tertiary"
						class="dev-mode__toggle"
						aria-label="Toggle screenshot mode"
						@click="screenshotMode = !screenshotMode">
						<template #icon>
							<IconChevronLeft v-if="!screenshotMode"
								class="bidirectional-icon"
								fill-color="#00FF41"
								:size="20" />
						</template>
					</NcButton>
					<div v-if="!screenshotMode" class="dev-mode__data">
						<span>GRID INFO</span>
						<button @click="disableDevMode">
							Disable
						</button>
						<span>Debug info</span>
						<button @click="gridDebugInformation">
							Log
						</button>
						<span>Videos (total):</span><span>{{ videosCount }}</span>
						<span>Displayed videos:</span><span>{{ displayedVideos.length }}</span>
						<span>Max per page:</span><span>~{{ videosCap }}</span>
						<span>Grid width:</span><span>{{ gridWidth }}px</span>
						<span>Grid height:</span><span>{{ gridHeight }}px</span>
						<span>Min video width:</span><span>{{ minWidth }}px</span>
						<span>Min video Height:</span><span>{{ minHeight }}px</span>
						<span>Grid aspect ratio:</span><span>{{ gridAspectRatio }}</span>
						<span>Number of pages:</span><span>{{ numberOfPages }}</span>
						<span>Current page:</span><span>{{ currentPage }}</span>
						<span>Dummies:</span><input v-model.number="dummies" type="number">
						<span>Stripe mode:</span><input v-model="devStripe" type="checkbox">
						<span>Screenshot mode:</span><input v-model="screenshotMode" type="checkbox">
					</div>
				</template>
			</div>
		</TransitionWrapper>
	</div>
</template>

<script>
import debounce from 'debounce'
import { inject, ref } from 'vue'

import IconChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import IconChevronLeft from 'vue-material-design-icons/ChevronLeft.vue'
import IconChevronRight from 'vue-material-design-icons/ChevronRight.vue'
import IconChevronUp from 'vue-material-design-icons/ChevronUp.vue'

import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'

import TransitionWrapper from '../../UIShared/TransitionWrapper.vue'
import EmptyCallView from '../shared/EmptyCallView.vue'
import LocalVideo from '../shared/LocalVideo.vue'
import VideoBottomBar from '../shared/VideoBottomBar.vue'
import VideoVue from '../shared/VideoVue.vue'

import { placeholderImage, placeholderModel, placeholderName, placeholderSharedData } from './gridPlaceholders.ts'
import { PARTICIPANT, ATTENDEE } from '../../../constants.ts'
import { useCallViewStore } from '../../../stores/callView.ts'

// Max number of videos per page. `0`, the default value, means no cap
const videosCap = parseInt(loadState('spreed', 'grid_videos_limit'), 10) || 0
const videosCapEnforced = loadState('spreed', 'grid_videos_limit_enforced') || false

// Align with var(--grid-gap) in CallView
const GRID_GAP = 8

export default {
	name: 'Grid',

	components: {
		VideoVue,
		LocalVideo,
		EmptyCallView,
		NcButton,
		TransitionWrapper,
		VideoBottomBar,
		IconChevronDown,
		IconChevronLeft,
		IconChevronRight,
		IconChevronUp,
	},

	props: {
		/**
		 * Display the overflow of videos in separate pages;
		 */
		hasPagination: {
			type: Boolean,
			default: false,
		},
		/**
		 * To be set to true when the grid is in the promoted view.
		 */
		isStripe: {
			type: Boolean,
			default: false,
		},
		isSidebar: {
			type: Boolean,
			default: false,
		},
		isRecording: {
			type: Boolean,
			default: false,
		},
		callParticipantModels: {
			type: Array,
			required: true,
		},
		localMediaModel: {
			type: Object,
			required: true,
		},
		localCallParticipantModel: {
			type: Object,
			required: true,
		},
		token: {
			type: String,
			required: true,
		},
		sharedDatas: {
			type: Object,
			required: true,
		},
		isLocalVideoSelectable: {
			type: Boolean,
			default: false,
		},
		screens: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['select-video', 'click-local-video'],

	setup() {
		// Developer mode: If enabled it allows to debug the grid using dummy videos
		const devMode = inject('CallView:devModeEnabled', ref(false))
		const screenshotMode = inject('CallView:screenshotModeEnabled', ref(false))
		// The number of dummy videos in dev mode
		const dummies = ref(4)

		return {
			devMode,
			dummies,
			screenshotMode,
			videosCap,
			videosCapEnforced,
			callViewStore: useCallViewStore(),
		}
	},

	data() {
		return {
			gridWidth: 0,
			gridHeight: 0,
			// Columns of the grid at any given moment
			columns: 0,
			// Rows of the grid at any given moment
			rows: 0,
			// The current page
			currentPage: 0,
			// Videos controls and name
			showVideoOverlay: true,
			// Timer for the videos bottom bar
			showVideoOverlayTimer: null,
			resizeObserver: null,
			debounceMakeGrid: () => {},
			debounceHandleWheelEvent: () => {},
			tempPromotedModels: [],
			unpromoteSpeakerTimer: {},
			promotedHistoryMask: [],
		}
	},

	computed: {
		stripeButtonTitle() {
			if (this.stripeOpen) {
				return t('spreed', 'Collapse stripe')
			} else {
				return t('spreed', 'Expand stripe')
			}
		},

		// The videos array. This is the total number of grid elements.
		// Depending on `gridWidth`, `gridHeight`, `minWidth`, `minHeight` and
		// `videosCap`, these videos are shown in one or more grid 'pages'.
		videos() {
			if (this.devMode) {
				return Array.from(Array(this.dummies).keys())
			} else {
				return this.callParticipantModels
			}
		},

		// Number of video components (it does not include the local video)
		videosCount() {
			if (!this.isStripe && this.videos.length === 0) {
				// Count the emptycontent as a grid element
				return 1
			}

			return this.videos.length
		},
		videoWidth() {
			return (this.gridWidth - GRID_GAP * (this.columns - 1)) / this.columns
		},
		videoHeight() {
			return (this.gridHeight - GRID_GAP * (this.rows - 1)) / this.rows
		},

		// Array of videos that are being displayed in the grid at any given
		// moment
		displayedVideos() {
			if (!this.slots) {
				return []
			}

			const slots = (this.videosCap && this.videosCapEnforced) ? Math.min(this.videosCap, this.slots) : this.slots

			// Slice the `videos` array to display the current page of videos
			if (((this.currentPage + 1) * slots) >= this.orderedVideos.length) {
				return this.orderedVideos.slice(this.currentPage * slots)
			}

			return this.orderedVideos.slice(this.currentPage * slots, (this.currentPage + 1) * slots)
		},

		isLessThanTwoVideos() {
			// without screen share, we don't want to duplicate videos if we were to show them in the stripe
			// however, if a screen share is in progress, it means the video of the presenting user is not visible,
			// so we can show it in the stripe
			return this.videos.length <= 1 && !this.screens.length
		},

		dpiFactor() {
			if (this.isStripe) {
				// On the stripe we only ever want 1 row, so we ignore the DPR
				// as the height of the grid is the height of the video elements then.
				return 1.0
			}

			const devicePixelRatio = window.devicePixelRatio

			// Some sanity check to not screw up the math.
			if (devicePixelRatio < 0.5) {
				return 0.5
			}

			if (devicePixelRatio > 2.0) {
				return 2.0
			}

			return devicePixelRatio
		},

		/**
		 * Minimum width of the video components
		 */
		minWidth() {
			return (this.isStripe || this.isSidebar) ? 200 : 320
		},
		/**
		 * Minimum height of the video components
		 */
		minHeight() {
			return (this.isStripe || this.isSidebar) ? 150 : 240
		},

		dpiAwareMinWidth() {
			return this.minWidth / this.dpiFactor
		},

		dpiAwareMinHeight() {
			return this.minHeight / this.dpiFactor
		},

		// The aspect ratio of the grid (in terms of px)
		gridAspectRatio() {
			return (this.gridWidth / this.gridHeight).toPrecision([2])
		},

		targetAspectRatio() {
			return this.isStripe ? 1 : 1.5
		},

		// Max number of columns possible
		columnsMax() {
			// Max amount of columns that fits on screen, including gaps (--grid-gap, 8px)
			const calculatedApproxColumnsMax = Math.floor((this.gridWidth - GRID_GAP * (this.columns - 1)) / this.dpiAwareMinWidth)
			// Max amount of columns that fits on screen (with one more gap, as if we try to fit one more column)
			const calculatedHypotheticalColumnsMax = Math.floor((this.gridWidth - GRID_GAP * this.columns) / this.dpiAwareMinWidth)
			// If we about to change current columns amount, check if one more column could fit the screen
			// This helps to avoid flickering, when resize within 8px from minimal gridWidth for current amount of columns
			const calculatedColumnsMax = calculatedApproxColumnsMax === this.columns ? calculatedApproxColumnsMax : calculatedHypotheticalColumnsMax
			// Return at least 1 column
			return calculatedColumnsMax <= 1 ? 1 : calculatedColumnsMax
		},

		// Max number of rows possible
		rowsMax() {
			if (Math.floor((this.gridHeight - GRID_GAP * (this.rows - 1)) / this.dpiAwareMinHeight) < 1) {
				// Return at least 1 row
				return 1
			} else {
				return Math.floor((this.gridHeight - GRID_GAP * (this.rows - 1)) / this.dpiAwareMinHeight)
			}
		},

		// Number of grid slots at any given moment
		// The local video always takes one slot if the grid view is not shown
		// as a stripe.
		slots() {
			return this.isStripe ? this.rows * this.columns : this.rows * this.columns - 1
		},

		// Grid pages at any given moment
		numberOfPages() {
			return Math.ceil(this.videosCount / this.slots)
		},

		// Hides or displays the `grid-navigation next` button
		hasNextPage() {
			if (this.displayedVideos.length !== 0 && this.hasPagination) {
				return this.displayedVideos.at(-1) !== this.orderedVideos.at(-1)
			} else {
				return false
			}
		},

		// Hides or displays the `grid-navigation previous` button
		hasPreviousPage() {
			if (this.displayedVideos.length !== 0 && this.hasPagination) {
				return this.displayedVideos[0] !== this.orderedVideos[0]
			} else {
				return false
			}
		},

		// Computed css to reactively style the grid
		gridStyle() {
			let columns = this.columns
			let rows = this.rows

			// If there are no other videos the empty call view is shown above
			// the local video.
			if (this.videos.length === 0 && !this.isStripe) {
				columns = 1
				rows = 2
			}

			return {
				gridTemplateColumns: `repeat(${columns}, minmax(${this.dpiAwareMinWidth}px, 1fr))`,
				gridTemplateRows: `repeat(${rows}, minmax(${this.dpiAwareMinHeight}px, 1fr))`,
			}
		},

		// Check if there's an overflow of videos (videos that don't fit in the grid)
		hasVideoOverflow() {
			return this.videosCount > this.slots
		},

		wrapperStyle() {
			if (this.isStripe) {
				return 'height: 250px'
			} else {
				return 'height: 100%'
			}
		},

		stripeOpen() {
			return this.callViewStore.isStripeOpen && !this.isRecording
		},

		participantsInitialised() {
			return this.$store.getters.participantsInitialised(this.token)
		},

		isGuestNonModerator() {
			return this.$store.getters.getActorType() === ATTENDEE.ACTOR_TYPE.GUESTS
				&& this.$store.getters.conversation(this.token).participantType !== PARTICIPANT.TYPE.GUEST_MODERATOR
		},

		orderedVideos() {
			// Dynamic ordering is not possible for guests because
			// participants store is not initialized
			if (this.isGuestNonModerator || this.devMode) {
				return this.videos
			}

			if (!this.participantsInitialised) {
				return []
			}

			const objectMap = {
				modelsWithScreenshare: [],
				modelsTempPromoted: [],
				modelsWithVideoEnabled: [],
				modelsWithAudioOnly: [],
				modelsWithNoPermissions: [],
			}
			const screensSet = new Set(this.screens)
			const tempPromotedModelsSet = new Set(this.tempPromotedModels.map(model => model.attributes.nextcloudSessionId))
			const videoTilesMap = new Map()
			const audioTilesMap = new Map()

			this.callParticipantModels.forEach((model) => {
				if (screensSet.has(model.attributes.peerId)) {
					objectMap.modelsWithScreenshare.push(model)
				} else if (tempPromotedModelsSet.has(model.attributes.nextcloudSessionId)) {
					objectMap.modelsTempPromoted.push(model)
				} else if (this.isModelWithVideo(model)) {
					videoTilesMap.set(model.attributes.nextcloudSessionId, model)
				} else if (this.isModelWithAudio(model)) {
					audioTilesMap.set(model.attributes.nextcloudSessionId, model)
				} else {
					objectMap.modelsWithNoPermissions.push(model)
				}
			})

			objectMap.modelsWithVideoEnabled = this.getOrderedTiles(videoTilesMap, this.promotedHistoryMask)
			objectMap.modelsWithAudioOnly = this.getOrderedTiles(audioTilesMap, this.promotedHistoryMask)

			return [...objectMap.modelsWithScreenshare,
				...objectMap.modelsTempPromoted,
				...objectMap.modelsWithVideoEnabled,
				...objectMap.modelsWithAudioOnly,
				...objectMap.modelsWithNoPermissions]
		},

		speakers() {
			return this.callParticipantModels.filter(model => model.attributes.speaking)
		},

		speakersWithAudioOff() {
			return this.tempPromotedModels.filter(model => !model.attributes.audioAvailable)
		},

		devStripe: {
			get() {
				return this.isStripe
			},
			set(value) {
				this.callViewStore.setCallViewMode({ token: this.token, isGrid: !value, clearLast: false })
			},
		},
	},

	watch: {
		// If the video array size changes, rebuild the grid
		'videos.length'() {
			this.makeGrid()
		},

		isStripe() {
			this.rebuildGrid()

			// Reset current page when switching between stripe and full grid,
			// as the previous page is meaningless in the new mode.
			this.currentPage = 0
		},

		stripeOpen() {
			this.rebuildGrid()
		},

		numberOfPages() {
			if (this.currentPage >= this.numberOfPages) {
				this.currentPage = Math.max(0, this.numberOfPages - 1)
			}
		},

		speakers(models) {
			models.forEach(model => {
				this.promoteSpeaker(model)
				clearTimeout(this.unpromoteSpeakerTimer[model.attributes.nextcloudSessionId])
			})
		},

		speakersWithAudioOff(newModels, oldModels) {
			newModels.forEach(speaker => {
				if (oldModels.includes(speaker)) {
					return
				}
				this.unpromoteSpeakerTimer[speaker.attributes.nextcloudSessionId] = setTimeout(() => {
					this.unpromoteSpeaker(speaker)
				}, 10000)
			})
		},
	},

	mounted() {
		this.debounceMakeGrid = debounce(this.makeGrid, 200)
		this.debounceHandleWheelEvent = debounce(this.handleWheelEvent, 50)
		this.resizeObserver = new ResizeObserver(this.debounceMakeGrid)
		this.resizeObserver.observe(this.$refs.gridWrapper)
		this.makeGrid()

		if (OC.debug) {
			OCA.Talk.gridDebugInformation = this.gridDebugInformation
			OCA.Talk.gridDevModeEnable = this.enableDevMode
		}
	},

	beforeDestroy() {
		this.debounceMakeGrid.clear?.()
		this.debounceHandleWheelEvent.clear?.()

		if (this.resizeObserver) {
			this.resizeObserver.disconnect()
		}

		if (OC.debug) {
			OCA.Talk.gridDebugInformation = undefined
			OCA.Talk.gridDevModeEnable = undefined
		}
	},

	methods: {
		t,
		gridDebugInformation() {
			console.info('Grid debug information', {
				minWidth: this.minWidth,
				minHeight: this.minHeight,
				videosCap: this.videosCap,
				videosCapEnforced: this.videosCapEnforced,
				targetAspectRatio: this.targetAspectRatio,
				videosCount: this.videosCount,
				videoWidth: this.videoWidth,
				videoHeight: this.videoHeight,
				devicePixelRatio: window.devicePixelRatio,
				dpiFactor: this.dpiFactor,
				dpiAwareMinWidth: this.dpiAwareMinWidth,
				dpiAwareMinHeight: this.dpiAwareMinHeight,
				gridAspectRatio: this.gridAspectRatio,
				columnsMax: this.columnsMax,
				rowsMax: this.rowsMax,
				numberOfPages: this.numberOfPages,
				bodyWidth: document.body.clientWidth,
				bodyHeight: document.body.clientHeight,
				gridWidth: this.$refs.grid.clientWidth,
				gridHeight: this.$refs.grid.clientHeight,
			})
		},

		rebuildGrid() {
			console.debug('isStripe: ', this.isStripe)
			console.debug('stripeOpen: ', this.stripeOpen)
			console.debug('previousGridWidth: ', this.gridWidth, 'previousGridHeight: ', this.gridHeight)
			console.debug('newGridWidth: ', this.gridWidth, 'newGridHeight: ', this.gridHeight)
			if (!this.isStripe || this.stripeOpen) {
				this.$nextTick(this.makeGrid)
			}
		},

		// Placeholder data for devMode and screenshotMode
		placeholderImage,
		placeholderName,
		placeholderModel,
		placeholderSharedData,

		enableDevMode() {
			this.screenshotMode = false
			this.devMode = true
		},

		disableDevMode() {
			this.screenshotMode = false
			this.devMode = false
		},

		// Find the right size if the grid in rows and columns (we already know the size in px).
		makeGrid() {
			// TODO: properly handle resizes when not on first page:
			// currently if the user is not on the 'first page', upon resize the
			// current position in the videos array is lost (first element
			// in the grid goes back to be first video)
			// TODO: rebuild the grid to have optimal for last page:
			// Exception for when navigating in and away from the last page of the grid
			// The last grid page is very likely not to have the same number of elements
			// as the previous pages so the grid needs to be tweaked accordingly
			if (!this.$refs.grid) {
				return
			}
			this.gridWidth = this.$refs.grid.clientWidth
			this.gridHeight = this.$refs.grid.clientHeight
			// prevent making grid if no videos
			if (this.videos.length === 0) {
				this.columns = 0
				this.rows = 0
				return
			}

			if (this.devMode) {
				console.debug('Recreating grid: videos: ', this.videos.length, 'columns: ', this.columnsMax + ', rows: ' + this.rowsMax)
			}

			// We start by assigning the max possible value to our rows and columns
			// variables. These variables are kept in the data and represent how the
			// grid looks at any given moment. We do this based on `gridWidth`,
			// `gridHeight`, `minWidth` and `minHeight`. If the video is used in the
			// context of the promoted view, we se 1 row directly, and we remove 1 column
			// (one of the participants will be in the promoted video slot)
			this.columns = this.columnsMax
			this.rows = this.rowsMax
			// This values would already work if the grid is entirely populated with
			// video elements. However, if we'd have only a couple of videos to display
			// and a very big screen, we'd now have a lot of columns and rows, and our
			// video components would occupy only the first 2 slots and be too small.
			// To solve this, we shrink this 'max grid' we've just created to fit the
			// number of videos that we have.
			if (this.videosCap !== 0 && this.videosCount > this.videosCap) {
				this.shrinkGrid(this.videosCap)
			} else {
				this.shrinkGrid(this.videosCount)
			}
		},

		// Fine tune the number of rows and columns of the grid
		async shrinkGrid(numberOfVideos) {
			if (this.devMode) {
				console.debug('Shrinking grid: columns', this.columns + ', rows: ' + this.rows)
			}

			// No need to shrink more if 1 row and 1 column
			if (this.rows === 1 && this.columns === 1) {
				return
			}

			let currentColumns = this.columns
			let currentRows = this.rows
			let currentSlots = this.isStripe ? currentColumns * currentRows : currentColumns * currentRows - 1

			// Run this code only if we don't have an 'overflow' of videos. If the
			// videos are populating the grid, there's no point in shrinking it.
			while (numberOfVideos < currentSlots) {
				const previousColumns = currentColumns
				const previousRows = currentRows

				// Current video dimensions
				const videoWidth = (this.gridWidth - GRID_GAP * (currentColumns - 1)) / currentColumns
				const videoHeight = (this.gridHeight - GRID_GAP * (currentRows - 1)) / currentRows

				// Hypothetical width/height with one column/row less than current
				const videoWidthWithOneColumnLess = (this.gridWidth - GRID_GAP * (currentColumns - 2)) / (currentColumns - 1)
				const videoHeightWithOneRowLess = (this.gridHeight - GRID_GAP * (currentRows - 2)) / (currentRows - 1)

				// Hypothetical aspect ratio with one column/row less than current
				const aspectRatioWithOneColumnLess = videoWidthWithOneColumnLess / videoHeight
				const aspectRatioWithOneRowLess = videoWidth / videoHeightWithOneRowLess

				// Deltas with target aspect ratio
				const deltaAspectRatioWithOneColumnLess = Math.abs(aspectRatioWithOneColumnLess - this.targetAspectRatio)
				const deltaAspectRatioWithOneRowLess = Math.abs(aspectRatioWithOneRowLess - this.targetAspectRatio)

				if (this.devMode) {
					console.debug('deltaAspectRatioWithOneColumnLess: ', deltaAspectRatioWithOneColumnLess, 'deltaAspectRatioWithOneRowLess: ', deltaAspectRatioWithOneRowLess)
				}
				// Compare the deltas to find out whether we need to remove a column or a row
				if (deltaAspectRatioWithOneColumnLess <= deltaAspectRatioWithOneRowLess) {
					if (currentColumns >= 2) {
						currentColumns--
					}

					currentSlots = this.isStripe ? currentColumns * currentRows : currentColumns * currentRows - 1

					// Check that there are still enough slots available
					if (numberOfVideos > currentSlots) {
						// If not, revert the changes and break the loop
						currentColumns++
						break
					}
				} else {
					if (currentRows >= 2) {
						currentRows--
					}

					currentSlots = this.isStripe ? currentColumns * currentRows : currentColumns * currentRows - 1

					// Check that there are still enough slots available
					if (numberOfVideos > currentSlots) {
						// If not, revert the changes and break the loop
						currentRows++
						break
					}
				}

				if (previousColumns === currentColumns && previousRows === currentRows) {
					break
				}
			}

			this.columns = currentColumns
			this.rows = currentRows
		},

		handleWheelEvent(event) {
			if (this.gridWidth <= 0) {
				return
			}

			if (event.deltaY < 0 && this.hasPreviousPage) {
				this.handleClickPrevious()
			} else if (event.deltaY > 0 && this.hasNextPage) {
				this.handleClickNext()
			}
		},

		handleClickNext() {
			this.currentPage++
			console.debug('handleclicknext, ', 'currentPage ', this.currentPage, 'slots ', this.slot, 'videos.length ', this.videos.length)
		},
		handleClickPrevious() {
			this.currentPage--
			console.debug('handleclickprevious, ', 'currentPage ', this.currentPage, 'slots ', this.slots, 'videos.length ', this.videos.length)
		},

		handleClickStripeCollapse() {
			this.callViewStore.setCallViewMode({ token: this.token, isStripeOpen: !this.stripeOpen, clearLast: false })
		},

		handleMovement() {
			// TODO: debounce this
			this.setTimerForUiControls()
		},
		setTimerForUiControls() {
			if (this.showVideoOverlayTimer !== null) {
				clearTimeout(this.showVideoOverlayTimer)
			}
			this.showVideoOverlay = true
			this.showVideoOverlayTimer = setTimeout(() => {
				this.showVideoOverlay = false
			}, 5000)
		},

		handleClickVideo(event, peerId) {
			console.debug('selected-video peer id', peerId)
			this.$emit('select-video', peerId)
		},

		handleClickLocalVideo() {
			this.$emit('click-local-video')
		},

		isSelected(callParticipantModel) {
			return callParticipantModel.attributes.peerId === this.callViewStore.selectedVideoPeerId
		},

		isModelWithVideo(callParticipantModel) {
			return callParticipantModel.attributes.videoAvailable
				&& (typeof callParticipantModel.attributes.stream === 'object')
		},

		isModelWithAudio(callParticipantModel) {
			const participant = this.$store.getters.getParticipantBySessionId(this.token, callParticipantModel.attributes.nextcloudSessionId)
			if (!participant) {
				return false
			}
			return participant?.permissions & PARTICIPANT.PERMISSIONS.PUBLISH_AUDIO
		},

		unpromoteSpeaker(model) {
			// remove model from the temp promoted speakers
			const index = this.tempPromotedModels.indexOf(model)
			if (index === -1) {
				return
			}

			this.tempPromotedModels.splice(index, 1)
		},

		promoteSpeaker(model) {
			const id = model.attributes.nextcloudSessionId

			// if model is already in the first page, do nothing
			if (this.orderedVideos.slice(0, this.slots).find(video => video.attributes.nextcloudSessionId === id)) {
				return
			}

			if (this.screens.includes(model.attributes.peerId)) {
				// tiles with screenshare have a better priority position already
				// do nothing
				return
			}

			// add the model
			if (!this.tempPromotedModels.includes(model)) {
				// remove model from the order history if it exists
				const modelIndex = this.promotedHistoryMask.indexOf(id)
				if (modelIndex !== -1) {
					this.promotedHistoryMask.splice(modelIndex, 1)
				}

				this.tempPromotedModels.unshift(model)
				// add model to the beginning of the orderedVideos in its category
				this.promotedHistoryMask.unshift(id)
			}
		},

		getOrderedTiles(tilesMap, orderMask) {
			const orderedTiles = []
			const rest = []
			// Get the ordered tiles
			orderMask.forEach(id => {
				if (tilesMap.has(id)) {
					orderedTiles.push(tilesMap.get(id))
				}
			})

			// Add remaining tiles not in orderMask to rest
			tilesMap.forEach((tile, id) => {
				if (!orderMask.includes(id)) {
					rest.push(tile)
				}
			})

			return [...orderedTiles, ...rest]
		},
	},
}

</script>

<style lang="scss" scoped>
.grid-main-wrapper {
	--navigation-position: calc(var(--default-grid-baseline) * 2);
	position: relative;
	width: 100%;
}

.grid-main-wrapper.transparent {
	background: transparent;
}

.grid-main-wrapper.is-grid {
	height: 100%;
}

.wrapper {
	width: 100%;
	display: flex;
	position: relative;
	bottom: 0;
	inset-inline-start: 0;
}

.grid {
	display: grid;
	height: 100%;
	width: 100%;

	grid-row-gap: var(--grid-gap);
	grid-column-gap: var(--grid-gap);

	&.stripe {
		padding: var(--grid-gap) var(--grid-gap) 0 0;
	}
}

.grid-wrapper {
	width: 100%;
	min-width: 0;
	position: relative;
	flex: 1 0 auto;
}

.stripe-wrapper {
	width: 100%;
	min-width: 0;
	position: relative;
}

.dev-mode-video {
	&:not(.dev-mode-screenshot) {
		outline: 1px solid #00FF41;
		color: #00FF41;
	}
	position: relative;

	img {
		object-fit: cover;
		height: 100%;
		width: 100%;
		border-radius: var(--border-radius-element, calc(var(--default-clickable-area) / 2));
	}

	.wrapper {
		position: absolute;
	}
}

.dev-mode__title {
	position: absolute;
	/* stylelint-disable-next-line csstools/use-logical */
	left: var(--default-clickable-area);
	color: #00FF41;
	z-index: 1;
	line-height: 120px;
	font-weight: 900;
	font-size: 100px !important;
	top: 88px;
	opacity: 25%;
}

.dev-mode__toggle {
	position: fixed !important;
	/* stylelint-disable-next-line csstools/use-logical */
	left: 20px;
	top: calc(2 * var(--header-height));
}

.dev-mode__data {
	direction: ltr;
	font-family: monospace;
	position: fixed;
	color: #00FF41;
	/* stylelint-disable-next-line csstools/use-logical */
	left: 20px;
	top: calc(2 * var(--header-height) + 40px);
	padding: 5px;
	background: rgba(0, 0, 0, 0.8);
	border: 1px solid #00FF41;
	display: grid;
	grid-template-columns: 165px 75px;
	align-items: center;
	justify-content: flex-start;
	z-index: 2;

	& span {
		text-overflow: ellipsis;
		overflow: hidden;
		white-space: nowrap;
	}
	& input {
		max-width: 65px;
		height: 22.5px !important;
		min-height: unset;
		margin: 0;
	}
}

.video:last-child {
	grid-column-end: -1;
}

.grid-navigation {
	.grid-wrapper & {
		position: absolute;
		top: calc(50% - var(--default-clickable-area) / 2);

		&__previous {
			inset-inline-start: calc(var(--default-grid-baseline) * 2);
		}

		&__next {
			inset-inline-end: calc(var(--default-grid-baseline) * 2);
		}
	}

	.stripe-wrapper & {
		position: absolute;
		top: calc(var(--navigation-position) + var(--grid-gap));

		&__previous {
			inset-inline-start: var(--navigation-position);
		}

		&__next {
			inset-inline-end: calc(var(--navigation-position) + var(--grid-gap));
		}
	}
}

.stripe--collapse {
	position: absolute !important;
	top: calc(-1 * (var(--default-clickable-area) + var(--navigation-position) / 2));
	inset-inline-end: calc(var(--navigation-position) / 2) ;
}

.stripe--collapse,
.grid-navigation {
	z-index: 2;
	opacity: .7;

	#call-container:hover & {
		background-color: rgba(0, 0, 0, 0.1) !important;

		&:hover,
		&:focus {
			opacity: 1;
			background-color: rgba(0, 0, 0, 0.2) !important;
		}
	}

	&:active {
		/* needed again to override default active button style */
		background: none;
	}
}

</style>
