/*
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createSharedComposable } from '@vueuse/core'
import { onUnmounted, readonly, ref } from 'vue'
import type { DeepReadonly, Ref } from 'vue'

/**
 * Composable to get current time (as Date object)
 * @param [precision] - precision in milliseconds (defaults to one minute)
 * @return Date reactive object with current time
 */
export function useCurrentTimeComposable(precision: number = 60_000): DeepReadonly<Ref<Date>> {
	let timeout: ReturnType<typeof setTimeout>

	const date = ref(new Date())

	requestUpdate()

	/**
	 * Called for shared composable when all subscribers are unmounted (onScopeDispose)
	 */
	onUnmounted(() => {
		clearTimeout(timeout)
	})

	/**
	 * Recursively request an update of the current time (with compensation of microtask queue inaccuracy)
	 */
	function requestUpdate() {
		date.value = new Date()
		timeout = setTimeout(() => {
			requestUpdate()
		}, precision - date.value.valueOf() % precision)
	}

	return readonly(date)
}

/**
 * Composable to get current time (as Date object)
 * @return Date reactive object with current time (with 60 seconds precision)
 */
function useCurrentTimeMinuteComposable(): DeepReadonly<Ref<Date>> {
	return useCurrentTimeComposable(60_000)
}

/**
 * Shared composable to get current time (as Date object)
 * @return Date reactive object with current time (with 60 seconds precision)
 */
export const useCurrentTime = createSharedComposable(useCurrentTimeMinuteComposable)
