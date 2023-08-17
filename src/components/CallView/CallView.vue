<!--
  - @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
  - @author Grigorii Shartsev <me@shgk.me>
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
	<div id="call-container">
		<ViewerOverlayCallView v-if="isViewerOverlay"
			:token="token"
			:model="promotedParticipantModel"
			:shared-data="promotedParticipantModel && sharedDatas[promotedParticipantModel.attributes.peerId]" />

		<template v-else>
			<EmptyCallView v-if="!remoteParticipantsCount && !screenSharingActive && !isGrid" :is-sidebar="isSidebar" />

			<div id="videos">
				<template v-if="!isGrid">
					<!-- Selected video override mode -->
					<div v-if="showSelectedVideo"
						class="video__promoted selected-video"
						:class="{'full-page': isOneToOne}">
						<template v-for="callParticipantModel in reversedCallParticipantModels">
							<VideoVue v-if="callParticipantModel.attributes.peerId === selectedVideoPeerId"
								:key="callParticipantModel.attributes.selectedVideoPeerId"
								:token="token"
								:model="callParticipantModel"
								:shared-data="sharedDatas[selectedVideoPeerId]"
								:show-talking-highlight="false"
								:is-grid="true"
								:is-big="true"
								:is-one-to-one="isOneToOne"
								:fit-video="true" />
						</template>
					</div>
					<!-- Screens -->
					<div v-else-if="showLocalScreen || showRemoteScreen || showSelectedScreen" id="screens">
						<!-- local screen -->
						<Screen v-if="showLocalScreen"
							:token="token"
							:local-media-model="localMediaModel"
							:shared-data="localSharedData"
							:is-big="true" />
						<!-- remote screen -->
						<template v-else>
							<template v-for="callParticipantModel in reversedCallParticipantModels">
								<Screen v-if="callParticipantModel.attributes.peerId === shownRemoteScreenPeerId"
									:key="'screen-' + callParticipantModel.attributes.peerId"
									:token="token"
									:call-participant-model="callParticipantModel"
									:shared-data="sharedDatas[shownRemoteScreenPeerId]"
									:is-big="true" />
							</template>
						</template>
					</div>
					<!-- Local Video Override mode (following own video) -->
					<div v-else-if="showLocalVideo"
						class="video__promoted selected-video--local"
						:class="{'full-page': isOneToOne}">
						<LocalVideo ref="localVideo"
							:fit-video="true"
							:is-stripe="false"
							:show-controls="false"
							:is-big="true"
							:token="token"
							:local-media-model="localMediaModel"
							:local-call-participant-model="localCallParticipantModel"
							:is-sidebar="false" />
					</div>
					<!-- Promoted "autopilot" mode -->
					<div v-else
						class="video__promoted autopilot"
						:class="{'full-page': isOneToOne}">
						<VideoVue v-if="promotedParticipantModel"
							:key="promotedParticipantModel.attributes.peerId"
							:token="token"
							:model="promotedParticipantModel"
							:shared-data="sharedDatas[promotedParticipantModel.attributes.peerId]"
							:show-talking-highlight="false"
							:is-grid="true"
							:fit-video="true"
							:is-big="true"
							:is-one-to-one="isOneToOne"
							:is-sidebar="isSidebar" />
					</div>
				</template>

				<!-- Stripe or fullscreen grid depending on `isGrid` -->
				<Grid v-if="!isSidebar"
					v-bind="$attrs"
					:is-stripe="!isGrid"
					:is-recording="isRecording"
					:token="token"
					:fit-video="true"
					:has-pagination="true"
					:min-height="isGrid && !isSidebar ? 240 : 150"
					:min-width="isGrid && !isSidebar ? 320 : 200"
					:videos-cap="gridVideosCap"
					:videos-cap-enforced="gridVideosCapEnforced"
					:call-participant-models="callParticipantModels"
					:screens="screens"
					:target-aspect-ratio="gridTargetAspectRatio"
					:local-media-model="localMediaModel"
					:local-call-participant-model="localCallParticipantModel"
					:shared-datas="sharedDatas"
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

import { getCapabilities } from '@nextcloud/capabilities'
import { showMessage } from '@nextcloud/dialogs'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'

import Grid from './Grid/Grid.vue'
import EmptyCallView from './shared/EmptyCallView.vue'
import LocalVideo from './shared/LocalVideo.vue'
import ReactionToaster from './shared/ReactionToaster.vue'
import Screen from './shared/Screen.vue'
import VideoVue from './shared/VideoVue.vue'
import ViewerOverlayCallView from './shared/ViewerOverlayCallView.vue'

import { SIMULCAST } from '../../constants.js'
import { fetchPeers } from '../../services/callsService.js'
import { EventBus } from '../../services/EventBus.js'
import { localMediaModel, localCallParticipantModel, callParticipantCollection } from '../../utils/webrtc/index.js'
import RemoteVideoBlocker from '../../utils/webrtc/RemoteVideoBlocker.js'

export default {
	name: 'CallView',

	components: {
		EmptyCallView,
		ViewerOverlayCallView,
		Grid,
		LocalVideo,
		ReactionToaster,
		Screen,
		VideoVue,
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

	data() {
		return {
			screens: [],
			localMediaModel,
			localCallParticipantModel,
			sharedDatas: {},
			raisedHandUnwatchers: {},
			speakingUnwatchers: {},
			screenUnwatchers: {},
			speakers: [],
			// callParticipantModelsWithScreen: [],
			localSharedData: {
				screenVisible: true,
			},
			callParticipantCollection,
		}
	},
	computed: {
		promotedParticipantModel() {
			return this.callParticipantModels.find((callParticipantModel) => this.sharedDatas[callParticipantModel.attributes.peerId].promoted)
		},

		callParticipantModels() {
			return callParticipantCollection.callParticipantModels.filter(callParticipantModel => !callParticipantModel.attributes.internal)
		},

		reversedCallParticipantModels() {
			return this.callParticipantModels.slice().reverse()
		},

		remoteParticipantsCount() {
			return this.callParticipantModels.length
		},

		callParticipantModelsWithScreen() {
			return this.callParticipantModels.filter(callParticipantModel => callParticipantModel.attributes.screen)
		},

		callParticipantModelsWithVideo() {
			return this.callParticipantModels.filter(callParticipantModel => {
				return callParticipantModel.attributes.videoAvailable
					&& this.sharedDatas[callParticipantModel.attributes.peerId].remoteVideoBlocker.isVideoEnabled()
					&& (typeof callParticipantModel.attributes.stream === 'object')
			})
		},

		injectableLocalMediaModel() {
			return localMediaModel
		},

		localScreen() {
			return localMediaModel.attributes.localScreen
		},

		screenSharingActive() {
			return this.screens.length > 0
		},

		isViewerOverlay() {
			return this.$store.getters.isViewerOverlay
		},

		isGrid() {
			return this.$store.getters.isGrid && !this.isSidebar
		},

		gridTargetAspectRatio() {
			if (this.isGrid) {
				return 1.5
			} else {
				return 1
			}
		},

		gridVideosCap() {
			return parseInt(loadState('spreed', 'grid_videos_limit'), 10)
		},

		gridVideosCapEnforced() {
			return loadState('spreed', 'grid_videos_limit_enforced')
		},

		selectedVideoPeerId() {
			return this.$store.getters.selectedVideoPeerId
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
		hasLocalVideo() {
			return this.localMediaModel.attributes.videoEnabled
		},

		hasRemoteVideo() {
			return this.callParticipantModelsWithVideo.length > 0
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
			if (!this.screenSharingActive) {
				return null
			}

			if (!this.hasRemoteScreen) {
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

		supportedReactions() {
			return getCapabilities()?.spreed?.config?.call?.['supported-reactions']
		},
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

		speakers() {
			this._setPromotedParticipant()
		},

		screenSharingActive() {
			this._setPromotedParticipant()
		},

		screens() {
			this._setScreenVisible()

		},

		callParticipantModelsWithScreen(newValue, previousValue) {
			// Everytime a new screen is shared, switch to promoted view
			if (newValue.length > previousValue.length) {
				this.$store.dispatch('startPresentation')
			} else if (newValue.length === 0 && previousValue.length > 0 && !this.hasLocalScreen) {
				// last screen share stopped, reopening stripe
				this.$store.dispatch('stopPresentation')
			}
		},
		showLocalScreen(showLocalScreen) {
			// Everytime the local screen is shared, switch to promoted view
			if (showLocalScreen) {
				this.$store.dispatch('startPresentation')
			} else if (this.callParticipantModelsWithScreen.length === 0) {
				this.$store.dispatch('stopPresentation')
			}
		},
		hasLocalVideo(newValue) {
			if (this.$store.getters.selectedVideoPeerId === 'local') {
				if (!newValue) {
					this.$store.dispatch('selectedVideoPeerId', null)
				}
			}
		},

		showSelectedVideo(newVal) {
			if (newVal) {
				this.$store.dispatch('setCallViewMode', { isGrid: false })
			}
		},

		showSelectedScreen(newVal) {
			if (newVal) {
				this.$store.dispatch('setCallViewMode', { isGrid: false })
			}
		},
	},
	created() {
		// Ensure that data is properly initialized before mounting the
		// subviews.
		this.updateDataFromCallParticipantModels(this.callParticipantModels)
	},
	mounted() {
		EventBus.$on('refresh-peer-list', this.debounceFetchPeers)

		callParticipantCollection.on('remove', this._lowerHandWhenParticipantLeaves)

		subscribe('switch-screen-to-id', this._switchScreenToId)
	},
	beforeDestroy() {
		EventBus.$off('refresh-peer-list', this.debounceFetchPeers)

		callParticipantCollection.off('remove', this._lowerHandWhenParticipantLeaves)

		unsubscribe('switch-screen-to-id', this._switchScreenToId)
	},
	methods: {
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
			const addedModels = models.filter(model => !this.sharedDatas[model.attributes.peerId])
			const removedModelIds = Object.keys(this.sharedDatas).filter(sharedDataId => models.find(model => model.attributes.peerId === sharedDataId) === undefined)

			removedModelIds.forEach(removedModelId => {
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

				const index = this.speakers.findIndex(speaker => speaker.id === removedModelId)
				this.speakers.splice(index, 1)

				this._setScreenAvailable(removedModelId, false)
			})

			addedModels.forEach(addedModel => {
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
				const index = this.speakers.findIndex(speaker => speaker.id === peerId)
				const speaker = this.speakers[index]
				speaker.active = true
				this.speakers.splice(index, 1)
				this.speakers.unshift(speaker)

				return
			}

			// Set the speaker as not speaking
			const index = this.speakers.findIndex(speaker => speaker.id === peerId)
			const speaker = this.speakers[index]
			speaker.active = false

			// Move the speaker after all the active speakers
			if (index === 0) {
				this.speakers.shift()

				const firstInactiveSpeakerIndex = this.speakers.findIndex(speaker => !speaker.active)
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
			Object.values(this.sharedDatas).forEach(sharedData => {
				sharedData.promoted = false
			})

			if (!this.screenSharingActive && this.speakers.length) {
				this.sharedDatas[this.speakers[0].id].promoted = true
			}

			this.adjustSimulcastQuality()
		},

		_switchScreenToId(id) {
			const index = this.screens.indexOf(id)
			if (index === -1) {
				return
			}

			this.$store.dispatch('startPresentation')
			this.$store.dispatch('selectedVideoPeerId', null)
			this.screens.splice(index, 1)
			this.screens.unshift(id)
		},

		_setScreenVisible() {
			this.localSharedData.screenVisible = false

			Object.values(this.sharedDatas).forEach(sharedData => {
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
			this.$store.dispatch('startPresentation')
			this.$store.dispatch('selectedVideoPeerId', peerId)
			this.isLocalVideoSelected = false
		},
		handleClickLocalVideo() {
			// DO nothing if no video
			if (!this.hasLocalVideo || this.isSidebar) {
				return
			}
			// Deselect possible selected video
			this.$store.dispatch('selectedVideoPeerId', 'local')
			this.$store.dispatch('startPresentation')
		},

		debounceFetchPeers: debounce(async function() {
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
		}, 1500),

		adjustSimulcastQuality() {
			this.callParticipantModels.forEach(callParticipantModel => {
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
	},
}
</script>

<style lang="scss" scoped>
@import '../../assets/variables';

.call-view {
	width: 100%;
	height: 100%;
	overflow: hidden;
	background-color: $color-call-background;
}

#call-container {
	width: 100%;
	height: 100%;
	background-color: $color-call-background;
	backdrop-filter: blur(25px);
}

#videos {
	position: absolute;
	width: 100%;
	height: calc(100% - 60px);
	top: 60px;
	overflow: hidden;
	display: -webkit-box;
	display: -moz-box;
	display: -ms-flexbox;
	display: -webkit-flex;
	display: flex;
	-webkit-justify-content: space-around;
	justify-content: space-around;
	-webkit-align-items: flex-end;
	align-items: flex-end;
	flex-direction: column;
	padding: 0 8px 8px 8px;
}

.video__promoted {
	position:relative;
	height: 100%;
	width: 100%;
	display: block;
}

.video__promoted.full-page {
	/* make the promoted video cover the whole call view */
	position: static;
}

.local-video {
	position: absolute;
	right: 0;
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

#videos .videoContainer :deep(.avatardiv) {
	box-shadow: 0 0 15px var(--color-box-shadow);
}

.participants-1 #videos .videoContainer :deep(video),
.participants-2 #videos .videoContainer :deep(video) {
	padding: 0;
}

.videoContainer :deep(.avatar-container .avatardiv) {
	display: block;
	margin-left: auto;
	margin-right: auto;
}

.videoContainer.promoted :deep(.avatar-container) {
	top: 30%;
}

.videoContainer.promoted :deep(.avatar-container + .nameIndicator) {
	display: none;
}

.videoContainer.promoted :deep(.mediaIndicator) {
	display: none !important;
}

@media only screen and (max-width: 768px) {
	.participants-1 .videoView,
	.participants-2 .videoView {
		max-height: 35%;
	}
}

.participants-1 .videoView :deep(video),
.participants-2 .videoView :deep(video) {
	position: absolute;
	max-height: 100% !important;
	bottom: 0;
	border-top-right-radius: 3px;
	right: 0;
}

#screens {
	position: relative;
	width: 100%;
	height: 100%;
	overflow: hidden;
}

:deep(.nameIndicator) {
	position: absolute;
	bottom: 0;
	left: 0;
	padding: 12px;
	color: #fff;
	text-shadow: 3px 3px 10px rgba(0, 0, 0, .5), 3px -3px 10px rgba(0, 0, 0, .5), -3px 3px 10px rgba(0, 0, 0, .5), -3px -3px 10px rgba(0, 0, 0, .5);
	width: 100%;
	text-align: center;
	font-size: 20px;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

:deep(.videoView .nameIndicator) {
	padding: 0;
	overflow: visible;
}

.participants-1 .videoView :deep(.nameIndicator),
.participants-2 .videoView :deep(.nameIndicator) {
	left: initial;
	right: 0;
}

.participants-1 .videoView :deep(.avatar-container),
.participants-2 .videoView :deep(.avatar-container) {
	left: initial;
	right: 0;
}

/* ellipsize name in 1on1 calls */
.participants-2 :deep(.videoContainer.promoted + .videoContainer-dummy .nameIndicator) {
	padding: 12px 35%;
}

#videos .videoContainer.speaking:not(.videoView) :deep(.nameIndicator),
#videos .videoContainer.videoView.speaking :deep(.nameIndicator .microphone-icon) {
	animation: pulse 1s;
	animation-iteration-count: infinite;
}

@keyframes pulse {
	0% {
		opacity: 1;
	}
	50% {
		opacity: .3;
	}
	100% {
		opacity: 1;
	}
}

</style>
