<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<VueDraggableResizable
		v-if="!isCollapsed"
		ref="presenterOverlay"
		parent
		class="presenter-overlay"
		:resizable="false"
		:h="presenterOverlaySize"
		:w="presenterOverlaySize"
		:x="isDirectionRTL ? parentWidth - presenterOverlaySize - 10 : 10"
		:y="10"
		@dragging="isDragging = true"
		@dragstop="isDragging = false">
		<LocalVideo
			v-if="isLocalPresenter"
			class="presenter-overlay__video"
			:token="token"
			:local-media-model="localMediaModel"
			:local-call-participant-model="model"
			is-presenter-overlay
			un-selectable
			hide-bottom-bar
			@click-presenter="$emit('click')" />
		<VideoVue
			v-else
			:token="token"
			:class="{ dragging: isDragging }"
			class="presenter-overlay__video"
			:model="model"
			:shared-data="sharedData"
			is-presenter-overlay
			un-selectable
			hide-bottom-bar
			@click-presenter="$emit('click')" />
	</VueDraggableResizable>

	<!-- presenter button when presenter overlay is collapsed -->
	<NcButton
		v-else
		:aria-label="t('spreed', 'Show presenter')"
		:title="t('spreed', 'Show presenter')"
		class="presenter-overlay--collapsed"
		variant="tertiary-no-background"
		@click="$emit('click')">
		<template #icon>
			<AccountBox fill-color="#ffffff" :size="20" />
		</template>
	</NcButton>
</template>

<script>

import { isRTL, t } from '@nextcloud/l10n'
import { ref } from 'vue'
import VueDraggableResizable from 'vue-draggable-resizable'
import NcButton from '@nextcloud/vue/components/NcButton'
import AccountBox from 'vue-material-design-icons/AccountBoxOutline.vue'
import LocalVideo from './LocalVideo.vue'
import VideoVue from './VideoVue.vue'

const isDirectionRTL = isRTL()

export default {
	name: 'PresenterOverlay',

	components: {
		AccountBox,
		VueDraggableResizable,
		NcButton,
		LocalVideo,
		VideoVue,
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

		isCollapsed: {
			type: Boolean,
			required: true,
		},

		isLocalPresenter: {
			type: Boolean,
			default: false,
		},

		localMediaModel: {
			type: Object,
			default: null,
		},
	},

	emits: ['click'],

	setup() {
		const parentWidth = ref(document.getElementById('videos').getBoundingClientRect().width)
		return {
			parentWidth,
			isDirectionRTL,
		}
	},

	data() {
		return {
			resizeObserver: null,
			presenterOverlaySize: 128,
			isDragging: false,
		}
	},

	mounted() {
		this.resizeObserver = new ResizeObserver(this.updateSize)
		this.resizeObserver.observe(this.$refs.presenterOverlay.$el.parentElement)
	},

	beforeUnmount() {
		if (this.resizeObserver) {
			this.resizeObserver.disconnect()
		}
	},

	methods: {
		t,
		updateSize() {
			// Size should be proportionate to the screen share size
			const newSize = Math.round(this.$refs.presenterOverlay.$el.parentElement.clientWidth * 0.1)
			this.presenterOverlaySize = Math.min(Math.max(newSize, 100), 242)
			// FIXME: inner method should be triggered to re-parent element
			this.$refs.presenterOverlay.checkParentSize()
			// FIXME: if it stays out of bounds (right and bottom), bring it back
			// FIXME: should consider RTL
			if (this.$refs.presenterOverlay.right < 0 && this.$refs.presenterOverlay.parentWidth > this.presenterOverlaySize) {
				this.$refs.presenterOverlay.moveHorizontally(this.$refs.presenterOverlay.parentWidth - this.presenterOverlaySize)
			}
			if (this.$refs.presenterOverlay.bottom < 0 && this.$refs.presenterOverlay.parentHeight > this.presenterOverlaySize) {
				this.$refs.presenterOverlay.moveVertically(this.$refs.presenterOverlay.parentHeight - this.presenterOverlaySize)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.presenter-overlay {
	position: absolute;
	top: 0;
	/* stylelint-disable-next-line csstools/use-logical */
	left: 0;
}

.presenter-overlay__video {
	position: relative;
	--max-size: 242px;
	--min-size: 100px;
	max-width: var(--max-size);
	max-height: var(--max-size);
	min-width: var(--min-size);
	min-height: var(--min-size);
	z-index: 10;
	aspect-ratio: 1;

	&:hover {
		cursor: grab;
	}

	&.dragging {
		cursor: grabbing;
	}
}

.presenter-overlay--collapsed {
	position: absolute !important;
	opacity: .7;
	bottom: calc(var(--default-clickable-area) + var(--default-grid-baseline));
	inset-inline-end: var(--grid-gap);

	#call-container:hover & {
		background-color: rgba(0, 0, 0, 0.1) !important;

		&:hover,
		&:focus {
			opacity: 1;
			background-color: rgba(0, 0, 0, 0.2) !important;
		}
	}
}

:deep(div) {
	// prevent default cursor
	cursor: inherit;
}
</style>
