<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div ref="presenterOverlayContainer" class="presenter-overlay__container">
		<div
			v-if="!isCollapsed"
			ref="presenterOverlay"
			class="presenter-overlay"
			:style="draggableStyle">
			<LocalVideo
				v-if="isLocalPresenter"
				class="presenter-overlay__video"
				:token="token"
				:localMediaModel="localMediaModel"
				:localCallParticipantModel="model"
				isPresenterOverlay
				unSelectable
				hideBottomBar
				@clickPresenter="$emit('click')" />
			<VideoVue
				v-else
				:token="token"
				:class="{ dragging: isDragging }"
				class="presenter-overlay__video"
				:model="model"
				:sharedData="sharedData"
				isPresenterOverlay
				unSelectable
				hideBottomBar
				@clickPresenter="$emit('click')" />
		</div>

		<!-- presenter button when presenter overlay is collapsed -->
		<NcButton
			v-else
			:aria-label="t('spreed', 'Show presenter')"
			:title="t('spreed', 'Show presenter')"
			class="presenter-overlay--collapsed"
			variant="tertiary-no-background"
			@click="$emit('click')">
			<template #icon>
				<AccountBox fillColor="#ffffff" :size="20" />
			</template>
		</NcButton>
	</div>
</template>

<script>

import { isRTL, t } from '@nextcloud/l10n'
import { ref, useTemplateRef } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import AccountBox from 'vue-material-design-icons/AccountBoxOutline.vue'
import LocalVideo from './LocalVideo.vue'
import VideoVue from './VideoVue.vue'
import { useDraggableBounded } from '../../../composables/useDraggableBounded.ts'

export default {
	name: 'PresenterOverlay',

	components: {
		AccountBox,
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
		const presenterOverlayContainer = useTemplateRef('presenterOverlayContainer')
		const presenterOverlay = useTemplateRef('presenterOverlay')

		// Initial position estimate; actual size is set by CSS clamp(100px, 10cqw, 242px)
		const initialX = isRTL() ? parentWidth.value - 128 - 10 : 10
		const { isDragging, style: draggableStyle } = useDraggableBounded(presenterOverlay, presenterOverlayContainer, {
			initialValue: { x: initialX, y: 10 },
		})

		return {
			presenterOverlayContainer,
			presenterOverlay,
			isDragging,
			draggableStyle,
		}
	},

	methods: {
		t,
	},
}
</script>

<style lang="scss" scoped>
.presenter-overlay__container {
	position: absolute;
	inset: 0;
	// Make container size to be computed in isolation, to use container units
	container-type: inline-size;

	// Make container transparent to user events
	pointer-events: none;

	& > * {
		pointer-events: auto;
	}
}

.presenter-overlay {
	position: absolute;
	width: clamp(100px, 10cqw, 242px);
	height: clamp(100px, 10cqw, 242px);
}

.presenter-overlay__video {
	position: relative;
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
