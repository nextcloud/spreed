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

import CallParticipantModel from './CallParticipantModel'

export default function CallParticipantCollection() {

	this.callParticipantModels = []

	this._handlers = []

}

CallParticipantCollection.prototype = {

	on: function(event, handler) {
		if (!this._handlers.hasOwnProperty(event)) {
			this._handlers[event] = [handler]
		} else {
			this._handlers[event].push(handler)
		}
	},

	off: function(event, handler) {
		const handlers = this._handlers[event]
		if (!handlers) {
			return
		}

		const index = handlers.indexOf(handler)
		if (index !== -1) {
			handlers.splice(index, 1)
		}
	},

	_trigger: function(event, args) {
		let handlers = this._handlers[event]
		if (!handlers) {
			return
		}

		args.unshift(this)

		handlers = handlers.slice(0)
		for (let i = 0; i < handlers.length; i++) {
			const handler = handlers[i]
			handler.apply(handler, args)
		}
	},

	add: function(options) {
		const callParticipantModel = new CallParticipantModel(options)
		this.callParticipantModels.push(callParticipantModel)

		this._trigger('add', [callParticipantModel])

		return callParticipantModel
	},

	get: function(peerId) {
		return this.callParticipantModels.find(function(callParticipantModel) {
			return callParticipantModel.attributes.peerId === peerId
		})
	},

	remove: function(peerId) {
		const index = this.callParticipantModels.findIndex(function(callParticipantModel) {
			return callParticipantModel.attributes.peerId === peerId
		})
		if (index !== -1) {
			const callParticipantModel = this.callParticipantModels[index]

			this.callParticipantModels.splice(index, 1)

			this._trigger('remove', [callParticipantModel])
		}
	},

}
