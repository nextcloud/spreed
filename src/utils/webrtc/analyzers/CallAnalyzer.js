/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { reactive } from 'vue'

import { ParticipantAnalyzer } from './ParticipantAnalyzer.js'
import EmitterMixin from '../../EmitterMixin.js'

/**
 * Analyzer for the quality of the connections of a call.
 *
 * After a CallAnalyzer is created the analysis will be automatically started.
 *
 * When the quality of the connections change different events will be triggered
 * depending on the case:
 * - 'change:senderConnectionQualityAudio'
 * - 'change:senderConnectionQualityVideo'
 * - 'change:senderConnectionQualityScreen'
 *
 * The reported values are based on CONNECTION_QUALITY values of
 * PeerConnectionAnalyzer.
 *
 * Besides the event themselves, the quality can be known too using
 * "get(valueName)" or even directly from the "attributes" object.
 *
 * Once the CallAnalyzer is no longer needed "destroy()" must be called to stop
 * the analysis.
 *
 * @param {object} localMediaModel the model for the local media.
 * @param {object} localCallParticipantModel the model for
 * the local participant; null if an MCU is not used.
 * @param {object} callParticipantCollection the collection
 * for the remote participants.
 */
export default function CallAnalyzer(localMediaModel, localCallParticipantModel, callParticipantCollection) {
	this._superEmitterMixin()

	this.attributes = reactive({
		senderConnectionQualityAudio: null,
		senderConnectionQualityVideo: null,
		senderConnectionQualityScreen: null,
	})

	this._localMediaModel = localMediaModel
	this._localCallParticipantModel = localCallParticipantModel
	this._callParticipantCollection = callParticipantCollection

	this._handleSenderConnectionQualityAudioChangeBound = this._handleSenderConnectionQualityAudioChange.bind(this)
	this._handleSenderConnectionQualityVideoChangeBound = this._handleSenderConnectionQualityVideoChange.bind(this)
	this._handleSenderConnectionQualityScreenChangeBound = this._handleSenderConnectionQualityScreenChange.bind(this)

	if (localCallParticipantModel) {
		this._localParticipantAnalyzer = new ParticipantAnalyzer()
		this._localParticipantAnalyzer.setSenderParticipant(localMediaModel, localCallParticipantModel)

		this._localParticipantAnalyzer.on('change:senderConnectionQualityAudio', this._handleSenderConnectionQualityAudioChangeBound)
		this._localParticipantAnalyzer.on('change:senderConnectionQualityVideo', this._handleSenderConnectionQualityVideoChangeBound)
		this._localParticipantAnalyzer.on('change:senderConnectionQualityScreen', this._handleSenderConnectionQualityScreenChangeBound)
	}
}
CallAnalyzer.prototype = {

	get(key) {
		return this.attributes[key]
	},

	set(key, value) {
		this.attributes[key] = value

		this._trigger('change:' + key, [value])
	},

	destroy() {
		if (this._localParticipantAnalyzer) {
			this._localParticipantAnalyzer.off('change:senderConnectionQualityAudio', this._handleSenderConnectionQualityAudioChangeBound)
			this._localParticipantAnalyzer.off('change:senderConnectionQualityVideo', this._handleSenderConnectionQualityVideoChangeBound)
			this._localParticipantAnalyzer.off('change:senderConnectionQualityScreen', this._handleSenderConnectionQualityScreenChangeBound)

			this._localParticipantAnalyzer.destroy()
		}
	},

	_handleSenderConnectionQualityAudioChange(participantAnalyzer, senderConnectionQualityAudio) {
		this.set('senderConnectionQualityAudio', senderConnectionQualityAudio)
	},

	_handleSenderConnectionQualityVideoChange(participantAnalyzer, senderConnectionQualityVideo) {
		this.set('senderConnectionQualityVideo', senderConnectionQualityVideo)
	},

	_handleSenderConnectionQualityScreenChange(participantAnalyzer, senderConnectionQualityScreen) {
		this.set('senderConnectionQualityScreen', senderConnectionQualityScreen)
	},

}

EmitterMixin.apply(CallAnalyzer.prototype)
