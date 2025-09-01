/*
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { ShallowRef } from 'vue'

import { shallowReadonly, shallowRef } from 'vue'

type ResultContainer<T> = {
	result: Readonly<ShallowRef<T | undefined>>
	isReady: ShallowRef<boolean>
	isLoading: ShallowRef<boolean>
	init: () => Promise<void>
}

/**
 * Cache modules by loader to immediately return resolved module.
 * Note: the loader itself is safe to be called multiple times. It is resolved by ESM/Bundler.
 * But with a promise it requires at least one tick to resolve.
 */
const cache = new Map<() => Promise<unknown>, ResultContainer<unknown>>()

/**
 * Use lazy/async initialization.
 * Allows to use something requiring asynchronous load/initialization with reactivity, such as dynamically imported modules or heavy initialized modules.
 * Make sure that the same initiator function reference is used for the same initialization.
 *
 * @param initiator - Initialization function
 * @param immediate - Whether to call the initiator immediately
 */
export function useAsyncInit<T>(initiator: () => Promise<T>, immediate: boolean = false): ResultContainer<T> {
	if (cache.has(initiator)) {
		return cache.get(initiator) as ResultContainer<T>
	}

	// Use shallowRef to avoid unexpected deep reactivity for the result
	const result = shallowRef<T | undefined>(undefined)
	const isReady = shallowRef(false)
	const isLoading = shallowRef(false)

	/**
	 * Initialize
	 */
	async function init() {
		// Avoid multiple initializations
		if (isReady.value || isLoading.value) {
			return
		}

		isLoading.value = true
		result.value = await initiator()
		isLoading.value = false
		isReady.value = true
	}

	const resultContainer: ResultContainer<T> = {
		result: shallowReadonly(result),
		isReady: shallowReadonly(isReady),
		isLoading: shallowReadonly(isLoading),
		init,
	}

	cache.set(initiator, resultContainer)

	if (immediate) {
		init()
	}

	return resultContainer
}
