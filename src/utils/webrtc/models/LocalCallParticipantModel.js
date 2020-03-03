/**
 *
 * @copyright Copyright (c) 2019, Daniel Calviño Sánchez (danxuliu@gmail.com)
 *
 * @license GNU AGPL version 3 or any later version
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

export default function LocalCallParticipantModel() {

	this.attributes = {
		peerId: null,
		guestName: null,
	}

	this._handlers = []

}

LocalCallParticipantModel.prototype = {

	set: function(key, value) {
		this.attributes[key] = value
	},

	on: function(event, handler) {
		if (!this._handlers.hasOwnProperty(event)) {
			this._handlers[event] = [handler]
		} else {
			this._handlers[event].push(handler)
		}
	},

	off: function(event, handler) {
		const index = this._handlers[event].indexOf(handler)
		if (index !== -1) {
			this._handlers[event].splice(index, 1)
		}
	},

	_trigger: function(event, args) {
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
	},

	setWebRtc: function(webRtc) {
		this._webRtc = webRtc

		this.set('peerId', this._webRtc.connection.getSessionId())
		this.set('guestName', null)
	},

	setGuestName: function(guestName) {
		if (!this._webRtc) {
			throw new Error('WebRtc not initialized yet')
		}

		this.set('guestName', guestName)

		this._webRtc.sendDirectlyToAll('status', 'nickChanged', guestName)
	},

}
