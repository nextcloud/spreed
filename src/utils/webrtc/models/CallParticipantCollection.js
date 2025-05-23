/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
