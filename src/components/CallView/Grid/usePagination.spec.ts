/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, test } from 'vitest'
import { computed, nextTick, ref } from 'vue'
import { usePagination } from './usePagination.ts'

/**
 * Create a pagination over `count` videos with `slots` videos per page.
 *
 * The composable only works with counts and index bounds, so `displayedVideos`
 * is derived here the same way the caller does, by slicing the list.
 *
 * @param count - number of videos in the list
 * @param slots - number of videos per page
 */
function createPagination(count: number, slots: number) {
	const videos = ref(Array.from(Array(count).keys()))
	const slotsRef = ref(slots)
	const pagination = usePagination(computed(() => videos.value.length), slotsRef)
	const displayedVideos = computed(() => videos.value.slice(...pagination.currentPageBounds.value))
	return { videos, slots: slotsRef, displayedVideos, ...pagination }
}

describe('usePagination', () => {
	describe('page computation', () => {
		test('shows all videos on a single page when they fit', () => {
			const { displayedVideos, numberOfPages, hasNextPage, hasPreviousPage } = createPagination(4, 6)

			expect(displayedVideos.value).toEqual([0, 1, 2, 3])
			expect(numberOfPages.value).toBe(1)
			expect(hasNextPage.value).toBe(false)
			expect(hasPreviousPage.value).toBe(false)
		})

		test('splits the videos into pages of the given size', () => {
			const { displayedVideos, numberOfPages, hasNextPage } = createPagination(7, 3)

			expect(numberOfPages.value).toBe(3)
			expect(displayedVideos.value).toEqual([0, 1, 2])
			expect(hasNextPage.value).toBe(true)
		})

		test('shows nothing while the layout is not known yet (0 slots)', () => {
			const { displayedVideos, numberOfPages, hasNextPage, hasPreviousPage } = createPagination(7, 0)

			expect(displayedVideos.value).toEqual([])
			expect(numberOfPages.value).toBe(0)
			expect(hasNextPage.value).toBe(false)
			expect(hasPreviousPage.value).toBe(false)
		})

		test('handles an empty list of videos', () => {
			const { displayedVideos, numberOfPages, hasNextPage, hasPreviousPage } = createPagination(0, 6)

			expect(displayedVideos.value).toEqual([])
			expect(numberOfPages.value).toBe(0)
			expect(hasNextPage.value).toBe(false)
			expect(hasPreviousPage.value).toBe(false)
		})
	})

	describe('navigation', () => {
		test('navigates forward through the pages', () => {
			const pagination = createPagination(7, 3)

			pagination.next()
			expect(pagination.currentPage.value).toBe(1)
			expect(pagination.displayedVideos.value).toEqual([3, 4, 5])
			expect(pagination.hasPreviousPage.value).toBe(true)

			pagination.next()
			expect(pagination.currentPage.value).toBe(2)
			expect(pagination.displayedVideos.value).toEqual([6])
			expect(pagination.hasNextPage.value).toBe(false)
		})

		test('navigates backward through the pages', () => {
			const pagination = createPagination(7, 3)

			pagination.next()
			pagination.previous()

			expect(pagination.currentPage.value).toBe(0)
			expect(pagination.displayedVideos.value).toEqual([0, 1, 2])
			expect(pagination.hasPreviousPage.value).toBe(false)
		})

		test('does not navigate past the last page', () => {
			const pagination = createPagination(7, 3)

			pagination.next()
			pagination.next()
			pagination.next()

			expect(pagination.currentPage.value).toBe(2)
			expect(pagination.displayedVideos.value).toEqual([6])
		})

		test('does not navigate before the first page', () => {
			const pagination = createPagination(7, 3)

			pagination.previous()

			expect(pagination.currentPage.value).toBe(0)
			expect(pagination.displayedVideos.value).toEqual([0, 1, 2])
		})
	})

	describe('reactivity to input changes', () => {
		test('clamps the current page when videos leave', async () => {
			const pagination = createPagination(7, 3)

			pagination.next()
			pagination.next()
			expect(pagination.currentPage.value).toBe(2)

			// Only one page left
			pagination.videos.value = pagination.videos.value.slice(0, 3)
			await nextTick()

			expect(pagination.currentPage.value).toBe(0)
			expect(pagination.displayedVideos.value).toEqual([0, 1, 2])
		})

		test('clamps the current page when the page grows on resize', async () => {
			const pagination = createPagination(7, 3)

			pagination.next()
			pagination.next()
			expect(pagination.currentPage.value).toBe(2)

			// All videos fit on one page now
			pagination.slots.value = 7
			await nextTick()

			expect(pagination.currentPage.value).toBe(0)
			expect(pagination.displayedVideos.value).toEqual([0, 1, 2, 3, 4, 5, 6])
		})

		test('clamps the current page when all videos leave', async () => {
			const pagination = createPagination(7, 3)

			pagination.next()
			pagination.videos.value = []
			await nextTick()

			expect(pagination.currentPage.value).toBe(0)
			expect(pagination.displayedVideos.value).toEqual([])
			expect(pagination.hasPreviousPage.value).toBe(false)
		})

		test('shows the new page when videos join', () => {
			const pagination = createPagination(3, 3)
			expect(pagination.hasNextPage.value).toBe(false)

			pagination.videos.value = [...pagination.videos.value, 3]

			expect(pagination.numberOfPages.value).toBe(2)
			expect(pagination.hasNextPage.value).toBe(true)
		})
	})
})
