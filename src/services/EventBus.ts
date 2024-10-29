/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import mitt from 'mitt'
import type { Emitter, EventType, Handler, WildcardHandler } from 'mitt'

type Events = Record<EventType, unknown>
type GenericEventHandler = Handler<Events[keyof Events]> | WildcardHandler<Events>
type ExtendedEmitter = Emitter<Events> & {
	once<Key extends keyof Events>(type: Key, handler: Handler<Events[Key]>): void
	once(type: '*', handler: WildcardHandler<Events>): void
}
export const EventBus: ExtendedEmitter = mitt() as ExtendedEmitter

/**
 * Register a one-time event handler for the given type
 *
 * @param type - type of event to listen for, or `'*'` for all events
 * @param handler - handler to call in response to given event
 */
EventBus.once = function<Key extends keyof Events>(type: Key, handler: GenericEventHandler) {
	/**
	 * @param args - event arguments: (type, event) or (event)
	 */
	const fn = (...args: Parameters<GenericEventHandler>) => {
		// @ts-expect-error: Vue: A spread argument must either have a tuple type or be passed to a rest parameter.
		handler(...args)
		this.off(type, fn)
	}
	this.on(type, fn)
}
