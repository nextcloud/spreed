/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Ref } from 'vue'

import debounce from 'debounce'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { computeGridDimensions, getMinTileHeight, getMinTileWidth, getTargetAspectRatio } from './gridLayout.ts'

type UseGridDimensionsOptions = {
	/** Element to observe for size changes (its child grid is measured) */
	wrapper: Ref<HTMLElement | null>
	/** Grid element whose client size drives the layout */
	grid: Ref<HTMLElement | null>
	/** Whether the grid is shown as a stripe */
	isStripe: Readonly<Ref<boolean>>
	/** Whether the grid is shown inside the sidebar */
	isSidebar: Readonly<Ref<boolean>>
	/** Whether the call is being recorded (the local video is then not shown) */
	isRecording: Readonly<Ref<boolean>>
	/** Number of tiles to lay out (already clamped to any cap) */
	videoCount: Readonly<Ref<number>>
	/** Whether the stripe is expanded (only relevant in stripe mode) */
	stripeOpen: Readonly<Ref<boolean>>
}

/**
 * Owns the measurement of the grid element and derives the number of columns and
 * rows the grid should use. The heavy lifting is done by the pure
 * {@link computeGridDimensions}; this composable only wires it to the DOM via a
 * `ResizeObserver` and the relevant reactive inputs.
 *
 * @param options - the reactive layout inputs and template refs
 * @param options.wrapper - element to observe for size changes
 * @param options.grid - grid element whose client size drives the layout
 * @param options.isStripe - whether the grid is shown as a stripe
 * @param options.isSidebar - whether the grid is shown inside the sidebar
 * @param options.isRecording - whether the call is being recorded
 * @param options.videoCount - number of tiles to lay out (already clamped to any cap)
 * @param options.stripeOpen - whether the stripe is expanded
 */
export function useGridDimensions({
	wrapper,
	grid,
	isStripe,
	isSidebar,
	isRecording,
	videoCount,
	stripeOpen,
}: UseGridDimensionsOptions) {
	const gridWidth = ref(0)
	const gridHeight = ref(0)
	const columns = ref(0)
	const rows = ref(0)

	const dpiFactor = computed(() => {
		if (isStripe.value) {
			// On the stripe we only ever want 1 row, so we ignore the DPR
			// as the height of the grid is the height of the video elements then.
			return 1.0
		}

		const devicePixelRatio = window.devicePixelRatio

		// Some sanity check to not screw up the math.
		if (devicePixelRatio < 0.5) {
			return 0.5
		}
		if (devicePixelRatio > 2.0) {
			return 2.0
		}
		return devicePixelRatio
	})

	const compact = computed(() => isStripe.value || isSidebar.value)
	const minWidth = computed(() => getMinTileWidth(compact.value))
	const minHeight = computed(() => getMinTileHeight(compact.value))
	const dpiAwareMinWidth = computed(() => minWidth.value / dpiFactor.value)
	const dpiAwareMinHeight = computed(() => minHeight.value / dpiFactor.value)
	const targetAspectRatio = computed(() => getTargetAspectRatio(isStripe.value))
	const gridAspectRatio = computed(() => (gridWidth.value / gridHeight.value).toPrecision(2))

	// The full grid reserves one slot for the local video, unless it is not shown
	// (stripe or recording mode).
	const noLocalVideoReserve = computed(() => isStripe.value || isRecording.value)

	/**
	 * Measure the grid element and recompute the number of columns and rows.
	 */
	function recompute() {
		const element = grid.value
		if (!element) {
			return
		}

		gridWidth.value = element.clientWidth
		gridHeight.value = element.clientHeight

		const dimensions = computeGridDimensions({
			gridWidth: gridWidth.value,
			gridHeight: gridHeight.value,
			videoCount: videoCount.value,
			targetAspectRatio: targetAspectRatio.value,
			minWidth: dpiAwareMinWidth.value,
			minHeight: dpiAwareMinHeight.value,
			noLocalVideoReserve: noLocalVideoReserve.value,
			// Seed the algorithm with the current layout to keep the
			// anti-flickering hysteresis behaviour.
			currentColumns: columns.value,
			currentRows: rows.value,
		})

		columns.value = dimensions.columns
		rows.value = dimensions.rows
	}

	const debouncedRecompute = debounce(recompute, 200)
	let resizeObserver: ResizeObserver | null = null

	onMounted(() => {
		if (wrapper.value) {
			resizeObserver = new ResizeObserver(debouncedRecompute)
			resizeObserver.observe(wrapper.value)
		}
		recompute()
	})

	onBeforeUnmount(() => {
		debouncedRecompute.clear?.()
		resizeObserver?.disconnect()
	})

	// The number of tiles changed: the available size is unchanged, recompute now.
	watch(videoCount, recompute)

	// Switching mode, (un)collapsing the stripe or toggling recording changes the
	// element visibility and size (and whether a local-video slot is reserved), so
	// recompute on the next tick once the DOM has settled. When the grid is hidden
	// the element is unmounted and `recompute` is a no-op.
	watch([isStripe, stripeOpen, isRecording], () => nextTick(recompute))

	return {
		gridWidth,
		gridHeight,
		columns,
		rows,
		dpiFactor,
		minWidth,
		minHeight,
		dpiAwareMinWidth,
		dpiAwareMinHeight,
		targetAspectRatio,
		gridAspectRatio,
		/** Force a re-measure and recompute (e.g. for debugging) */
		recompute,
	}
}
