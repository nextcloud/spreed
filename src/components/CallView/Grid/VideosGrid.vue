<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div ref="gridWrapper" class="grid-main-wrapper" :class="{ 'is-grid': !isStripe, overlap: isOverlap }">
		<NcButton
			v-if="isStripe && !isRecording"
			class="stripe--collapse"
			variant="tertiary-no-background"
			:title="stripeButtonTitle"
			:aria-label="stripeButtonTitle"
			@click="handleClickStripeCollapse">
			<template #icon>
				<IconChevronDown
					v-if="stripeOpen"
					fillColor="#ffffff"
					:size="20" />
				<IconChevronUp
					v-else
					fillColor="#ffffff"
					:size="20" />
			</template>
		</NcButton>
		<TransitionWrapper :name="isStripe ? 'slide-down' : undefined">
			<div v-if="!isStripe || stripeOpen" class="wrapper" :style="wrapperStyle">
				<div :class="[isStripe ? 'stripe-wrapper' : 'grid-wrapper']">
					<NcButton
						v-if="hasPreviousPage && gridWidth > 0"
						variant="tertiary-no-background"
						class="grid-navigation grid-navigation__previous"
						:aria-label="t('spreed', 'Previous page of videos')"
						@click="handleClickPrevious">
						<template #icon>
							<IconChevronLeft
								class="bidirectional-icon"
								fillColor="#ffffff"
								:size="20" />
						</template>
					</NcButton>
					<div
						ref="grid"
						class="grid"
						:class="{ stripe: isStripe }"
						:style="gridStyle"
						@wheel="debounceHandleWheelEvent">
						<template v-if="!devMode && !(isLessThanTwoVideos && isStripe)">
							<EmptyCallView v-if="videos.length === 0 && !isStripe" class="video" :isGrid="true" />
							<VideoVue
								v-for="callParticipantModel in displayedVideos"
								:key="callParticipantModel.attributes.peerId"
								:class="{ video: !isStripe }"
								:showVideoOverlay="showVideoOverlay"
								:token="token"
								:model="callParticipantModel"
								:isGrid="true"
								:showTalkingHighlight="!isStripe"
								:isStripe="isStripe"
								:isPromoted="sharedDatas[callParticipantModel.attributes.peerId].promoted && !(isStripe && screens.length)"
								:isSelected="isSelected(callParticipantModel)"
								:sharedData="sharedDatas[callParticipantModel.attributes.peerId]"
								@clickVideo="handleClickVideo($event, callParticipantModel.attributes.peerId)" />
						</template>
						<!-- VideosGrid developer mode -->
						<template v-if="devMode">
							<div
								v-for="key in displayedVideos"
								:key="key"
								class="dev-mode-video video"
								:class="{ 'dev-mode-screenshot': screenshotMode }">
								<img :alt="placeholderName(key)" :src="placeholderImage(key)">
								<VideoBottomBar
									:hasShadow="false"
									:model="placeholderModel(key)"
									:sharedData="placeholderSharedData(key)"
									:token="token"
									:participantName="placeholderName(key, !screenshotMode)" />
							</div>
							<h1 v-if="!screenshotMode" class="dev-mode__title">
								Dev mode on ;-)
							</h1>
						</template>
						<LocalVideo
							v-if="!isStripe && !isRecording"
							ref="localVideo"
							class="video"
							isGrid
							:fitVideo="false"
							:token="token"
							:localMediaModel="localMediaModel"
							:localCallParticipantModel="localCallParticipantModel"
							@clickVideo="handleClickLocalVideo" />
					</div>
					<NcButton
						v-if="hasNextPage && gridWidth > 0"
						variant="tertiary-no-background"
						class="grid-navigation grid-navigation__next"
						:aria-label="t('spreed', 'Next page of videos')"
						@click="handleClickNext">
						<template #icon>
							<IconChevronRight
								class="bidirectional-icon"
								fillColor="#ffffff"
								:size="20" />
						</template>
					</NcButton>
				</div>
				<LocalVideo
					v-if="isStripe && !isRecording"
					ref="localVideo"
					class="video"
					:class="{ 'local-video--highlighted': isLessThanTwoVideos && isStripe }"
					:isStripe="true"
					:showControls="false"
					:token="token"
					:localMediaModel="localMediaModel"
					:localCallParticipantModel="localCallParticipantModel"
					@clickVideo="handleClickLocalVideo" />

				<template v-if="devMode">
					<NcButton
						variant="tertiary"
						class="dev-mode__toggle"
						aria-label="Toggle screenshot mode"
						@click="screenshotMode = !screenshotMode">
						<template #icon>
							<IconChevronLeft
								v-if="!screenshotMode"
								class="bidirectional-icon"
								fillColor="#00FF41"
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
import { t } from '@nextcloud/l10n'
import debounce from 'debounce'
import { computed, inject, ref, toRef, useTemplateRef } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import IconChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import IconChevronLeft from 'vue-material-design-icons/ChevronLeft.vue'
import IconChevronRight from 'vue-material-design-icons/ChevronRight.vue'
import IconChevronUp from 'vue-material-design-icons/ChevronUp.vue'
import TransitionWrapper from '../../UIShared/TransitionWrapper.vue'
import EmptyCallView from '../shared/EmptyCallView.vue'
import LocalVideo from '../shared/LocalVideo.vue'
import VideoBottomBar from '../shared/VideoBottomBar.vue'
import VideoVue from '../shared/VideoVue.vue'
import { PARTICIPANT } from '../../../constants.ts'
import { getTalkConfig } from '../../../services/CapabilitiesManager.ts'
import { useActorStore } from '../../../stores/actor.ts'
import { useCallViewStore } from '../../../stores/callView.ts'
import { GRID_GAP } from './gridLayout.ts'
import { placeholderImage, placeholderModel, placeholderName, placeholderSharedData } from './gridPlaceholders.ts'
import { useGridDimensions } from './useGridDimensions.ts'

// Max number of videos per page. `0`, the default value, means no cap
const videosCap = getTalkConfig('local', 'call', 'grid-limit') || 0

export default {
	name: 'VideosGrid',

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

		isOverlap: {
			type: Boolean,
			default: false,
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

		showVideoOverlay: {
			type: Boolean,
			default: true,
		},
	},

	emits: ['selectVideo', 'clickLocalVideo'],

	setup(props) {
		// Developer mode: If enabled it allows to debug the grid using dummy videos
		const devMode = inject('CallView:devModeEnabled', ref(false))
		const screenshotMode = inject('CallView:screenshotModeEnabled', ref(false))
		// The number of dummy videos in dev mode
		const dummies = ref(4)

		const actorStore = useActorStore()
		const callViewStore = useCallViewStore()

		// Template refs for the elements measured by the grid layout
		const gridWrapper = useTemplateRef('gridWrapper')
		const grid = useTemplateRef('grid')

		const stripeOpen = computed(() => callViewStore.isStripeOpen && !props.isRecording)

		// Number of tiles to lay out (clamped to `videosCap`, `0` means no cap)
		const cappedVideosCount = computed(() => {
			const count = devMode.value ? dummies.value : props.callParticipantModels.length
			return videosCap ? Math.min(videosCap, count) : count
		})

		const gridDimensions = useGridDimensions({
			wrapper: gridWrapper,
			grid,
			isStripe: toRef(() => props.isStripe),
			isSidebar: toRef(() => props.isSidebar),
			isRecording: toRef(() => props.isRecording),
			videoCount: cappedVideosCount,
			stripeOpen,
		})

		return {
			devMode,
			dummies,
			screenshotMode,
			videosCap,
			callViewStore,
			actorStore,
			gridWrapper,
			grid,
			stripeOpen,
			...gridDimensions,
		}
	},

	data() {
		return {
			// The current page
			currentPage: 0,
			debounceHandleWheelEvent: () => {},
			tempPromotedModels: [],
			unpromoteSpeakerTimer: {},
			promotedHistoryMask: [],
		}
	},

	computed: {
		stripeButtonTitle() {
			if (this.stripeOpen) {
				return t('spreed', 'Collapse participant bar')
			} else {
				return t('spreed', 'Expand participant bar')
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
		// TODO: properly handle resizes when not on first page:
		// currently if the user is not on the 'first page', upon resize the
		// current position in the videos array is lost (`slots` changes, so
		// `currentPage * slots` points at a different window of the videos)
		displayedVideos() {
			if (!this.slots) {
				return []
			}

			const slots = this.slots

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

		// The local video always takes one slot if the grid view is not shown as a stripe.
		// In recording mode the local video is not shown, so all slots are available.
		noLocalVideoReserve() {
			return this.isStripe || this.isRecording
		},

		// Number of grid slots (videos per page) at any given moment, clamped to
		// `videosCap` (`0` means no cap).
		// The cap is primarily enforced by shrinking the grid layout (see
		// `computeGridDimensions`); this clamp keeps the "videos per page" math
		// consistent even before the layout has been recomputed.
		slots() {
			const slots = this.noLocalVideoReserve ? this.rows * this.columns : this.rows * this.columns - 1
			return this.videosCap ? Math.min(this.videosCap, slots) : slots
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

		wrapperStyle() {
			if (this.isStripe) {
				return 'height: 250px'
			} else {
				return 'height: 100%'
			}
		},

		participantsInitialised() {
			return this.$store.getters.participantsInitialised(this.token)
		},

		isGuestNonModerator() {
			return this.actorStore.isActorGuest
				&& this.$store.getters.conversation(this.token).participantType !== PARTICIPANT.TYPE.GUEST_MODERATOR
		},

		orderedVideos() {
			// Dynamic ordering is not possible for guests because
			// participants store is not initialized
			if (this.isGuestNonModerator || this.devMode) {
				return this.videos
			}

			const objectMap = {
				modelsWithScreenshare: [],
				modelsTempPromoted: [],
				modelsWithVideoEnabled: [],
				modelsWithAudioOnly: [],
				modelsWithNoPermissions: [],
			}
			const screensSet = new Set(this.screens)
			const tempPromotedModelsSet = new Set(this.tempPromotedModels.map((model) => model.attributes.nextcloudSessionId))
			const videoTilesMap = new Map()
			const audioTilesMap = new Map()

			this.callParticipantModels.forEach((model) => {
				if (screensSet.has(model.attributes.peerId)) {
					objectMap.modelsWithScreenshare.push(model)
				} else if (tempPromotedModelsSet.has(model.attributes.nextcloudSessionId)) {
					objectMap.modelsTempPromoted.push(model)
				} else if (this.isModelWithVideo(model)) {
					videoTilesMap.set(model.attributes.nextcloudSessionId, model)
				} else if (this.participantsInitialised && this.isModelWithAudio(model)) {
					audioTilesMap.set(model.attributes.nextcloudSessionId, model)
				} else {
					objectMap.modelsWithNoPermissions.push(model)
				}
			})

			objectMap.modelsWithVideoEnabled = this.getOrderedTiles(videoTilesMap, this.promotedHistoryMask)
			objectMap.modelsWithAudioOnly = this.getOrderedTiles(audioTilesMap, this.promotedHistoryMask)

			return [
				...objectMap.modelsWithScreenshare,
				...objectMap.modelsTempPromoted,
				...objectMap.modelsWithVideoEnabled,
				...objectMap.modelsWithAudioOnly,
				...objectMap.modelsWithNoPermissions,
			]
		},

		speakers() {
			return this.callParticipantModels.filter((model) => model.attributes.speaking)
		},

		speakersWithAudioOff() {
			return this.tempPromotedModels.filter((model) => !model.attributes.audioAvailable)
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
		isStripe() {
			// Reset current page when switching between stripe and full grid,
			// as the previous page is meaningless in the new mode.
			// The grid layout itself is recomputed by `useGridDimensions`.
			this.currentPage = 0
		},

		numberOfPages() {
			if (this.currentPage >= this.numberOfPages) {
				this.currentPage = Math.max(0, this.numberOfPages - 1)
			}
		},

		speakers(models) {
			models.forEach((model) => {
				this.promoteSpeaker(model)
				clearTimeout(this.unpromoteSpeakerTimer[model.attributes.nextcloudSessionId])
			})
		},

		speakersWithAudioOff(newModels, oldModels) {
			newModels.forEach((speaker) => {
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
		this.debounceHandleWheelEvent = debounce(this.handleWheelEvent, 50)

		if (OC.debug) {
			OCA.Talk.gridDebugInformation = this.gridDebugInformation
			OCA.Talk.gridDevModeEnable = this.enableDevMode
		}
	},

	beforeUnmount() {
		this.debounceHandleWheelEvent.clear?.()

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
				targetAspectRatio: this.targetAspectRatio,
				videosCount: this.videosCount,
				videoWidth: this.videoWidth,
				videoHeight: this.videoHeight,
				devicePixelRatio: window.devicePixelRatio,
				dpiFactor: this.dpiFactor,
				dpiAwareMinWidth: this.dpiAwareMinWidth,
				dpiAwareMinHeight: this.dpiAwareMinHeight,
				gridAspectRatio: this.gridAspectRatio,
				columns: this.columns,
				rows: this.rows,
				numberOfPages: this.numberOfPages,
				bodyWidth: document.body.clientWidth,
				bodyHeight: document.body.clientHeight,
				gridWidth: this.grid?.clientWidth,
				gridHeight: this.grid?.clientHeight,
			})
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
			console.debug('handleclicknext, ', 'currentPage ', this.currentPage, 'slots ', this.slots, 'videos.length ', this.videos.length)
		},

		handleClickPrevious() {
			this.currentPage--
			console.debug('handleclickprevious, ', 'currentPage ', this.currentPage, 'slots ', this.slots, 'videos.length ', this.videos.length)
		},

		handleClickStripeCollapse() {
			this.callViewStore.setCallViewMode({ token: this.token, isStripeOpen: !this.stripeOpen, clearLast: false })
		},

		handleClickVideo(event, peerId) {
			console.debug('selected-video peer id', peerId)
			this.$emit('selectVideo', peerId)
		},

		handleClickLocalVideo() {
			this.$emit('clickLocalVideo')
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
			if (this.orderedVideos.slice(0, this.slots).find((video) => video.attributes.nextcloudSessionId === id)) {
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
			orderMask.forEach((id) => {
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

	row-gap: var(--grid-gap);
	column-gap: var(--grid-gap);

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
	position: relative;

	&:not(.dev-mode-screenshot) {
		outline: 1px solid #00FF41;
		color: #00FF41;
	}

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
	top: calc(-1 * (var(--default-clickable-area) + var(--grid-gap)));
	inset-inline-end: var(--navigation-position);
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

	.overlap & {
		inset-inline-end: var(--grid-gap);
	}

	&:active {
		/* needed again to override default active button style */
		background: none;
	}
}

.local-video--highlighted {
	inset-block-end: var(--grid-gap);
	inset-inline-end: var(--grid-gap);
	box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2), 0 6px 20px 0 rgba(0, 0, 0, 0.19);
}

</style>
