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
export const _onceHandlers = new Map<keyof Events | '*', Map<GenericEventHandler, GenericEventHandler>>()

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
	if (!_onceHandlers.has(type)) {
		_onceHandlers.set(type, new Map())
	}
	_onceHandlers.get(type)!.set(handler, fn)
}

const off = EventBus.off.bind(EventBus)
/**
 * OVERRIDING OF ORIGINAL MITT FUNCTION
 * Remove an event handler for the given type.
 * If `handler` is omitted, all handlers of the given type are removed.
 * @param type Type of event to unregister `handler` from (`'*'` to remove a wildcard handler)
 * @param [handler] Handler function to remove
 */
EventBus.off = function<Key extends keyof Events>(type: Key, handler?: GenericEventHandler) {
	// @ts-expect-error: Vue: No overload matches this call
	off(type, handler)

	const onceHandlers = handler && _onceHandlers.get(type)
	if (onceHandlers) {
		for (const [originalFn, fn] of onceHandlers) {
			if (fn === handler) {
				onceHandlers!.delete(originalFn)
			} else if (originalFn === handler) {
				// @ts-expect-error: Vue: No overload matches this call
				off(type, fn)
				onceHandlers!.delete(originalFn)
			}
		}
	} else {
		_onceHandlers.delete(type)
	}
}
