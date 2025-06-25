/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { reactive } from 'vue'
import EmitterMixin from '../../EmitterMixin.js'
import CallParticipantModel from './CallParticipantModel.js'

/**
 *
 */
export default function CallParticipantCollection() {
	this._superEmitterMixin()

	this.callParticipantModels = reactive([])
}

CallParticipantCollection.prototype = {

	add(options) {
		const callParticipantModel = new CallParticipantModel(options)
		this.callParticipantModels.push(callParticipantModel)

		this._trigger('add', [callParticipantModel])

		return callParticipantModel
	},

	get(peerId) {
		return this.callParticipantModels.find(function(callParticipantModel) {
			return callParticipantModel.attributes.peerId === peerId
		})
	},

	remove(peerId) {
		const index = this.callParticipantModels.findIndex(function(callParticipantModel) {
			return callParticipantModel.attributes.peerId === peerId
		})
		if (index !== -1) {
			const callParticipantModel = this.callParticipantModels[index]

			this.callParticipantModels.splice(index, 1)

			this._trigger('remove', [callParticipantModel])

			callParticipantModel.destroy()
			return true
		}
		return false
	},

}

EmitterMixin.apply(CallParticipantCollection.prototype)
