/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import mitt from 'mitt'

export const EventBus = mitt()

/**
 * Register a one-time event handler for the given type
 *
 * @param {string|symbol} type - type of event to listen for, or `'*'` for all events
 * @param {Function} handler - handler to call in response to given event
 */
EventBus.once = function(type, handler) {
	const fn = (...args) => {
		handler(...args)
		this.off(type, fn)
	}
	this.on(type, fn)
}
