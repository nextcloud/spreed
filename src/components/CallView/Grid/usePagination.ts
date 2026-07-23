/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Ref } from 'vue'

import { computed, ref, watch } from 'vue'

/**
 * Paginates a list of `videosCount` videos into pages of `slots` items each.
 *
 * The composable only deals with counts and indexes; slicing the actual list
 * is left to the caller, which slices it with `currentPageBounds`.
 *
 * The current page is clamped whenever the number of pages shrinks (e.g. on
 * resize or when participants leave), so the pagination never points past the
 * end of the list.
 *
 * @param videosCount - the number of videos to paginate
 * @param slots - number of videos per page (`0` while the grid layout is not
 *   yet known, in which case nothing is displayed)
 */
export function usePagination(videosCount: Readonly<Ref<number>>, slots: Readonly<Ref<number>>) {
	const currentPage = ref(0)

	const numberOfPages = computed(() => {
		return slots.value > 0 ? Math.ceil(videosCount.value / slots.value) : 0
	})

	// The `[start, end)` index range of the videos shown on the current page,
	// ready to be spread into `Array.prototype.slice`.
	const currentPageBounds = computed<[number, number]>(() => {
		if (slots.value <= 0) {
			return [0, 0]
		}

		const start = currentPage.value * slots.value
		return [start, start + slots.value]
	})

	const hasNextPage = computed(() => currentPage.value < numberOfPages.value - 1)

	const hasPreviousPage = computed(() => currentPage.value > 0)

	/**
	 * Advance to the next page, if there is one.
	 */
	function next() {
		if (hasNextPage.value) {
			currentPage.value++
		}
	}

	/**
	 * Go back to the previous page, if there is one.
	 */
	function previous() {
		if (hasPreviousPage.value) {
			currentPage.value--
		}
	}

	// Keep the current page within bounds when the page count shrinks
	watch(numberOfPages, () => {
		if (currentPage.value >= numberOfPages.value) {
			currentPage.value = Math.max(0, numberOfPages.value - 1)
		}
	})

	return {
		currentPage,
		numberOfPages,
		currentPageBounds,
		hasNextPage,
		hasPreviousPage,
		next,
		previous,
	}
}
