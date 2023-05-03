<!--
  - @copyright Copyright (c) 2023 Grigorii Shartsev <me@shgk.me>
  -
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
	<div ref="ghost" class="viewer-overlay-ghost">
		<Portal>
			<div class="viewer-overlay"
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

				<Transition name="slide-down">
					<div v-show="!isCollapsed" class="viewer-overlay__video-container">
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

						<LocalVideo v-if="localModel.attributes.videoEnabled"
							class="viewer-overlay__local-video"
							:token="token"
							:show-controls="false"
							:local-media-model="localModel"
							:local-call-participant-model="localCallParticipantModel"
							is-small
							un-selectable />

						<VideoVue class="viewer-overlay__video"
							:token="token"
							:model="model"
							:shared-data="sharedData"
							is-grid
							un-selectable
							@click-video="maximize">
							<template #bottom-bar>
								<div class="viewer-overlay__bottom-bar">
									<LocalAudioControlButton class="viewer-overlay__button"
										:conversation="conversation"
										:model="localModel"
										nc-button-type="secondary"
										disable-keyboard-shortcuts />
									<LocalVideoControlButton class="viewer-overlay__button"
										:conversation="conversation"
										:model="localModel"
										nc-button-type="secondary"
										disable-keyboard-shortcuts />
								</div>
							</template>
						</VideoVue>
					</div>
				</Transition>
			</div>
		</Portal>
	</div>
</template>

<script>
import { Portal } from '@linusborg/vue-simple-portal'

import ArrowExpand from 'vue-material-design-icons/ArrowExpand.vue'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import ChevronUp from 'vue-material-design-icons/ChevronUp.vue'

import { NcButton, Tooltip } from '@nextcloud/vue'

import LocalAudioControlButton from './LocalAudioControlButton.vue'
import LocalVideo from './LocalVideo.vue'
import LocalVideoControlButton from './LocalVideoControlButton.vue'
import VideoVue from './VideoVue.vue'

import { localCallParticipantModel, localMediaModel } from '../../../utils/webrtc/index.js'

export default {
	name: 'ViewerOverlayCallView',

	components: {
		LocalAudioControlButton,
		LocalVideoControlButton,
		Portal,
		LocalVideo,
		ChevronUp,
		ChevronDown,
		NcButton,
		VideoVue,
		ArrowExpand,
	},

	directives: {
		tooltip: Tooltip,
	},

	props: {
		token: {
			type: String,
			required: true,
		},

		model: {
			type: Object,
			required: true,
		},

		sharedData: {
			type: Object,
			required: true,
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
@import "../../../assets/variables";

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
  bottom: 10%;
  right: 5%;
  width: 25%;
  height: 25%;
  overflow: hidden;
}

.viewer-overlay__video {
	position: relative;
	height: 100%;
}
</style>
