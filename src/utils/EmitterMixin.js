/**
 *
 * @copyright Copyright (c) 2021, Daniel Calviño Sánchez (danxuliu@gmail.com)
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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
