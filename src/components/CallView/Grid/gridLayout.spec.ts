/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, test } from 'vitest'
import {
	computeGridDimensions,
	getMinTileHeight,
	getMinTileWidth,
	getTargetAspectRatio,
} from './gridLayout.ts'

/**
 * Number of usable tile slots for a given layout, mirroring the production
 * helper (the full grid reserves one slot for the local video).
 *
 * @param columns - number of columns
 * @param rows - number of rows
 * @param isStripe - whether the grid is shown as a stripe
 */
function slotsFor(columns: number, rows: number, isStripe: boolean): number {
	return isStripe ? columns * rows : columns * rows - 1
}

describe('gridLayout', () => {
	describe('tile size helpers', () => {
		test('uses the larger tile sizes for the full grid', () => {
			expect(getMinTileWidth(false)).toBe(320)
			expect(getMinTileHeight(false)).toBe(240)
		})

		test('uses the compact tile sizes for stripe/sidebar', () => {
			expect(getMinTileWidth(true)).toBe(200)
			expect(getMinTileHeight(true)).toBe(150)
		})

		test('targets a wider aspect ratio for the full grid than the stripe', () => {
			expect(getTargetAspectRatio(false)).toBe(1.5)
			expect(getTargetAspectRatio(true)).toBe(1)
		})
	})

	describe('computeGridDimensions', () => {
		// Common full-grid layout on a 1920x1080 viewport
		const fullGrid = {
			gridWidth: 1920,
			gridHeight: 1080,
			targetAspectRatio: 1.5,
			minWidth: 320,
			minHeight: 240,
			noLocalVideoReserve: false,
		}

		test('returns no columns or rows when there are no tiles', () => {
			expect(computeGridDimensions({ ...fullGrid, videoCount: 0 }))
				.toEqual({ columns: 0, rows: 0 })
		})

		test('keeps at least one column and one row when the grid has not been measured yet', () => {
			// Columns and rows are floored at 1 while tiles are present, so the
			// slot count derived from them never goes negative on a transient
			// zero-size measurement (hidden or mid-transition element).
			for (const zeroSize of [{ gridWidth: 0 }, { gridHeight: 0 }, { gridWidth: 0, gridHeight: 0 }]) {
				const { columns, rows } = computeGridDimensions({ ...fullGrid, ...zeroSize, videoCount: 4 })
				expect(columns).toBeGreaterThanOrEqual(1)
				expect(rows).toBeGreaterThanOrEqual(1)
			}
		})

		test('fills the maximum grid when tiles populate every slot', () => {
			// 1920x1080 fits 6 columns and 4 rows (23 slots once the local video is reserved)
			expect(computeGridDimensions({ ...fullGrid, videoCount: 23 }))
				.toEqual({ columns: 6, rows: 4 })
		})

		test('does not grow beyond the maximum grid when tiles overflow', () => {
			// Overflowing tiles are paginated, the layout stays at its maximum
			expect(computeGridDimensions({ ...fullGrid, videoCount: 100 }))
				.toEqual({ columns: 6, rows: 4 })
		})

		test('shrinks the grid to fit a small number of tiles', () => {
			// Two tiles plus the reserved local-video slot fit best in a 2x2 grid
			expect(computeGridDimensions({ ...fullGrid, videoCount: 2 }))
				.toEqual({ columns: 2, rows: 2 })
		})

		test('keeps a single row for the stripe and never reserves a local slot', () => {
			const result = computeGridDimensions({
				gridWidth: 1000,
				gridHeight: 150,
				videoCount: 10,
				targetAspectRatio: 1,
				minWidth: 200,
				minHeight: 150,
				noLocalVideoReserve: true,
			})
			expect(result).toEqual({ columns: 5, rows: 1 })
		})

		test('applies hysteresis on the current column count to avoid flickering', () => {
			// 976px fits exactly 3 columns of 320px with two 8px gaps. Whether a
			// fourth column is offered depends on the current column count.
			const base = {
				gridWidth: 976,
				gridHeight: 240,
				videoCount: 100,
				targetAspectRatio: 1.5,
				minWidth: 320,
				minHeight: 240,
				noLocalVideoReserve: false,
			}
			expect(computeGridDimensions({ ...base, currentColumns: 3, currentRows: 1 }))
				.toEqual({ columns: 3, rows: 1 })
			expect(computeGridDimensions({ ...base, currentColumns: 4, currentRows: 1 }))
				.toEqual({ columns: 2, rows: 1 })
		})

		test('fits every tile that the grid can hold and never grows past its maximum', () => {
			const widths = [400, 800, 1280, 1920, 2560]
			const heights = [300, 600, 1080, 1440]
			const counts = [1, 2, 3, 5, 8, 12, 20]

			for (const gridWidth of widths) {
				for (const gridHeight of heights) {
					for (const isStripe of [false, true]) {
						const layout = {
							gridWidth,
							gridHeight,
							targetAspectRatio: getTargetAspectRatio(isStripe),
							minWidth: getMinTileWidth(isStripe),
							minHeight: getMinTileHeight(isStripe),
							noLocalVideoReserve: isStripe,
						}
						// The maximum grid is what we get when there is no overflow to shrink away
						const max = computeGridDimensions({ ...layout, videoCount: 10000 })
						const maxSlots = slotsFor(max.columns, max.rows, isStripe)

						for (const videoCount of counts) {
							const { columns, rows } = computeGridDimensions({ ...layout, videoCount })

							expect(columns).toBeGreaterThanOrEqual(1)
							expect(rows).toBeGreaterThanOrEqual(1)
							// Never larger than the maximum grid for this viewport
							expect(columns).toBeLessThanOrEqual(max.columns)
							expect(rows).toBeLessThanOrEqual(max.rows)
							// Every tile that the grid can hold is given a slot
							if (videoCount <= maxSlots) {
								expect(slotsFor(columns, rows, isStripe)).toBeGreaterThanOrEqual(videoCount)
							} else {
								// Overflowing tiles are paginated on the maximum grid
								expect({ columns, rows }).toEqual(max)
							}
						}
					}
				}
			}
		})
	})
})
