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
	cancelOnce<Key extends keyof Events>(type: Key, handler?: Handler<Events[Key]>): void
	cancelOnce(type: '*', handler: WildcardHandler<Events>): void
	_onceHandlersMap: Map<keyof Events | '*', Map<GenericEventHandler, GenericEventHandler>>
}

export const EventBus: ExtendedEmitter = mitt() as ExtendedEmitter
EventBus._onceHandlersMap = new Map()

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

	// Store reference to the original handler to be able to remove it later
	if (!EventBus._onceHandlersMap.has(type)) {
		EventBus._onceHandlersMap.set(type, new Map())
	}
	EventBus._onceHandlersMap.get(type)!.set(handler, fn)
}

/**
 * OVERRIDING OF ORIGINAL MITT FUNCTION
 * Remove an event handler for the given type.
 * If `handler` is omitted, all handlers of the given type are removed.
 * @param type Type of event to unregister `handler` from (`'*'` to remove a wildcard handler)
 * @param [handler] Handler function to remove
 */
EventBus.off = function<Key extends keyof Events>(type: Key, handler?: GenericEventHandler) {
	const handlers: Array<GenericEventHandler> | undefined = EventBus.all!.get(type)
	const onceHandlers: Map<GenericEventHandler, GenericEventHandler> | undefined = EventBus._onceHandlersMap!.get(type)

	if (handlers) {
		if (handler) {
			handlers.splice(handlers.indexOf(handler) >>> 0, 1)
		} else {
			EventBus.all!.set(type, [])
		}
	}

	if (handlers && onceHandlers) {
		if (handler) {
			for (const [_handler, fn] of onceHandlers) {
				if (handler === fn) {
					// Event was received once and removed by original code, clearing reference only
					onceHandlers.delete(_handler)
				} else if (_handler === handler) {
					// Event was not received yet, removing the fn and reference
					handlers.splice(handlers.indexOf(fn) >>> 0, 1)
					onceHandlers.delete(_handler)
				}
			}
		} else {
			EventBus._onceHandlersMap.set(type, new Map())
		}
	}
}
