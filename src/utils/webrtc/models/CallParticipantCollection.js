/*!
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { reactive } from 'vue'
import EmitterMixin from '../../EmitterMixin.js'
import { CallParticipantModel } from './CallParticipantModel.js'

/**
 *
 */
export function CallParticipantCollection() {
	this._superEmitterMixin()

	this.callParticipantModels = reactive([])

	// Local volume of each actor, so it is kept when the participant
	// reconnects and a new model is created for them.
	this._volumes = new Map()

	this._handleVolumeChangedBound = this._handleVolumeChanged.bind(this)
	this._handleActorChangedBound = this._handleActorChanged.bind(this)
}

CallParticipantCollection.prototype = {

	add(options) {
		const callParticipantModel = new CallParticipantModel(options)
		this.callParticipantModels.push(callParticipantModel)

		callParticipantModel.on('change:volume', this._handleVolumeChangedBound)
		callParticipantModel.on('change:actorType', this._handleActorChangedBound)
		callParticipantModel.on('change:actorId', this._handleActorChangedBound)

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

			callParticipantModel.off('change:volume', this._handleVolumeChangedBound)
			callParticipantModel.off('change:actorType', this._handleActorChangedBound)
			callParticipantModel.off('change:actorId', this._handleActorChangedBound)

			this._trigger('remove', [callParticipantModel])

			callParticipantModel.destroy()
			return true
		}
		return false
	},

	_getActorKey(callParticipantModel) {
		const actorType = callParticipantModel.get('actorType')
		const actorId = callParticipantModel.get('actorId')
		if (!actorType || !actorId) {
			return null
		}

		return actorType + '/' + actorId
	},

	_handleVolumeChanged(callParticipantModel, volume) {
		const actorKey = this._getActorKey(callParticipantModel)
		if (!actorKey) {
			return
		}

		if (volume === 1) {
			this._volumes.delete(actorKey)
		} else {
			this._volumes.set(actorKey, volume)
		}
	},

	_handleActorChanged(callParticipantModel) {
		const actorKey = this._getActorKey(callParticipantModel)
		if (!actorKey || !this._volumes.has(actorKey)) {
			return
		}

		callParticipantModel.setVolume(this._volumes.get(actorKey))
	},

}

EmitterMixin.apply(CallParticipantCollection.prototype)
