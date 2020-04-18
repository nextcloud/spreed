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
		<EmptyCallView v-if="!remoteParticipantsCount && !screenSharingActive" />
		<div id="videos">
			<template v-for="callParticipantModel in reversedCallParticipantModels">
				<Video
					:key="callParticipantModel.attributes.peerId"
					:token="token"
					:model="callParticipantModel"
					:shared-data="sharedDatas[callParticipantModel.attributes.peerId]"
					:use-constrained-layout="useConstrainedLayout"
					@switchScreenToId="_switchScreenToId" />
				<Video
					:key="'placeholder' + callParticipantModel.attributes.peerId"
					:token="token"
					:placeholder-for-promoted="true"
					:model="callParticipantModel"
					:shared-data="sharedDatas[callParticipantModel.attributes.peerId]"
					:use-constrained-layout="useConstrainedLayout"
					@switchScreenToId="_switchScreenToId" />
			</template>
			<LocalVideo ref="localVideo"
				:local-media-model="localMediaModel"
				:local-call-participant-model="localCallParticipantModel"
				:use-constrained-layout="useConstrainedLayout"
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
import EmptyCallView from '../shared/EmptyCallView'
import LocalVideo from '../shared/LocalVideo'
import Screen from '../shared/Screen'
import Video from '../shared/Video'
import call from '../../../mixins/call'
import { callParticipantCollection } from '../../../utils/webrtc/index'

export default {

	name: 'PromotedView',

	components: {
		EmptyCallView,
		LocalVideo,
		Screen,
		Video,
	},

	mixins: [call],

	props: {
		useConstrainedLayout: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			speakers: [],
			speakingUnwatchers: {},
			screenUnwatchers: {},
			// callParticipantModelsWithScreen: [],
			localSharedData: {
				screenVisible: true,
			},
			sharedDatas: {},
			callParticipantCollection: callParticipantCollection,
		}
	},

	computed: {

		callViewClass() {
			const callViewClass = {
				'incall': this.remoteParticipantsCount > 0,
				'screensharing': this.screenSharingActive,
				'constrained-layout': this.useConstrainedLayout,
			}
			callViewClass['participants-' + (this.remoteParticipantsCount + 1)] = true

			return callViewClass
		},
	},

	created() {
		// Ensure that data is properly initialized before mounting the
		// subviews.
		this.updateDataFromCallParticipantModels(this.callParticipantModels)
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

#videos .emptycontent {
	height: 50%;
	transform: translateY(-50%)
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

.constrained-layout.screensharing .videoContainer {
	max-height: 100px;

	/* Avatars slightly overflow the container; although they overlap the shared
	 * screen it is not too bad and it is better than compressing even further
	 * the shared screen. */
	overflow: visible;
}

::v-deep video {
	z-index: 0;
	max-height: 100%;
	/* default filter for slightly better look */
	/* Disabled for now as it causes a huuuuge performance drop.
	 CPU usage is more than halved without this.
	 -webkit-filter: contrast(1.1) saturate(1.1) sepia(.1);
	 filter: contrast(1.1) saturate(1.1) sepia(.1);
	 */
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
	.avatardiv,
	.avatar.guest {
		opacity: 0.5;
	}
}

#videos .videoContainer:not(.promoted) ::v-deep video {
	max-height: 200px;
	max-width: 100%;
	background-color: transparent;
	border-radius: var(--border-radius) var(--border-radius) 0 0;
	box-shadow: 0 0 15px var(--color-box-shadow);
	bottom: 44px;
	position: relative;
}

.constrained-layout #videos .videoContainer:not(.promoted) ::v-deep video {
	/* Make the unpromoted videos smaller to not overlap too much the promoted
	 * video */
	max-height: 100px;
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
.constrained-layout.participants-1 .videoView,
.constrained-layout.participants-2 .videoView {
	/* Do not force the width to 200px, as otherwise the video is too tall and
	 * overlaps too much with the promoted video. */
	min-width: initial;
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

.constrained-layout.screensharing #screens {
	/* The row with the participants is shorter in the constrained layout to
	 * make room for the promoted video and the shared screens. */
	height: calc(100% - 100px);
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

.constrained-layout ::v-deep .nameIndicator {
	/* Reduce padding to bring the name closer to the bottom */
	padding: 3px;
	/* Use default font size, as it takes too much space otherwise */
	font-size: initial;
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

.constrained-layout.participants-2 ::v-deep .videoContainer.promoted + .videoContainer-dummy .nameIndicator {
	/* Reduce padding to bring the name closer to the bottom */
	padding: 3px 35%;
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
