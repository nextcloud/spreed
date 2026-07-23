/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// Align with var(--grid-gap) in CallView
export const GRID_GAP = 8

// Minimum size of a video tile, in px. The "compact" variants are used when the
// grid is shown as a stripe or inside the sidebar.
export const MIN_TILE_WIDTH = 320
export const MIN_TILE_HEIGHT = 240
export const MIN_TILE_WIDTH_COMPACT = 200
export const MIN_TILE_HEIGHT_COMPACT = 150

// Aspect ratio (width / height) the layout tries to reach for each tile.
export const TARGET_ASPECT_RATIO = 1.5
export const TARGET_ASPECT_RATIO_STRIPE = 1

/**
 * Minimum tile width for the given layout mode.
 *
 * @param compact - whether the grid is shown as a stripe or in the sidebar
 */
export function getMinTileWidth(compact: boolean): number {
	return compact ? MIN_TILE_WIDTH_COMPACT : MIN_TILE_WIDTH
}

/**
 * Minimum tile height for the given layout mode.
 *
 * @param compact - whether the grid is shown as a stripe or in the sidebar
 */
export function getMinTileHeight(compact: boolean): number {
	return compact ? MIN_TILE_HEIGHT_COMPACT : MIN_TILE_HEIGHT
}

/**
 * Target tile aspect ratio for the given layout mode.
 *
 * @param isStripe - whether the grid is shown as a stripe
 */
export function getTargetAspectRatio(isStripe: boolean): number {
	return isStripe ? TARGET_ASPECT_RATIO_STRIPE : TARGET_ASPECT_RATIO
}

type GridDimensionsOptions = {
	/** Available grid width in px */
	gridWidth: number
	/** Available grid height in px */
	gridHeight: number
	/** Number of tiles to lay out (already clamped to any cap) */
	videoCount: number
	/** Target tile aspect ratio (width / height) */
	targetAspectRatio: number
	/** Minimum tile width in px (already DPI-aware) */
	minWidth: number
	/** Minimum tile height in px (already DPI-aware) */
	minHeight: number
	/** Whether no slot must be reserved for the local video (stripe or recording mode) */
	noLocalVideoReserve: boolean
	/** Current number of columns, used as a hysteresis seed to avoid flickering */
	currentColumns?: number
	/** Current number of rows, used as a hysteresis seed to avoid flickering */
	currentRows?: number
}

/**
 * Number of tile slots available for a given column/row count.
 * The full grid reserves one slot for the local video, unless it is not shown
 * (stripe or recording mode).
 *
 * @param columns - number of columns
 * @param rows - number of rows
 * @param noLocalVideoReserve - whether no slot must be reserved for the local video
 */
function slotsFor(columns: number, rows: number, noLocalVideoReserve: boolean): number {
	return noLocalVideoReserve ? columns * rows : columns * rows - 1
}

/**
 * Maximum number of tiles that fit along one axis (columns or rows) given the
 * available size along that axis.
 *
 * A small hysteresis based on the current tile count avoids flickering when
 * resizing within `GRID_GAP` px of the threshold for the current amount of
 * tiles.
 *
 * @param size - available grid size in px along the axis
 * @param minSize - minimum tile size in px along the axis
 * @param currentCount - current number of tiles along the axis
 */
function computeAxisMax(size: number, minSize: number, currentCount: number): number {
	// Max amount of tiles that fits on screen, including gaps for the current layout
	const approxMax = Math.floor((size - GRID_GAP * (currentCount - 1)) / minSize)
	// Max amount of tiles that fits on screen if we tried to fit one more tile
	const hypotheticalMax = Math.floor((size - GRID_GAP * currentCount) / minSize)
	// If we are about to change the tile count, check whether one more tile could fit.
	// This helps to avoid flickering when resizing within GRID_GAP px of the
	// minimal size for the current amount of tiles.
	const axisMax = approxMax === currentCount ? approxMax : hypotheticalMax
	// Return at least 1 tile
	return Math.max(axisMax, 1)
}

/**
 * Compute the number of columns and rows the grid should use.
 *
 * The algorithm starts from the maximum number of columns and rows that fit the
 * available space, then shrinks the grid - removing whichever column or row
 * keeps the tiles closest to the target aspect ratio - until it is just big
 * enough to hold every tile. This is a pure function of its inputs, so it can be
 * unit-tested in isolation from the component and its `ResizeObserver`.
 *
 * @param options - the layout inputs
 * @param options.gridWidth - available grid width in px
 * @param options.gridHeight - available grid height in px
 * @param options.videoCount - number of tiles to lay out (already clamped to any cap)
 * @param options.targetAspectRatio - target tile aspect ratio (width / height)
 * @param options.minWidth - minimum tile width in px (already DPI-aware)
 * @param options.minHeight - minimum tile height in px (already DPI-aware)
 * @param options.noLocalVideoReserve - whether no slot must be reserved for the local video
 * @param options.currentColumns - current number of columns, used as a hysteresis seed
 * @param options.currentRows - current number of rows, used as a hysteresis seed
 * @return the resulting number of columns and rows
 */
export function computeGridDimensions({
	gridWidth,
	gridHeight,
	videoCount,
	targetAspectRatio,
	minWidth,
	minHeight,
	noLocalVideoReserve,
	currentColumns = 0,
	currentRows = 0,
}: GridDimensionsOptions): { columns: number, rows: number } {
	// TODO: rebuild the grid to have optimal for last page:
	// Exception for when navigating in and away from the last page of the grid
	// The last grid page is very likely not to have the same number of elements
	// as the previous pages so the grid needs to be tweaked accordingly

	// Nothing to lay out. Note that a zero-size grid (not measured yet, hidden
	// or mid-transition) still falls back to a 1x1 layout below while tiles are
	// present, so the downstream slot math never goes negative.
	if (videoCount <= 0) {
		return { columns: 0, rows: 0 }
	}

	// Start from the largest grid that fits the available space, then shrink it
	// to fit the number of tiles we actually have.
	let columns = computeAxisMax(gridWidth, minWidth, currentColumns)
	let rows = computeAxisMax(gridHeight, minHeight, currentRows)

	// No need to shrink more if 1 row and 1 column
	if (rows === 1 && columns === 1) {
		return { columns, rows }
	}

	let currentSlots = slotsFor(columns, rows, noLocalVideoReserve)

	// Only shrink when we have an 'overflow' of slots. If the tiles already
	// populate the grid, there is no point in shrinking it.
	while (videoCount < currentSlots) {
		const previousColumns = columns
		const previousRows = rows

		// Current tile dimensions
		const videoWidth = (gridWidth - GRID_GAP * (columns - 1)) / columns
		const videoHeight = (gridHeight - GRID_GAP * (rows - 1)) / rows

		// Hypothetical width/height with one column/row less than current
		const videoWidthWithOneColumnLess = (gridWidth - GRID_GAP * (columns - 2)) / (columns - 1)
		const videoHeightWithOneRowLess = (gridHeight - GRID_GAP * (rows - 2)) / (rows - 1)

		// Hypothetical aspect ratio with one column/row less than current
		const aspectRatioWithOneColumnLess = videoWidthWithOneColumnLess / videoHeight
		const aspectRatioWithOneRowLess = videoWidth / videoHeightWithOneRowLess

		// Deltas with target aspect ratio
		const deltaAspectRatioWithOneColumnLess = Math.abs(aspectRatioWithOneColumnLess - targetAspectRatio)
		const deltaAspectRatioWithOneRowLess = Math.abs(aspectRatioWithOneRowLess - targetAspectRatio)

		// Compare the deltas to find out whether we need to remove a column or a row
		if (deltaAspectRatioWithOneColumnLess <= deltaAspectRatioWithOneRowLess) {
			if (columns >= 2) {
				columns--
			}

			currentSlots = slotsFor(columns, rows, noLocalVideoReserve)

			// Check that there are still enough slots available
			if (videoCount > currentSlots) {
				// If not, revert the change and stop shrinking
				columns++
				break
			}
		} else {
			if (rows >= 2) {
				rows--
			}

			currentSlots = slotsFor(columns, rows, noLocalVideoReserve)

			// Check that there are still enough slots available
			if (videoCount > currentSlots) {
				// If not, revert the change and stop shrinking
				rows++
				break
			}
		}

		if (previousColumns === columns && previousRows === rows) {
			break
		}
	}

	return { columns, rows }
}
