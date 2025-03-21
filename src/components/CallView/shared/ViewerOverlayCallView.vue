<!--
  - @copyright Copyright (c) 2023 Grigorii Shartsev <me@shgk.me>
  -
  - @license AGPL-3.0-or-later
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
	<div ref="ghost" class="viewer-overlay-ghost">
		<!--
			Viewer Overlay should be teleported to be to the top of DOM to be on top of the Viewer,
			because by default Viewer is on top of an entire Talk (#content-vue).
			In the fullscreen mode Viewer is manually moved to #content-vue which is top layer by Fullscreen API.
			FIXME: this is not correct to use Portal/Teleport to move something inside the Vue app.
			Alternative solutions could be:
			- Use full version of the Portal library (doesn't solve the same problem with Viewer)
			- Use a new child of #content-vue as Talk Vue app
		-->
		<!-- Also Portal's selector is not reactive. We need to re-mount the node on selector change using key -->
		<Portal :key="portalSelector" :selector="portalSelector">
			<!-- Add .app-talk to use Talk icon classes outside of #content-vue -->
			<div class="viewer-overlay app-talk"
				:style="{
					right: position.right + 'px',
					bottom: position.bottom + 'px'
				}">
				<div class="viewer-overlay__collapse"
					:class="{ collapsed: isCollapsed }">
					<NcButton type="secondary"
						class="viewer-overlay__button"
						:aria-label="
							isCollapsed ? t('spreed', 'Collapse') : t('spreed', 'Expand')
						"
						@click.stop="isCollapsed = !isCollapsed">
						<template #icon>
							<ChevronDown v-if="!isCollapsed" :size="20" />
							<ChevronUp v-else :size="20" />
						</template>
					</NcButton>
				</div>

				<TransitionWrapper name="slide-down">
					<div v-show="!isCollapsed"
						class="viewer-overlay__video-container"
						tabindex="0"
						@click="maximize">
						<div class="video-overlay__top-bar">
							<NcButton type="secondary"
								class="viewer-overlay__button"
								:aria-label="t('spreed', 'Expand')"
								@click.stop="maximize">
								<template #icon>
									<ArrowExpand :size="20" />
								</template>
							</NcButton>
						</div>

						<!-- local screen -->
						<Screen v-if="showLocalScreen"
							:token="token"
							:local-media-model="localModel"
							:shared-data="localSharedData" />
						<!-- remote screen -->
						<Screen v-else-if="model && screens[model.attributes.peerId]"
							:token="token"
							:call-participant-model="model"
							:shared-data="sharedData" />

						<VideoVue v-else-if="model"
							class="viewer-overlay__video"
							:token="token"
							:model="model"
							:shared-data="sharedData"
							is-grid
							un-selectable
							hide-bottom-bar
							@click-video="maximize">
							<template #bottom-bar />
						</VideoVue>

						<EmptyCallView v-else is-small />

						<LocalVideo v-if="localModel.attributes.videoEnabled"
							class="viewer-overlay__local-video"
							:token="token"
							:show-controls="false"
							:local-media-model="localModel"
							:local-call-participant-model="localCallParticipantModel"
							is-small
							un-selectable />

						<div class="viewer-overlay__bottom-bar">
							<LocalAudioControlButton class="viewer-overlay__button"
								:token="token"
								:conversation="conversation"
								:model="localModel"
								type="secondary"
								disable-keyboard-shortcuts />
							<LocalVideoControlButton class="viewer-overlay__button"
								:token="token"
								:conversation="conversation"
								:model="localModel"
								type="secondary"
								disable-keyboard-shortcuts />
						</div>
					</div>
				</TransitionWrapper>
			</div>
		</Portal>
	</div>
</template>

<script>
import { Portal } from '@linusborg/vue-simple-portal'

import ArrowExpand from 'vue-material-design-icons/ArrowExpand.vue'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import ChevronUp from 'vue-material-design-icons/ChevronUp.vue'

import { NcButton } from '@nextcloud/vue'

import EmptyCallView from './EmptyCallView.vue'
import LocalAudioControlButton from './LocalAudioControlButton.vue'
import LocalVideo from './LocalVideo.vue'
import LocalVideoControlButton from './LocalVideoControlButton.vue'
import Screen from './Screen.vue'
import VideoVue from './VideoVue.vue'
import TransitionWrapper from '../../UIShared/TransitionWrapper.vue'

import { localCallParticipantModel, localMediaModel } from '../../../utils/webrtc/index.js'

export default {
	name: 'ViewerOverlayCallView',

	components: {
		EmptyCallView,
		LocalAudioControlButton,
		LocalVideoControlButton,
		Portal,
		Screen,
		LocalVideo,
		ChevronUp,
		ChevronDown,
		NcButton,
		TransitionWrapper,
		VideoVue,
		ArrowExpand,
	},

	props: {
		token: {
			type: String,
			required: true,
		},

		// Promoted participant model
		model: {
			type: Object,
			required: false,
			default: null,
		},

		sharedData: {
			type: Object,
			required: false,
			default: null,
		},

		localModel: {
			type: Object,
			required: false,
			default: () => localMediaModel,
		},

		localCallParticipantModel: {
			type: Object,
			required: false,
			default: () => localCallParticipantModel,
		},

		localSharedData: {
			type: Object,
			required: true,
			default: () => {}
		},

		screens: {
			type: Array,
			required: false,
			default: () => [],
		}
	},

	data() {
		return {
			isCollapsed: false,
			observer: null,
			position: {
				right: 0,
				bottom: 0,
			},
		}
	},

	computed: {
		conversation() {
			return this.$store.getters.conversation(this.token)
		},

		portalSelector() {
			return this.$store.getters.getMainContainerSelector()
		},

		hasLocalScreen() {
			return !!this.localModel.attributes.localScreen
		},

		showLocalScreen() {
			return this.hasLocalScreen && this.screens[0] === localCallParticipantModel.attributes.peerId
		},
	},

	mounted() {
		this.updatePosition()
		this.observer = new ResizeObserver(this.updatePosition)
		this.observer.observe(this.$refs.ghost)
	},

	beforeDestroy() {
		this.observer.disconnect()
	},

	methods: {
		maximize() {
			if (OCA.Viewer) {
				OCA.Viewer.close()
			}
			this.$store.dispatch('setCallViewMode', { isViewerOverlay: false })
		},

		updatePosition() {
			const { right, bottom } = this.$refs.ghost.getBoundingClientRect()
			this.position.right = window.innerWidth - right
			this.position.bottom = window.innerHeight - bottom
		},
	},
}
</script>

<style lang="scss" scoped>
.viewer-overlay-ghost {
	position: absolute;
	bottom: 8px;
	right: 8px;
	left: 0;
}

.viewer-overlay {
	--aspect-ratio: calc(3 / 4);
	--width: 20vw;
	--min-width: 250px;
	--max-width: 400px;
	position: absolute;
	width: var(--width);
	min-width: var(--min-width);
	max-width: var(--max-width);
	min-height: calc(var(--default-clickable-area) + 8px);
	z-index: 11000;
}

.viewer-overlay * {
	box-sizing: border-box;
}

.viewer-overlay__collapse {
	position: absolute;
	top: 8px;
	right: 8px;
	z-index: 100;
}

.viewer-overlay__button {
	opacity: 0.8;
	&:active,
	&:hover,
	&:focus {
		opacity: 0.9;
	}
}

.video-overlay__top-bar {
	position: absolute;
	top: 8px;
	left: 8px;
	z-index: 100;
}

.viewer-overlay__bottom-bar {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
	position: absolute;
	bottom: 0;
	width: 100%;
	padding: 0 12px 8px 12px;
	z-index: 1;
}

.viewer-overlay__video-container {
	width: 100%;
	height: calc(var(--width) * var(--aspect-ratio));
	min-height: calc(var(--min-width) * var(--aspect-ratio));
	max-height: calc(var(--max-width) * var(--aspect-ratio));
	/* Note: because of transition it always has position absolute on animation */
	bottom: 0;
	right: 0;
}

.viewer-overlay__local-video {
	position: absolute;
	bottom: 8px;
	right: 8px;
	width: 25%;
	height: 25%;
	overflow: hidden;
}

.viewer-overlay__video {
	position: relative;
	height: 100%;
}

:deep(.screen) {
	border-radius: calc(var(--default-clickable-area) / 4);
}
</style>
