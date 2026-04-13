/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export const DEFAULT_VIRTUAL_BACKGROUND_DEBUG_CONFIG = Object.freeze({
	DEFAULT_BLUR_PASSES: 3,
	SIGMA_SPACE: 5,
	SIGMA_COLOR: 0.15,
	SPARSITY_FACTOR: 1,
	DEFAULT_FRAME_RATE: 30,
	MAX_SEGMENTATION_FRAME_RATE: 25,
})

export const VIRTUAL_BACKGROUND_DEBUG_CONFIG_RANGES = Object.freeze({
	DEFAULT_BLUR_PASSES: Object.freeze({ min: 1, max: 6, step: 1 }),
	SIGMA_SPACE: Object.freeze({ min: 1, max: 20, step: 0.5 }),
	SIGMA_COLOR: Object.freeze({ min: 0.05, max: 1, step: 0.05 }),
	SPARSITY_FACTOR: Object.freeze({ min: 0.05, max: 2, step: 0.05 }),
	DEFAULT_FRAME_RATE: Object.freeze({ min: 1, max: 60, step: 1 }),
	MAX_SEGMENTATION_FRAME_RATE: Object.freeze({ min: 1, max: 60, step: 1 }),
})

const integerKeys = new Set([
	'DEFAULT_BLUR_PASSES',
	'DEFAULT_FRAME_RATE',
	'MAX_SEGMENTATION_FRAME_RATE',
])

const configKeys = Object.keys(DEFAULT_VIRTUAL_BACKGROUND_DEBUG_CONFIG)

export const virtualBackgroundDebugConfig = {
	...DEFAULT_VIRTUAL_BACKGROUND_DEBUG_CONFIG,
}

/**
 * Sets a debug config value after parsing and clamping it to the allowed range.
 *
 * @param {string} key - Config key to update.
 * @param {number|string} value - New value coming from the UI/global object.
 * @return {number} The normalized value stored in the shared config.
 */
export function setVirtualBackgroundDebugConfigValue(key, value) {
	if (!Object.prototype.hasOwnProperty.call(virtualBackgroundDebugConfig, key)) {
		throw new Error(`Unknown virtual background debug config key: ${key}`)
	}

	const parsedValue = integerKeys.has(key)
		? Number.parseInt(value, 10)
		: Number.parseFloat(value)
	const { min, max } = VIRTUAL_BACKGROUND_DEBUG_CONFIG_RANGES[key]
	const fallbackValue = DEFAULT_VIRTUAL_BACKGROUND_DEBUG_CONFIG[key]
	const normalizedValue = Number.isNaN(parsedValue)
		? fallbackValue
		: Math.min(max, Math.max(min, parsedValue))

	virtualBackgroundDebugConfig[key] = normalizedValue

	return normalizedValue
}

/**
 * Exposes the shared debug config on the provided Talk object.
 *
 * @param {object} talkObject - window.OCA.Talk object.
 * @return {object} The same object with debug config attached.
 */
export function exposeVirtualBackgroundDebugConfig(talkObject) {
	for (const key of configKeys) {
		Object.defineProperty(talkObject, key, {
			configurable: true,
			enumerable: true,
			get() {
				return virtualBackgroundDebugConfig[key]
			},
			set(value) {
				setVirtualBackgroundDebugConfigValue(key, value)
			},
		})
	}

	talkObject.VIRTUAL_BACKGROUND_DEBUG_CONFIG = virtualBackgroundDebugConfig
	talkObject.VIRTUAL_BACKGROUND_DEBUG_CONFIG_DEFAULTS = DEFAULT_VIRTUAL_BACKGROUND_DEBUG_CONFIG
	talkObject.VIRTUAL_BACKGROUND_DEBUG_CONFIG_RANGES = VIRTUAL_BACKGROUND_DEBUG_CONFIG_RANGES

	return talkObject
}
