<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div id="call-container" :class="callContainerClass">
		<ViewerOverlayCallView v-if="isViewerOverlay"
			:token="token"
			:model="promotedParticipantModel"
			:shared-data="promotedParticipantModel && sharedDatas[promotedParticipantModel.attributes.peerId]"
			:screens="screens"
			:local-shared-data="localSharedData" />

		<template v-else>
			<EmptyCallView v-if="showEmptyCallView" :is-sidebar="isSidebar" />

			<div id="videos">
				<div v-if="devMode ? !isGrid : (!isGrid || !callParticipantModels.length)"
					class="video__promoted"
					:class="{ 'full-page': showFullPage }">
					<!-- Selected video override mode -->
					<VideoVue v-if="showSelectedVideo && selectedCallParticipantModel"
						:key="`promoted-${selectedVideoPeerId}`"
						:token="token"
						:model="selectedCallParticipantModel"
						:shared-data="sharedDatas[selectedVideoPeerId]"
						:show-talking-highlight="false"
						:is-one-to-one="isOneToOne"
						is-grid
						is-big
						fit-video />

					<!-- Local Video Override mode (following own video) -->
					<LocalVideo v-else-if="showLocalVideo"
						ref="localVideo"
						:token="token"
						:local-media-model="localMediaModel"
						:local-call-participant-model="localCallParticipantModel"
						:is-stripe="false"
						:show-controls="false"
						:is-sidebar="false"
						is-big
						fit-video />

					<!-- Screens -->
					<!-- Local screen -->
					<Screen v-else-if="showLocalScreen"
						key="screen-local"
						:token="token"
						:local-media-model="localMediaModel"
						:shared-data="localSharedData"
						is-big />
					<!-- Remote or selected screen -->
					<Screen v-else-if="(showRemoteScreen || showSelectedScreen) && shownRemoteScreenCallParticipantModel"
						:key="`screen-${shownRemoteScreenPeerId}`"
						:token="token"
						:call-participant-model="shownRemoteScreenCallParticipantModel"
						:shared-data="sharedDatas[shownRemoteScreenPeerId]"
						is-big />
					<!-- Promoted "autopilot" mode -->
					<VideoVue v-else-if="promotedParticipantModel"
						:key="`autopilot-${promotedParticipantModel.attributes.peerId}`"
						:token="token"
						:model="promotedParticipantModel"
						:shared-data="sharedDatas[promotedParticipantModel.attributes.peerId]"
						:show-talking-highlight="false"
						is-grid
						fit-video
						is-big
						:is-one-to-one="isOneToOne"
						:is-sidebar="isSidebar"
						@force-promote-video="forcePromotedModel = $event" />
					<!-- presenter overlay -->
					<PresenterOverlay v-if="shouldShowPresenterOverlay"
						:token="token"
						:model="presenterModel"
						:shared-data="presenterSharedData"
						:is-local-presenter="showLocalScreen"
						:local-media-model="localMediaModel"
						:is-collapsed="!showPresenterOverlay"
						@click="toggleShowPresenterOverlay" />

					<div v-else-if="devMode && !isGrid"
						class="dev-mode-video--promoted">
						<img :alt="placeholderName(6)" :src="placeholderImage(6)">
						<VideoBottomBar :has-shadow="false"
							:model="placeholderModel(6)"
							:shared-data="placeholderSharedData(6)"
							:token="token"
							:participant-name="placeholderName(6)"
							is-big />
					</div>
				</div>

				<!-- Stripe or fullscreen grid depending on `isGrid` -->
				<Grid v-if="!isSidebar"
					:is-stripe="devMode ? !isGrid : (!isGrid || !callParticipantModels.length)"
					:is-recording="isRecording"
					:token="token"
					:has-pagination="true"
					:call-participant-models="callParticipantModels"
					:screens="screens"
					:local-media-model="localMediaModel"
					:local-call-participant-model="localCallParticipantModel"
					:shared-datas="sharedDatas"
					v-bind="$attrs"
					@select-video="handleSelectVideo"
					@click-local-video="handleClickLocalVideo" />

				<ReactionToaster v-if="supportedReactions?.length"
					:token="token"
					:supported-reactions="supportedReactions"
					:call-participant-models="callParticipantModels" />

				<!-- Local video if sidebar -->
				<LocalVideo v-if="isSidebar && !showLocalVideo"
					ref="localVideo"
					class="local-video"
					:class="{ 'local-video--sidebar': isSidebar }"
					:show-controls="false"
					:fit-video="true"
					:is-stripe="true"
					:token="token"
					:local-media-model="localMediaModel"
					:local-call-participant-model="localCallParticipantModel"
					:is-sidebar="isSidebar"
					@click-video="handleClickLocalVideo" />
			</div>
		</template>
	</div>
</template>

<script>
import debounce from 'debounce'
import { provide, ref } from 'vue'

import { showMessage } from '@nextcloud/dialogs'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'

import Grid from './Grid/Grid.vue'
import EmptyCallView from './shared/EmptyCallView.vue'
import LocalVideo from './shared/LocalVideo.vue'
import PresenterOverlay from './shared/PresenterOverlay.vue'
import ReactionToaster from './shared/ReactionToaster.vue'
import Screen from './shared/Screen.vue'
import VideoBottomBar from './shared/VideoBottomBar.vue'
import VideoVue from './shared/VideoVue.vue'
import ViewerOverlayCallView from './shared/ViewerOverlayCallView.vue'

import { placeholderImage, placeholderModel, placeholderName, placeholderSharedData } from './Grid/gridPlaceholders.ts'
import { useWakeLock } from './useWakeLock.ts'
import { SIMULCAST } from '../../constants.ts'
import BrowserStorage from '../../services/BrowserStorage.js'
import { fetchPeers } from '../../services/callsService.js'
import { getTalkConfig } from '../../services/CapabilitiesManager.ts'
import { EventBus } from '../../services/EventBus.ts'
import { useCallViewStore } from '../../stores/callView.ts'
import { useSettingsStore } from '../../stores/settings.js'
import { satisfyVersion } from '../../utils/satisfyVersion.ts'
import { localMediaModel, localCallParticipantModel, callParticipantCollection } from '../../utils/webrtc/index.js'
import RemoteVideoBlocker from '../../utils/webrtc/RemoteVideoBlocker.js'

const serverVersion = loadState('core', 'config', {}).version ?? '29.0.0.0'
const serverSupportsBackgroundBlurred = satisfyVersion(serverVersion, '29.0.4.0')

export default {
	name: 'CallView',

	components: {
		EmptyCallView,
		Grid,
		LocalVideo,
		PresenterOverlay,
		ReactionToaster,
		Screen,
		VideoBottomBar,
		VideoVue,
		ViewerOverlayCallView,
	},

	props: {
		token: {
			type: String,
			required: true,
		},

		// Determines whether this component is used in the sidebar
		isSidebar: {
			type: Boolean,
			default: false,
		},

		// Determines whether this component is used in the recording view
		isRecording: {
			type: Boolean,
			default: false,
		},
	},

	setup(props) {
		// Prevent the screen from turning off
		useWakeLock()

		// For debug and screenshot purposes. Set to true to enable
		const devMode = ref(false)
		provide('CallView:devModeEnabled', devMode)
		const screenshotMode = ref(false)
		provide('CallView:screenshotModeEnabled', screenshotMode)
		const settingsStore = useSettingsStore()
		// If media settings was not used, we check the global config of default devices state here
		if (!settingsStore.getShowMediaSettings(props.token) && settingsStore.startWithoutMedia) {
			localMediaModel.disableAudio()
			localMediaModel.disableVideo()
		}

		// Fallback ref for versions before v29.0.4
		const isBackgroundBlurred = ref(BrowserStorage.getItem('background-blurred') !== 'false')

		return {
			localMediaModel,
			localCallParticipantModel,
			callParticipantCollection,
			devMode,
			callViewStore: useCallViewStore(),
			isBackgroundBlurred,
		}
	},

	data() {
		return {
			screens: [],
			sharedDatas: {},
			raisedHandUnwatchers: {},
			speakingUnwatchers: {},
			screenUnwatchers: {},
			speakers: [],
			localSharedData: {
				screenVisible: true,
			},

			showPresenterOverlay: true,
			debounceFetchPeers: () => {},
			forcePromotedModel: null,
		}
	},

	computed: {
		promotedParticipantModel() {
			return this.forcePromotedModel ?? this.callParticipantModels.find((callParticipantModel) => this.sharedDatas[callParticipantModel.attributes.peerId].promoted)
		},

		callParticipantModels() {
			return callParticipantCollection.callParticipantModels.value.filter((callParticipantModel) => !callParticipantModel.attributes.internal || callParticipantModel.attributes.videoAvailable)
		},

		callParticipantModelsWithScreen() {
			return this.callParticipantModels.filter((callParticipantModel) => callParticipantModel.attributes.screen)
		},

		localScreen() {
			return localMediaModel.attributes.localScreen
		},

		screenSharingActive() {
			return this.screens.length > 0
		},

		isViewerOverlay() {
			return this.callViewStore.isViewerOverlay
		},

		isGrid() {
			return this.callViewStore.isGrid && !this.isSidebar
		},

		selectedVideoPeerId() {
			return this.callViewStore.selectedVideoPeerId
		},

		selectedCallParticipantModel() {
			if (!this.showSelectedVideo || !this.selectedVideoPeerId) {
				return null
			}
			return this.callParticipantModels.find((callParticipantModel) => {
				return callParticipantModel.attributes.peerId === this.selectedVideoPeerId
			})
		},

		hasSelectedScreen() {
			return this.selectedVideoPeerId !== null && this.screens.includes(this.selectedVideoPeerId)
		},

		hasSelectedVideo() {
			return this.selectedVideoPeerId !== null && !this.screens.includes(this.selectedVideoPeerId)
		},

		isOneToOne() {
			return this.callParticipantModels.length === 1
		},

		showFullPage() {
			return this.isOneToOne && !(this.showLocalScreen || this.showRemoteScreen || this.showSelectedScreen)
		},

		hasLocalVideo() {
			return this.localMediaModel.attributes.videoEnabled
		},

		hasLocalScreen() {
			return !!this.localMediaModel.attributes.localScreen
		},

		hasRemoteScreen() {
			return this.callParticipantModelsWithScreen.length > 0
		},
		// The following conditions determine what to show in the "Big container"
		// of the promoted view

		// Show selected video (other than local)
		showSelectedVideo() {
			return this.hasSelectedVideo && !this.showLocalVideo
		},

		showSelectedScreen() {
			return this.hasSelectedScreen && !this.showLocalVideo
		},

		// Shows the local video if selected
		showLocalVideo() {
			return this.hasLocalVideo && this.selectedVideoPeerId === 'local'
		},

		// Show local screen
		showLocalScreen() {
			return this.hasLocalScreen && this.selectedVideoPeerId === null && this.screens[0] === localCallParticipantModel.attributes.peerId
		},

		// Show somebody else's screen. This will show the screen of the last
		// person that shared it.
		showRemoteScreen() {
			return this.shownRemoteScreenPeerId !== null && !this.showSelectedVideo && !this.showSelectedScreen
		},

		shownRemoteScreenPeerId() {
			if (!this.screenSharingActive || !this.hasRemoteScreen) {
				return null
			}

			if (this.screens.includes(this.selectedVideoPeerId)) {
				return this.selectedVideoPeerId
			}

			if (!this.hasSelectedScreen) {
				return this.screens[0]
			}

			return null
		},

		shownRemoteScreenCallParticipantModel() {
			if (!this.shownRemoteScreenPeerId) {
				return null
			}
			return this.callParticipantModels.find((callParticipantModel) => {
				return callParticipantModel.attributes.peerId === this.shownRemoteScreenPeerId
			})
		},

		shouldShowPresenterOverlay() {
			return (this.showLocalScreen && this.hasLocalVideo)
				|| ((this.showRemoteScreen || this.showSelectedScreen)
					&& (this.shownRemoteScreenCallParticipantModel?.attributes.videoAvailable || this.isModelWithVideo(this.shownRemoteScreenCallParticipantModel)))
		},

		presenterModel() {
			// Prioritize local screen over remote screen, if both are available (as in DOM order)
			return this.showLocalScreen ? this.localCallParticipantModel : this.shownRemoteScreenCallParticipantModel
		},

		presenterSharedData() {
			return this.showLocalScreen ? this.localSharedData : this.sharedDatas[this.shownRemoteScreenPeerId]
		},

		presenterVideoBlockerEnabled() {
			return this.sharedDatas[this.shownRemoteScreenPeerId]?.remoteVideoBlocker?.isVideoEnabled()
		},

		showEmptyCallView() {
			return !this.callParticipantModels.length && !this.screenSharingActive && !this.devMode
		},

		supportedReactions() {
			return getTalkConfig(this.token, 'call', 'supported-reactions')
		},

		/**
		 * Fallback style for versions before v29.0.4
		 */
		callContainerClass() {
			if (serverSupportsBackgroundBlurred) {
				return
			}

			return this.isBackgroundBlurred ? 'call-container__blurred' : 'call-container__non-blurred'
		}
	},

	watch: {
		'localCallParticipantModel.attributes.peerId'(newValue, previousValue) {
			const index = this.screens.indexOf(previousValue)
			if (index !== -1) {
				this.screens[index] = newValue
			}
		},

		localScreen(localScreen) {
			this._setScreenAvailable(localCallParticipantModel.attributes.peerId, localScreen)
		},

		callParticipantModels(models) {
			this.updateDataFromCallParticipantModels(models)
		},

		isGrid() {
			this.adjustSimulcastQuality()
		},

		selectedVideoPeerId() {
			this.adjustSimulcastQuality()
		},

		speakers(value) {
			if (value) {
				this._setPromotedParticipant()
			}
		},

		shownRemoteScreenPeerId(value) {
			if (value) {
				this._setPromotedParticipant()
			}
		},

		screens() {
			this._setScreenVisible()
		},

		callParticipantModelsWithScreen(newValue, previousValue) {
			// Everytime a new screen is shared, switch to promoted view
			if (newValue.length > previousValue.length) {
				this.callViewStore.startPresentation(this.token)
			} else if (newValue.length === 0 && previousValue.length > 0 && !this.hasLocalScreen && !this.selectedVideoPeerId) {
				// last screen share stopped and no selected video, restoring previous state
				this.callViewStore.stopPresentation(this.token)
			}
		},

		showLocalScreen(showLocalScreen) {
			// Everytime the local screen is shared, switch to promoted view
			if (showLocalScreen) {
				this.callViewStore.startPresentation(this.token)
			} else if (this.callParticipantModelsWithScreen.length === 0 && !this.selectedVideoPeerId) {
				// last screen share stopped and no selected video, restoring previous state
				this.callViewStore.stopPresentation(this.token)
			}
		},

		hasLocalVideo(newValue) {
			if (this.selectedVideoPeerId === 'local') {
				if (!newValue) {
					this.callViewStore.setSelectedVideoPeerId(null)
				}
			}
		},

		presenterVideoBlockerEnabled(value) {
			this.showPresenterOverlay = value
		},

		showEmptyCallView: {
			immediate: true,
			handler(value) {
				this.callViewStore.setIsEmptyCallView(value)
			},
		}
	},

	created() {
		// Ensure that data is properly initialized before mounting the
		// subviews.
		this.updateDataFromCallParticipantModels(this.callParticipantModels)
	},

	mounted() {
		this.debounceFetchPeers = debounce(this.fetchPeers, 1500)
		EventBus.on('refresh-peer-list', this.debounceFetchPeers)

		callParticipantCollection.on('remove', this._lowerHandWhenParticipantLeaves)

		subscribe('switch-screen-to-id', this._switchScreenToId)
		subscribe('set-background-blurred', this.setBackgroundBlurred)
	},

	beforeDestroy() {
		this.debounceFetchPeers.clear?.()
		this.callViewStore.setIsEmptyCallView(true)
		EventBus.off('refresh-peer-list', this.debounceFetchPeers)

		callParticipantCollection.off('remove', this._lowerHandWhenParticipantLeaves)

		unsubscribe('switch-screen-to-id', this._switchScreenToId)
		unsubscribe('set-background-blurred', this.setBackgroundBlurred)
	},

	methods: {
		t,
		// Placeholder data for devMode and screenshotMode
		placeholderImage,
		placeholderName,
		placeholderModel,
		placeholderSharedData,
		/**
		 * Updates data properties that depend on the CallParticipantModels.
		 *
		 * The data contains some properties that can not be dynamically
		 * computed but that depend on the current CallParticipantModels, so
		 * this function adds and removes elements and watchers as needed based
		 * on the given CallParticipantModels.
		 *
		 * @param {Array} models the array of CallParticipantModels
		 */
		updateDataFromCallParticipantModels(models) {
			const addedModels = models.filter((model) => !this.sharedDatas[model.attributes.peerId])
			const removedModelIds = Object.keys(this.sharedDatas).filter((sharedDataId) => models.find((model) => model.attributes.peerId === sharedDataId) === undefined)

			removedModelIds.forEach((removedModelId) => {
				this.sharedDatas[removedModelId].remoteVideoBlocker.destroy()

				this.$delete(this.sharedDatas, removedModelId)

				this.speakingUnwatchers[removedModelId]()
				// Not reactive, but not a problem
				delete this.speakingUnwatchers[removedModelId]

				this.screenUnwatchers[removedModelId]()
				// Not reactive, but not a problem
				delete this.screenUnwatchers[removedModelId]

				this.raisedHandUnwatchers[removedModelId]()
				// Not reactive, but not a problem
				delete this.raisedHandUnwatchers[removedModelId]

				const index = this.speakers.findIndex((speaker) => speaker.id === removedModelId)
				this.speakers.splice(index, 1)

				this._setScreenAvailable(removedModelId, false)
			})

			addedModels.forEach((addedModel) => {
				const sharedData = {
					promoted: false,
					remoteVideoBlocker: new RemoteVideoBlocker(addedModel),
					screenVisible: false,
				}

				this.$set(this.sharedDatas, addedModel.attributes.peerId, sharedData)

				// Not reactive, but not a problem
				this.speakingUnwatchers[addedModel.attributes.peerId] = this.$watch(function() {
					return addedModel.attributes.speaking
				}, function(speaking) {
					this._setSpeaking(addedModel.attributes.peerId, speaking)
				})

				this.speakers.push({
					id: addedModel.attributes.peerId,
					active: false,
				})

				// Not reactive, but not a problem
				this.screenUnwatchers[addedModel.attributes.peerId] = this.$watch(function() {
					return addedModel.attributes.screen
				}, function(screen) {
					this._setScreenAvailable(addedModel.attributes.peerId, screen)
				})

				// Not reactive, but not a problem
				this.raisedHandUnwatchers[addedModel.attributes.peerId] = this.$watch(function() {
					return addedModel.attributes.raisedHand
				}, function(raisedHand) {
					this._handleParticipantRaisedHand(addedModel, raisedHand)
				})

				this.adjustSimulcastQualityForParticipant(addedModel)
			})
		},

		_setSpeaking(peerId, speaking) {
			if (speaking) {
				// Move the speaker to the first element of the list
				const index = this.speakers.findIndex((speaker) => speaker.id === peerId)
				const speaker = this.speakers[index]
				speaker.active = true
				this.speakers.splice(index, 1)
				this.speakers.unshift(speaker)

				return
			}

			// Set the speaker as not speaking
			const index = this.speakers.findIndex((speaker) => speaker.id === peerId)
			const speaker = this.speakers[index]
			speaker.active = false

			// Move the speaker after all the active speakers
			if (index === 0) {
				this.speakers.shift()

				const firstInactiveSpeakerIndex = this.speakers.findIndex((speaker) => !speaker.active)
				if (firstInactiveSpeakerIndex === -1) {
					this.speakers.push(speaker)
				} else {
					this.speakers.splice(firstInactiveSpeakerIndex, 0, speaker)
				}
			}
		},

		_handleParticipantRaisedHand(callParticipantModel, raisedHand) {
			const nickName = callParticipantModel.attributes.name || callParticipantModel.attributes.userId
			// sometimes the nick name is not available yet...
			if (nickName) {
				if (raisedHand?.state) {
					showMessage(t('spreed', '{nickName} raised their hand.', { nickName }))
				}
			} else {
				if (raisedHand?.state) {
					showMessage(t('spreed', 'A participant raised their hand.'))
				}
			}

			// update in callViewStore
			this.$store.dispatch('setParticipantHandRaised', {
				sessionId: callParticipantModel.attributes.nextcloudSessionId,
				raisedHand,
			})
		},

		_lowerHandWhenParticipantLeaves(callParticipantCollection, callParticipantModel) {
			this.$store.dispatch('setParticipantHandRaised', {
				sessionId: callParticipantModel.attributes.nextcloudSessionId,
				raisedHand: false,
			})
		},

		_setScreenAvailable(id, screen) {
			if (screen) {
				this.screens.unshift(id)

				return
			}

			const index = this.screens.indexOf(id)
			if (index !== -1) {
				this.screens.splice(index, 1)
			}
		},

		_setPromotedParticipant() {
			Object.values(this.sharedDatas).forEach((sharedData) => {
				sharedData.promoted = false
			})

			if (!this.screenSharingActive && this.speakers.length) {
				this.sharedDatas[this.speakers[0].id].promoted = true
			} else if (this.shownRemoteScreenPeerId && this.sharedDatas[this.shownRemoteScreenPeerId]) {
				this.sharedDatas[this.shownRemoteScreenPeerId].promoted = true
			}

			this.adjustSimulcastQuality()
		},

		_switchScreenToId(id) {
			const index = this.screens.indexOf(id)
			if (index === -1) {
				return
			}

			if (this.callViewStore.presentationStarted) {
				this.callViewStore.setCallViewMode({
					token: this.token,
					isGrid: false,
					isStripeOpen: false,
					clearLast: false,
				})
			} else {
				this.callViewStore.startPresentation(this.token)
			}
			this.callViewStore.setSelectedVideoPeerId(null)
			this.screens.splice(index, 1)
			this.screens.unshift(id)
		},

		_setScreenVisible() {
			this.localSharedData.screenVisible = false

			Object.values(this.sharedDatas).forEach((sharedData) => {
				sharedData.screenVisible = false
			})

			if (!this.screens.length) {
				return
			}

			if (this.screens[0] === this.localCallParticipantModel.attributes.peerId) {
				this.localSharedData.screenVisible = true

				return
			}

			this.sharedDatas[this.screens[0]].screenVisible = true
		},

		handleSelectVideo(peerId) {
			if (this.isSidebar) {
				return
			}
			this.callViewStore.setSelectedVideoPeerId(peerId)
			this.callViewStore.setCallViewMode({
				token: this.token,
				isGrid: false,
				isStripeOpen: false,
				clearLast: false,
			})
		},

		handleClickLocalVideo() {
			// DO nothing if no video
			if (!this.hasLocalVideo || this.isSidebar) {
				return
			}
			// Deselect possible selected video
			this.callViewStore.setSelectedVideoPeerId('local')
			this.callViewStore.setCallViewMode({
				token: this.token,
				isGrid: false,
				isStripeOpen: false,
				clearLast: false,
			})
		},

		async fetchPeers() {
			// The recording participant does not have a Nextcloud session, so
			// it can not fetch the peers. This should not be a problem, as all
			// the needed data for the recording should be (eventually)
			// available in the signaling data.
			if (this.isRecording) {
				return
			}

			const token = this.token
			try {
				const response = await fetchPeers(token)
				this.$store.dispatch('purgePeersStore')

				response.data.ocs.data.forEach((peer) => {
					this.$store.dispatch('addPeer', {
						token,
						peer,
					})
				})
			} catch (exception) {
				// Just means guests have no name, so don't error …
				console.error(exception)
			}
		},

		adjustSimulcastQuality() {
			this.callParticipantModels.forEach((callParticipantModel) => {
				this.adjustSimulcastQualityForParticipant(callParticipantModel)
			})
		},

		adjustSimulcastQualityForParticipant(callParticipantModel) {
			if (this.isGrid) {
				callParticipantModel.setSimulcastVideoQuality(SIMULCAST.MEDIUM)
			} else if (this.sharedDatas[callParticipantModel.attributes.peerId].promoted || this.selectedVideoPeerId === callParticipantModel.attributes.peerId) {
				callParticipantModel.setSimulcastVideoQuality(SIMULCAST.HIGH)
			} else {
				callParticipantModel.setSimulcastVideoQuality(SIMULCAST.LOW)
			}
		},

		/**
		 * Fallback method for versions before v29.0.4
		 * @param {boolean} value whether background should be blurred
		 */
		setBackgroundBlurred(value) {
			this.isBackgroundBlurred = value
		},

		isModelWithVideo(callParticipantModel) {
			if (!callParticipantModel) {
				return false
			}
			return callParticipantModel.attributes.videoAvailable
				&& this.sharedDatas[callParticipantModel.attributes.peerId].remoteVideoBlocker.isVideoEnabled()
				&& (typeof callParticipantModel.attributes.stream === 'object')
		},

		toggleShowPresenterOverlay() {
			if (!this.showLocalScreen && !this.presenterVideoBlockerEnabled) {
				this.sharedDatas[this.shownRemoteScreenPeerId].remoteVideoBlocker.setVideoEnabled(true)
			} else {
				this.showPresenterOverlay = !this.showPresenterOverlay
			}
		},

	},
}
</script>

<style lang="scss" scoped>
@import '../../assets/variables';

#call-container {
	width: 100%;
	height: 100%;
	background-color: $color-call-background;
	// Default value has changed since v29.0.4: 'blur(25px)' => 'none'
	backdrop-filter: var(--filter-background-blur);
	--grid-gap: calc(var(--default-grid-baseline) * 2);

	&.call-container__blurred {
		backdrop-filter: blur(25px);
	}
	&.call-container__non-blurred {
		backdrop-filter: none;
	}
}

#videos {
	position: absolute;
	width: 100%;
	height: calc(100% - 51px);
	top: 51px; // TopBar height
	overflow: hidden;
	display: flex;
	justify-content: space-around;
	align-items: flex-end;
	flex-direction: column;
	padding: calc(var(--default-grid-baseline) * 2);
	padding-block-start: 0;
}

.video__promoted {
	position: relative;
	height: 100%;
	width: 100%;

	&.full-page {
		// force the promoted remote or local video to cover the whole call view
		// doesn't affect screen shares, as it's a different MediaStream
		position: static;
	}

	.dev-mode-video--promoted {
		position: absolute;
		width: 100%;
		height: 100%;
		display: flex;
		justify-content: center;
	}

	.dev-mode-video--promoted img {
		position: absolute;
		height: 100%;
		aspect-ratio: 4 / 3;
		object-fit: cover;
		border-radius: var(--border-radius-element, calc(var(--default-clickable-area) / 2));
	}
}

.local-video {
	position: absolute;
	inset-inline-end: 0;
	bottom: 0;
	width: 300px;
	height: 250px;

	&--sidebar {
		width: 150px;
		height: 100px;
	}
}

#videos.hidden {
	display: none;
}

:deep(video) {
	z-index: 0;
	/* default filter for slightly better look */
	/* Disabled for now as it causes a huuuuge performance drop.
	 CPU usage is more than halved without this.
	 -webkit-filter: contrast(1.1) saturate(1.1) sepia(.1);
	 filter: contrast(1.1) saturate(1.1) sepia(.1);
	 */
	vertical-align: top; /* fix white line below video */
}

#videos :deep(video) {
	padding: 0;
}

</style>
