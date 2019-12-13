<!--
  - @copyright Copyright (c) 2019, Daniel Calviño Sánchez (danxuliu@gmail.com)
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div id="call-container" :class="callViewClass">
		<div id="videos">
			<template v-for="callParticipantModel in reversedCallParticipantModels">
				<Video
					:key="callParticipantModel.attributes.peerId"
					:model="callParticipantModel"
					:shared-data="sharedDatas[callParticipantModel.attributes.peerId]"
					@switchScreenToId="_switchScreenToId" />
				<Video
					:key="'placeholder' + callParticipantModel.attributes.peerId"
					:placeholder-for-promoted="true"
					:model="callParticipantModel"
					:shared-data="sharedDatas[callParticipantModel.attributes.peerId]"
					@switchScreenToId="_switchScreenToId" />
			</template>
			<LocalVideo ref="localVideo"
				:local-media-model="localMediaModel"
				:local-call-participant-model="localCallParticipantModel"
				:has-dark-background="hasDarkBackground"
				@switchScreenToId="_switchScreenToId" />
		</div>
		<div id="screens">
			<Screen v-if="localMediaModel.attributes.localScreen"
				:local-media-model="localMediaModel"
				:shared-data="localSharedData" />
			<Screen v-for="callParticipantModel in callParticipantModelsWithScreen"
				:key="'screen-' + callParticipantModel.attributes.peerId"
				:call-participant-model="callParticipantModel"
				:shared-data="sharedDatas[callParticipantModel.attributes.peerId]" />
		</div>
	</div>
</template>

<script>
import LocalVideo from './LocalVideo'
import Screen from './Screen'
import Video from './Video'

import { localMediaModel, localCallParticipantModel, callParticipantCollection } from '../../utils/webrtc/index'

export default {

	name: 'CallView',

	components: {
		LocalVideo,
		Screen,
		Video,
	},

	data() {
		return {
			speakers: [],
			speakingUnwatchers: {},
			screens: [],
			screenUnwatchers: {},
			// callParticipantModelsWithScreen: [],
			localSharedData: {
				screenVisible: true,
			},
			sharedDatas: {},

			localMediaModel: localMediaModel,
			localCallParticipantModel: localCallParticipantModel,
			callParticipantCollection: callParticipantCollection,
		}
	},

	computed: {

		callViewClass() {
			const callViewClass = {
				'incall': this.remoteParticipantsCount > 0,
				'screensharing': this.screenSharingActive,
			}
			callViewClass['participants-' + (this.remoteParticipantsCount + 1)] = true

			return callViewClass
		},

		callParticipantModels() {
			return callParticipantCollection.callParticipantModels
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

		localScreen() {
			return localMediaModel.attributes.localScreen
		},

		screenSharingActive() {
			return this.screens.length > 0
		},

		hasDarkBackground() {
			return this.remoteParticipantsCount > 0 || this.screenSharingActive
		},

	},

	watch: {

		hasDarkBackground: function(hasDarkBackground) {
			this.$emit('hasDarkBackground', hasDarkBackground)
		},

		localScreen: function(localScreen) {
			this._setScreenAvailable(this.localCallParticipantModel.attributes.peerId, localScreen)
		},

		callParticipantModels: function(models) {
			const addedModels = models.filter(model => !this.sharedDatas[model.attributes.peerId])
			const removedModelIds = Object.keys(this.sharedDatas).filter(sharedDataId => models.find(model => model.attributes.peerId === sharedDataId) === undefined)

			removedModelIds.forEach(removedModelId => {
				this.$delete(this.sharedDatas, removedModelId)

				this.speakingUnwatchers[removedModelId]()
				// Not reactive, but not a problem
				delete this.speakingUnwatchers[removedModelId]

				this.screenUnwatchers[removedModelId]()
				// Not reactive, but not a problem
				delete this.screenUnwatchers[removedModelId]

				const index = this.speakers.findIndex(speaker => speaker.id === removedModelId)
				this.speakers.splice(index, 1)

				this._setScreenAvailable(removedModelId, false)
			})

			addedModels.forEach(addedModel => {
				const sharedData = {
					promoted: false,
					videoEnabled: true,
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
			})
		},

		'speakers': function() {
			this._setPromotedParticipant()
		},

		'screenSharingActive': function() {
			this._setPromotedParticipant()
		},

		'screens': function() {
			this._setScreenVisible()
		},

	},

	methods: {

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
		},

		_switchScreenToId(id) {
			const index = this.screens.indexOf(id)
			if (index === -1) {
				return
			}

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

	},

}
</script>

<style lang="scss" scoped>
#call-container {
	width: 100%;
	height: 100%;
}

.incall,
.screensharing {
	background-color: #000;
}

#videos {
	position: absolute;
	width: 100%;
	height: 100%;
	top: 0;
	display: -webkit-box;
	display: -moz-box;
	display: -ms-flexbox;
	display: -webkit-flex;
	display: flex;
	-webkit-justify-content: space-around;
	justify-content: space-around;
	-webkit-align-items: flex-end;
	align-items: flex-end;
}

#videos.hidden {
	display: none;
}

.videoContainer,
/* Force regular rules on "big speaker video" when screensharing is enabled. */
.participants-1.screensharing .videoContainer,
.participants-2.screensharing .videoContainer {
	position: relative;
	width: 100%;
	padding: 0 2%;
	-webkit-box-flex: auto;
	-moz-box-flex: auto;
	-webkit-flex: auto;
	-ms-flex: auto;
	flex: auto;
	z-index: 2;
	display: flex;
	justify-content: center;
	align-items: flex-end;
}

.videoContainer.hidden,
.participants-1.screensharing .videoContainer.hidden,
.participants-2.screensharing .videoContainer.hidden {
	display: none;
}

.screensharing .videoContainer {
	max-height: 200px;
}

::v-deep video {
	z-index: 0;
	max-height: 100%;
	/* default filter for slightly better look */
	-webkit-filter: contrast(1.1) saturate(1.1) sepia(.1);
	filter: contrast(1.1) saturate(1.1) sepia(.1);
	vertical-align: top; /* fix white line below video */
}

.screensharing .videoContainer ::v-deep video {
	max-height: 200px;
	background-color: transparent;
	box-shadow: none;
}

#screens ::v-deep video {
	width: 100%;
	-webkit-filter: none;
	filter: none;
}

#videos .videoContainer.not-connected ::v-deep {
	video,
	.avatardiv {
		opacity: 0.5;
	}
}

#videos .videoContainer:not(.promoted) ::v-deep video {
	max-height: 200px;
	max-width: 100%;
	background-color: transparent;
	border-radius: var(--border-radius) var(--border-radius) 0 0;
	box-shadow: 0 0 15px var(--color-box-shadow);
}

#videos .videoContainer ::v-deep .avatardiv {
	box-shadow: 0 0 15px var(--color-box-shadow);
}

.participants-1 #videos .videoContainer ::v-deep video,
.participants-2 #videos .videoContainer ::v-deep video {
	padding: 0;
}

.videoContainer ::v-deep .avatar-container {
	position: absolute;
	bottom: 44px;
	left: 0;
	width: 100%;
}

.videoContainer ::v-deep .avatar-container .avatardiv--unknown {
	// Force grey background for guest avatars
	background-color: #b9b9b9 !important;
}

/* Text avatars need to be forced to 128px, as imageplaceholder() overrides
	* the given size with the actual height of the element it was called on, so
	* the text avatar may have any hardcoded height. Note that this does not
	* apply to regular image avatars, as in that case they are always requested
	* with a size of 128px. */
.videoContainer ::v-deep .avatar-container .avatardiv {
	width: 128px !important;
	height: 128px !important;
	line-height: 128px !important;
	/* imageplaceholder() sets font-size to "height * 0.55" */
	font-size: 70.4px !important;
}

.videoContainer ::v-deep .avatar-container .avatardiv {
	display: block;
	margin-left: auto;
	margin-right: auto;
}
.videoContainer.promoted ::v-deep .avatar-container {
	top: 30%;
}
.videoContainer.promoted ::v-deep .avatar-container + .nameIndicator {
	display: none;
}

.videoContainer.promoted ::v-deep .mediaIndicator {
	display: none !important;
}

.participants-1:not(.screensharing) ~ #emptycontent {
	display: block !important;
}

/* big speaker video */
.participants-1 .videoContainer,
.participants-2 .videoContainer,
.videoContainer.promoted {
	position: absolute;
	width: 100%;
	height: 100%;
	overflow: hidden;
	left: 0;
	top: 0;
	z-index: 1;
}
.videoContainer.promoted ::v-deep video,
.participants-2 .videoContainer:not(.videoView) ::v-deep video {
	position: absolute;
	width: initial;
	height: 100%;
	left: 50%;
	top: 50%;
	transform: translateY(-50%) translateX(-50%);
}

/* own video */
.participants-1 .videoView,
.participants-2 .videoView {
	position: absolute;
	width: 22%;
	min-width: 200px;
	overflow:visible;
	right: 0;
	bottom: 0;
	top: initial;
	left: initial;
}
@media only screen and (max-width: 768px) {
	.participants-1 .videoView,
	.participants-2 .videoView {
		max-height: 35%;
	}
}
.participants-1 .videoView ::v-deep video,
.participants-2 .videoView ::v-deep video {
	position: absolute;
	max-height: 100% !important;
	bottom: 0;
	border-top-right-radius: 3px;
	right: 0;
}

.screensharing #screens {
	position: absolute;
	width: 100%;
	height: calc(100% - 200px);
	top: 0;
	background-color: transparent;
}

.screensharing .screenContainer {
	position: relative;
	width: 100%;
	height: 100%;
	overflow: hidden;
}

::v-deep .nameIndicator {
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

::v-deep .videoView .nameIndicator {
	padding: 0;
	overflow: visible;
}

.participants-1 .videoView ::v-deep .nameIndicator,
.participants-2 .videoView ::v-deep .nameIndicator {
	left: initial;
	right: 0;
}

.participants-1 .videoView ::v-deep .avatar-container,
.participants-2 .videoView ::v-deep .avatar-container {
	left: initial;
	right: 0;
}

/* ellipsize name in 1on1 calls */
.participants-2 ::v-deep .videoContainer.promoted + .videoContainer-dummy .nameIndicator {
	padding: 12px 35%;
}

#videos .videoContainer.speaking:not(.videoView) ::v-deep .nameIndicator,
#videos .videoContainer.videoView.speaking ::v-deep .nameIndicator .icon-audio {
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
