/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Emitter of events.
 *
 * The mixin can be inherited calling "EmitterMixin.apply(Inheriter.prototype)";
 * "_superEmitterMixin()" must be called from the constructor. Inheriters of the
 * mixin can trigger events calling "_trigger('eventName', [arguments])".
 *
 * Clients of the emitter can subscribe to events calling "on('eventName',
 * eventHandler)", and desubscribe calling "off('eventName', eventHandler)". The
 * event handler should be defined as "eventHandler(emitter, argument0,
 * argument1...)". The same subscribed event handler must be provided to
 * desubscribe it, so an inline function would not work (a function stored in a
 * variable and probably bound to a specific object may need to be used
 * instead).
 */
export default (function() {
	/**
	 * Mixin constructor.
	 *
	 * Adds mixin attributes to objects inheriting the mixin.
	 *
	 * This must be called in their constructor by classes inheriting the mixin.
	 */
	function _superEmitterMixin() {
		this._handlers = []
	}

	/**
	 * @param {string} event the name of the event
	 * @param {Function} handler the handler function to register
	 */
	function on(event, handler) {
		if (!Object.prototype.hasOwnProperty.call(this._handlers, event)) {
			this._handlers[event] = [handler]
		} else {
			this._handlers[event].push(handler)
		}
	}

	/**
	 * @param {string} event the name of the event
	 * @param {Function} handler the handler function to deregister
	 */
	function off(event, handler) {
		const handlers = this._handlers[event]
		if (!handlers) {
			return
		}

		const index = handlers.indexOf(handler)
		if (index !== -1) {
			handlers.splice(index, 1)
		}
	}

	/**
	 * @param {string} event the name of the event
	 * @param {Array} args the arguments of the event
	 */
	function _trigger(event, args) {
		let handlers = this._handlers[event]
		if (!handlers) {
			return
		}

		if (!args) {
			args = []
		}

		args.unshift(this)

		handlers = handlers.slice(0)
		for (let i = 0; i < handlers.length; i++) {
			const handler = handlers[i]
			handler.apply(handler, args)
		}
	}

	return function() {
		// Add methods to the prototype from the functions defined above
		this._superEmitterMixin = _superEmitterMixin
		this.on = on
		this.off = off
		this._trigger = _trigger
	}
})()
