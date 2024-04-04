/**
 *
 * @copyright Copyright (c) 2019, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

import { ref } from 'vue'

import CallParticipantModel from './CallParticipantModel.js'
import EmitterMixin from '../../EmitterMixin.js'

/**
 *
 */
export default function CallParticipantCollection() {

	this._superEmitterMixin()

	// FIXME: use reactive instead of ref after migration to vue 3
	this.callParticipantModels = ref([])

}

CallParticipantCollection.prototype = {

	add(options) {
		const callParticipantModel = new CallParticipantModel(options)
		this.callParticipantModels.value.push(callParticipantModel)

		this._trigger('add', [callParticipantModel])

		return callParticipantModel
	},

	get(peerId) {
		return this.callParticipantModels.value.find(function(callParticipantModel) {
			return callParticipantModel.attributes.peerId === peerId
		})
	},

	remove(peerId) {
		const index = this.callParticipantModels.value.findIndex(function(callParticipantModel) {
			return callParticipantModel.attributes.peerId === peerId
		})
		if (index !== -1) {
			const callParticipantModel = this.callParticipantModels.value[index]

			this.callParticipantModels.value.splice(index, 1)

			this._trigger('remove', [callParticipantModel])

			callParticipantModel.destroy()
			return true
		}
		return false
	},

}

EmitterMixin.apply(CallParticipantCollection.prototype)
