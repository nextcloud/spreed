<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div ref="ghost" class="viewer-overlay-ghost">
		<Teleport to="body">
			<!-- Add .app-talk to use Talk icon classes outside of #content-vue -->
			<div class="viewer-overlay app-talk"
				:style="computedStyle">
				<div class="viewer-overlay__collapse"
					:class="{ collapsed: isCollapsed }">
					<NcButton variant="secondary"
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
							<NcButton variant="secondary"
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
								variant="secondary"
								disable-keyboard-shortcuts />
							<LocalVideoControlButton class="viewer-overlay__button"
								:token="token"
								:conversation="conversation"
								:model="localModel"
								variant="secondary"
								disable-keyboard-shortcuts />
						</div>
					</div>
				</TransitionWrapper>
			</div>
		</Teleport>
	</div>
</template>

<script>
import { isRTL, t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import ArrowExpand from 'vue-material-design-icons/ArrowExpand.vue'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import ChevronUp from 'vue-material-design-icons/ChevronUp.vue'
import TransitionWrapper from '../../UIShared/TransitionWrapper.vue'
import EmptyCallView from './EmptyCallView.vue'
import LocalAudioControlButton from './LocalAudioControlButton.vue'
import LocalVideo from './LocalVideo.vue'
import LocalVideoControlButton from './LocalVideoControlButton.vue'
import Screen from './Screen.vue'
import VideoVue from './VideoVue.vue'
import { useCallViewStore } from '../../../stores/callView.ts'
import { localCallParticipantModel, localMediaModel } from '../../../utils/webrtc/index.js'

export default {
	name: 'ViewerOverlayCallView',

	components: {
		EmptyCallView,
		LocalAudioControlButton,
		LocalVideoControlButton,
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
			default: () => {},
		},

		screens: {
			type: Array,
			required: false,
			default: () => [],
		},
	},

	setup() {
		return {
			callViewStore: useCallViewStore(),
		}
	},

	data() {
		return {
			isCollapsed: false,
			observer: null,
			position: {
				right: 0,
				left: 0,
				bottom: 0,
			},
		}
	},

	computed: {
		conversation() {
			return this.$store.getters.conversation(this.token)
		},

		hasLocalScreen() {
			return !!this.localModel.attributes.localScreen
		},

		showLocalScreen() {
			return this.hasLocalScreen && this.screens[0] === localCallParticipantModel.attributes.peerId
		},

		computedStyle() {
			return {
				[isRTL() ? 'left' : 'right']: this.position[isRTL() ? 'left' : 'right'] + 'px',
				bottom: this.position.bottom + 'px',
			}
		},
	},

	mounted() {
		this.updatePosition()
		this.observer = new ResizeObserver(this.updatePosition)
		this.observer.observe(this.$refs.ghost)
	},

	beforeUnmount() {
		this.observer.disconnect()
	},

	methods: {
		t,
		maximize() {
			if (OCA.Viewer) {
				OCA.Viewer.close()
			}
			this.callViewStore.setIsViewerOverlay(false)
		},

		updatePosition() {
			const { left, right, bottom } = this.$refs.ghost.getBoundingClientRect()
			if (isRTL()) {
				this.position.left = left
			} else {
				this.position.right = window.innerWidth - right
			}
			this.position.bottom = window.innerHeight - bottom
		},
	},
}
</script>

<style lang="scss" scoped>
.viewer-overlay-ghost {
	position: absolute;
	bottom: 8px;
	inset-inline: 0 8px;
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
	inset-inline-end: 8px;
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
	inset-inline-start: 8px;
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
	inset-inline-end: 0;
}

.viewer-overlay__local-video {
	position: absolute;
	bottom: 8px;
	inset-inline-end: 8px;
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
