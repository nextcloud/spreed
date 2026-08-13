<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed, ref, useTemplateRef } from 'vue'
import VideoVue from '../shared/VideoVue.vue'
import {
	computeTilePlacements,
	getHalfColumnCount,
	getHalfColumnMinWidth,
	TILE_COLUMN_SPAN,
} from './gridLayout.ts'
import { useGridDimensions } from './useGridDimensions.ts'

const props = defineProps<{
	/** Token of the conversation the call belongs to */
	token: string
	/** The promoted participant models, in the order they became active */
	models: { attributes: { peerId: string, nextcloudSessionId: string } }[]
	/** Per participant shared state, keyed by peer id */
	sharedDatas: Record<string, object>
	/** Whether the video overlay is currently shown */
	showVideoOverlay?: boolean
	/** Whether the call is a one to one conversation */
	isOneToOne?: boolean
}>()

const emit = defineEmits<{
	selectVideo: [peerId: string]
}>()

const wrapper = useTemplateRef('wrapper')
const grid = useTemplateRef('grid')

const videoCount = computed(() => props.models.length)

// This grid only ever holds promoted speakers, the local video lives in the
// stripe below it.
const noLocalVideoReserve = ref(true)

const {
	columns,
	rows,
	dpiAwareMinWidth,
	dpiAwareMinHeight,
} = useGridDimensions({
	wrapper,
	grid,
	isStripe: ref(false),
	isSidebar: ref(false),
	noLocalVideoReserve,
	videoCount,
	stripeOpen: ref(false),
})

// Placement of every tile, so that a row leaving an odd number of columns
// empty is centered rather than left-aligned (three speakers give one
// centered tile below two).
const tilePlacements = computed(() => computeTilePlacements({
	totalTiles: videoCount.value,
	rows: rows.value,
	columns: columns.value,
}))

const gridStyle = computed(() => ({
	gridTemplateColumns: `repeat(${getHalfColumnCount(columns.value)}, minmax(${getHalfColumnMinWidth(dpiAwareMinWidth.value)}px, 1fr))`,
	gridTemplateRows: `repeat(${rows.value}, minmax(${dpiAwareMinHeight.value}px, 1fr))`,
}))

/**
 * Explicit placement of a tile in the grid. Columns are counted in half
 * columns, which the grid flips on its own in RTL layouts.
 *
 * @param index - rendering position of the tile
 */
function tileStyle(index: number) {
	const placement = tilePlacements.value[index]
	if (!placement) {
		return undefined
	}

	return {
		gridRow: placement.row,
		gridColumn: `${placement.column} / span ${TILE_COLUMN_SPAN}`,
	}
}
</script>

<template>
	<div ref="wrapper" class="speakers-grid-wrapper">
		<div ref="grid" class="speakers-grid" :style="gridStyle">
			<VideoVue
				v-for="(model, index) in models"
				:key="model.attributes.peerId"
				class="speakers-grid__tile"
				:style="tileStyle(index)"
				:data-tile-session-id="model.attributes.nextcloudSessionId"
				:token="token"
				:model="model"
				:sharedData="sharedDatas[model.attributes.peerId]"
				:showVideoOverlay="showVideoOverlay"
				:isOneToOne="isOneToOne"
				isGrid
				fitVideo
				@clickVideo="emit('selectVideo', model.attributes.peerId)" />
		</div>
	</div>
</template>

<style lang="scss" scoped>
.speakers-grid-wrapper {
	width: 100%;
	height: 100%;
	min-width: 0;
}

.speakers-grid {
	display: grid;
	width: 100%;
	height: 100%;

	row-gap: var(--grid-gap);
	column-gap: var(--grid-gap);

	// The grid is laid out in half columns, so a tile takes two of them by
	// default. Explicitly placed tiles set their own start line but keep that
	// span (see `TILE_COLUMN_SPAN`).
	> * {
		grid-column: span 2;
	}
}
</style>
