/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Ref } from 'vue'

import { computed, ref, watch } from 'vue'

/**
 * Paginates a reactive list of videos into pages of `slots` items each.
 *
 * The current page is clamped whenever the number of pages shrinks (e.g. on
 * resize or when participants leave), so the pagination never points past the
 * end of the list.
 *
 * @param videos - the ordered list of videos to paginate
 * @param slots - number of videos per page (`0` while the grid layout is not
 *   yet known, in which case nothing is displayed)
 */
export function usePagination<T>(videos: Readonly<Ref<readonly T[]>>, slots: Readonly<Ref<number>>) {
	const currentPage = ref(0)

	const numberOfPages = computed(() => {
		return slots.value > 0 ? Math.ceil(videos.value.length / slots.value) : 0
	})

	// The window of the videos array shown on the current page
	const displayedVideos = computed(() => {
		if (slots.value <= 0) {
			return []
		}

		return videos.value.slice(currentPage.value * slots.value, (currentPage.value + 1) * slots.value)
	})

	const hasNextPage = computed(() => currentPage.value < numberOfPages.value - 1)

	const hasPreviousPage = computed(() => currentPage.value > 0 && displayedVideos.value.length > 0)

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
		displayedVideos,
		hasNextPage,
		hasPreviousPage,
		next,
		previous,
	}
}
