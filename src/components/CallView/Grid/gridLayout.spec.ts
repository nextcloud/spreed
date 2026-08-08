/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, test } from 'vitest'
import {
	computeGridDimensions,
	computeRowDistribution,
	computeTilePlacements,
	getHalfColumnCount,
	getHalfColumnMinWidth,
	getMinTileHeight,
	getMinTileWidth,
	getTargetAspectRatio,
	GRID_GAP,
	TILE_COLUMN_SPAN,
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

	describe('computeRowDistribution', () => {
		test('spreads the tiles evenly across the rows', () => {
			expect(computeRowDistribution({ totalTiles: 7, rows: 3 })).toEqual([2, 3, 2])
			expect(computeRowDistribution({ totalTiles: 9, rows: 3 })).toEqual([3, 3, 3])
			expect(computeRowDistribution({ totalTiles: 13, rows: 4 })).toEqual([3, 4, 3, 3])
			expect(computeRowDistribution({ totalTiles: 14, rows: 4 })).toEqual([3, 4, 4, 3])
		})

		test('keeps a single row untouched', () => {
			expect(computeRowDistribution({ totalTiles: 4, rows: 1 })).toEqual([4])
		})

		test('never leaves a row empty', () => {
			expect(computeRowDistribution({ totalTiles: 2, rows: 4 })).toEqual([1, 1])
			expect(computeRowDistribution({ totalTiles: 0, rows: 3 })).toEqual([])
			expect(computeRowDistribution({ totalTiles: 3, rows: 0 })).toEqual([])
		})

		test('lays out every tile in rows of an even size', () => {
			for (let rows = 1; rows <= 6; rows++) {
				for (let totalTiles = 1; totalTiles <= 30; totalTiles++) {
					const distribution = computeRowDistribution({ totalTiles, rows })

					// Every tile is laid out, and no row is left empty
					expect(distribution.reduce((sum, size) => sum + size, 0)).toBe(totalTiles)
					expect(distribution.length).toBe(Math.min(rows, totalTiles))
					// The rows differ by at most one tile
					expect(Math.max(...distribution) - Math.min(...distribution)).toBeLessThanOrEqual(1)
					// The first row is the last one to receive an extra tile, so the
					// fuller rows sit below it
					if (totalTiles % distribution.length !== 0) {
						expect(distribution[0]).toBe(Math.min(...distribution))
					}
				}
			}
		})
	})

	describe('half column helpers', () => {
		test('splits every tile column in two', () => {
			expect(TILE_COLUMN_SPAN).toBe(2)
			expect(getHalfColumnCount(4)).toBe(8)
			expect(getHalfColumnCount(0)).toBe(0)
		})

		test('sizes a half column so that a tile spanning two keeps its minimum width', () => {
			// A tile spans two half columns and the gap between them
			expect(getHalfColumnMinWidth(320) * 2 + GRID_GAP).toBe(320)
		})

		test('never returns a negative half column width', () => {
			expect(getHalfColumnMinWidth(0)).toBe(0)
		})
	})

	describe('computeTilePlacements', () => {
		// A 4 columns grid, laid out as 8 half columns
		const grid = { columns: 4 }

		test('fills a complete grid from the first half column', () => {
			expect(computeTilePlacements({ ...grid, rows: 2, totalTiles: 8 })).toEqual([
				{ row: 1, column: 1 },
				{ row: 1, column: 3 },
				{ row: 1, column: 5 },
				{ row: 1, column: 7 },
				{ row: 2, column: 1 },
				{ row: 2, column: 3 },
				{ row: 2, column: 5 },
				{ row: 2, column: 7 },
			])
		})

		test('shifts a row by a half column when it leaves an odd number of columns empty', () => {
			// 7 tiles over 2 rows of a 4 columns grid: 3 and 4 tiles
			expect(computeTilePlacements({ ...grid, rows: 2, totalTiles: 7 })).toEqual([
				{ row: 1, column: 2 },
				{ row: 1, column: 4 },
				{ row: 1, column: 6 },
				{ row: 2, column: 1 },
				{ row: 2, column: 3 },
				{ row: 2, column: 5 },
				{ row: 2, column: 7 },
			])
		})

		test('centers a row leaving an even number of columns empty', () => {
			// 7 tiles over 3 rows of a 4 columns grid: 2, 3 and 2 tiles
			expect(computeTilePlacements({ ...grid, rows: 3, totalTiles: 7 })).toEqual([
				{ row: 1, column: 3 },
				{ row: 1, column: 5 },
				{ row: 2, column: 2 },
				{ row: 2, column: 4 },
				{ row: 2, column: 6 },
				{ row: 3, column: 3 },
				{ row: 3, column: 5 },
			])
		})

		test('falls back to the default placement when there is nothing to lay out', () => {
			expect(computeTilePlacements({ ...grid, rows: 2, totalTiles: 0 })).toEqual([])
			expect(computeTilePlacements({ ...grid, rows: 0, totalTiles: 4 })).toEqual([])
			expect(computeTilePlacements({ columns: 0, rows: 0, totalTiles: 4 })).toEqual([])
		})

		test('falls back to the default placement when the grid is too small', () => {
			expect(computeTilePlacements({ ...grid, rows: 2, totalTiles: 9 })).toEqual([])
		})

		test('keeps every row inside the grid and centered', () => {
			for (let columns = 1; columns <= 5; columns++) {
				const halfColumns = getHalfColumnCount(columns)

				for (let rows = 1; rows <= 5; rows++) {
					for (let totalTiles = 1; totalTiles <= columns * rows; totalTiles++) {
						const placements = computeTilePlacements({ columns, rows, totalTiles })
						expect(placements.length).toBe(totalTiles)

						const distribution = computeRowDistribution({ totalTiles, rows })
						for (const [index, rowSize] of distribution.entries()) {
							const row = placements.filter((placement) => placement.row === index + 1)
							expect(row.length).toBe(rowSize)

							// The tiles of the row are laid out one after the other, every
							// tile spanning TILE_COLUMN_SPAN half columns
							const columnsOfRow = row.map((placement) => placement.column)
							expect(columnsOfRow).toEqual(Array.from({ length: rowSize }, (_, i) => columnsOfRow[0] + i * TILE_COLUMN_SPAN))

							// The row stays within the half columns of the grid
							const firstLine = columnsOfRow[0]
							const lastLine = columnsOfRow[rowSize - 1] + TILE_COLUMN_SPAN
							expect(firstLine).toBeGreaterThanOrEqual(1)
							expect(lastLine).toBeLessThanOrEqual(halfColumns + 1)

							// The half columns left empty are split evenly on both sides
							expect(firstLine - 1).toBe(halfColumns + 1 - lastLine)
						}
					}
				}
			}
		})
	})
})
