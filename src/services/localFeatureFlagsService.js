/*
 * @copyright Copyright (c) 2023 Grigorii Shartsev <me@shgk.me>
 *
 * @author Grigorii Shartsev <me@shgk.me>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

import BrowserStorage from './BrowserStorage.js'

export const FEATURE_FLAGS = {
	CONVERSATIONS_LIST__SOFT_CONVERSATIONS_UPDATE: false,
	CONVERSATIONS_LIST__REVERT_USER_STATUS_SYNC: false,
	CONVERSATIONS_LIST__HIDDEN_AVATARS: false,
}

/**
 * Persist all feature flags in the Browser Storage
 */
export function persistFeatureFlags() {
	BrowserStorage.setItem('FEATURE_FLAGS', JSON.stringify(FEATURE_FLAGS))
}

/**
 * Restore feature flags from the browser storage
 */
export function restoreFeatureFlags() {
	const restored = BrowserStorage.getItem('FEATURE_FLAGS')
	if (!restored) {
		return
	}

	const parsedFlags = JSON.parse(restored)
	Object.assign(FEATURE_FLAGS, parsedFlags)
	console.info('FEATURE_FLAGS are available: ', parsedFlags)
}

/**
 * Reset feature flag
 */
export function resetFeatureFlags() {
	BrowserStorage.removeItem('FEATURE_FLAGS')
	console.info('FEATURE_FLAGS are reset. Do not forget to reload the page.')
}

/**
 * Enable and persist feature flag
 *
 * @param {string} flag The flag
 * @param {any} [value=true] flag value if not just true
 */
export function enableFeatureFlag(flag, value = true) {
	if (FEATURE_FLAGS[flag] === undefined) {
		console.error(`Unknown feature flag ${flag}. Available flags: ${Object.keys(FEATURE_FLAGS)}`)
		return
	}
	FEATURE_FLAGS[flag] = value
	persistFeatureFlags()
	console.info(`Feature flag ${flag} is enabled. You might need to reload the page.`)
}

/**
 * Disable feature flag
 *
 * @param {string} flag The flag
 * @param {any} [value=false] optional value
 */
export function disableFeatureFlag(flag, value = false) {
	if (FEATURE_FLAGS[flag] === undefined) {
		console.error(`Unknown feature flag ${flag}. Available flags: ${Object.keys(FEATURE_FLAGS)}`)
		return
	}
	FEATURE_FLAGS[flag] = value
	persistFeatureFlags()
	console.info(`Feature flag ${flag} is disabled. You might need to reload the page.`)
}

/**
 * Init feature flags: restore, add global variable and Vue plugin
 * To use in the console:
 * - __TALK__.FEATURE_FLAGS.enableFeatureFlag('FLAG_NAME')
 * - __TALK__.FEATURE_FLAGS.disableFeatureFlag('FLAG_NAME')
 * - __TALK__.FEATURE_FLAGS.resetFeatureFlags('FLAG_NAME')
 *
 * @return {object} Vue plugin to inject global property $FEATURE_FLAGS to components
 */
export function initFeatureFlags() {
	restoreFeatureFlags()
	if (window.OCA?.Talk) {
		window.OCA.Talk.FEATURE_FLAGS = {
			FLAGS: FEATURE_FLAGS,
			enableFeatureFlag,
			disableFeatureFlag,
			resetFeatureFlags,
		}
	}
	return {
		install(Vue) {
			Vue.prototype.FEATURE_FLAGS = FEATURE_FLAGS
		},
	}
}
