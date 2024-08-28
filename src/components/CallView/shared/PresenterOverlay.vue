<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<VueDraggableResizable v-if="!isCollapsed"
		:key="presenterOverlaySize"
		parent
		:resizable="false"
		:h="presenterOverlaySize"
		:w="presenterOverlaySize"
		:x="10"
		:y="10"
		@dragging="isDragging = true"
		@dragstop="isDragging = false">
		<LocalVideo v-if="isLocalPresenter"
			class="presenter-overlay__video"
			:token="token"
			:local-media-model="localMediaModel"
			:local-call-participant-model="model"
			is-presenter-overlay
			un-selectable
			hide-bottom-bar
			@click-presenter="$emit('click')" />
		<VideoVue v-else
			:token="token"
			:class="{ 'dragging': isDragging }"
			class="presenter-overlay__video"
			:model="model"
			:shared-data="sharedData"
			is-presenter-overlay
			un-selectable
			hide-bottom-bar
			@click-presenter="$emit('click')" />
	</VueDraggableResizable>

	<!-- presenter button when presenter overlay is collapsed -->
	<NcButton v-else
		:aria-label="t('spreed', 'Show presenter')"
		:title="t('spreed', 'Show presenter')"
		class="presenter-overlay--collapsed"
		type="tertiary-no-background"
		@click="$emit('click')">
		<template #icon>
			<AccountBox fill-color="#ffffff" :size="20" />
		</template>
	</NcButton>
</template>

<script>

import VueDraggableResizable from 'vue-draggable-resizable'

import AccountBox from 'vue-material-design-icons/AccountBoxOutline.vue'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import LocalVideo from './LocalVideo.vue'
import VideoVue from './VideoVue.vue'

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

	data() {
		return {
			presenterOverlaySize: 128,
			isDragging: false,
		}
	},

	mounted() {
		window.addEventListener('resize', this.updateSize)
	},

	beforeDestroy() {
		window.removeEventListener('resize', this.updateSize)
	},

	methods: {
		t,
		updateSize() {
			this.presenterOverlaySize = Math.min(Math.max(window.innerWidth * 0.1, 100), 242)
		},
	},
}
</script>

<style lang="scss" scoped>
.presenter-overlay__video {
	position: relative;
	--max-size: 242px;
	--min-size: 100px;
	width: 10vw;
	height: 10vw;
	max-width: var(--max-size);
	max-height: var(--max-size);
	min-width: var(--min-size);
	min-height: var(--min-size);
	z-index: 10;

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
	bottom: 48px;
	right: 0;

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
