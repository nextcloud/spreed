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
			<div ref="videoContainer" class="video__promoted">
				<template v-for="callParticipantModel in reversedCallParticipantModels">
					<Video
						v-if="sharedDatas[callParticipantModel.attributes.peerId].promoted"
						:key="callParticipantModel.attributes.peerId"
						:token="token"
						:model="callParticipantModel"
						:shared-data="sharedDatas[callParticipantModel.attributes.peerId]"
						:use-constrained-layout="useConstrainedLayout"
						:is-grid="true"
						:fit-video="true"
						@switchScreenToId="_switchScreenToId" />
				</template>
			</div>
			<div class="videos-stripe">
				<GridView
					v-bind="$attrs"
					:is-stripe="true"
					:token="token"
					:min-height="250"
					boundaries-element-class="videos-stripe"
					:has-pagination="false" />
			</div>
			<!--
			</div>
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
				-->

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
	</div>
</template>

<script>
import EmptyCallView from '../shared/EmptyCallView'
import Screen from '../shared/Screen'
import Video from '../shared/Video'
import call from '../../../mixins/call'
import GridView from '../GridView/GridView'

export default {

	name: 'PromotedView',

	components: {
		EmptyCallView,
		Screen,
		Video,
		GridView,
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
			videoContainerAspectRatio: 0,
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
		// Get the aspect ratio of the incoming stream
		getVideoContainerAspectRatio() {
			const videoContainerWidth = this.$refs.videoContainer.clientWidth
			const VideoContainerHeight = this.$refs.videoContainer.clientHeight
			this.videoContainerAspectRatio = videoContainerWidth / VideoContainerHeight
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
	flex-direction: column;
}

.videos-stripe {
	position: relative;
	bottom: 0;
	left: 0;
	width: 100%;
	display: block;
	height: 250px;
}

.video__promoted {
	position:relative;
	height: 100%;
	width: 100%;
	display: block;
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
