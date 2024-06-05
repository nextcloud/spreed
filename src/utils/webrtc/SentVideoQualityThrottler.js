/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import {
	QUALITY,
	VideoConstrainer,
} from './VideoConstrainer.js'

/**
 * Helper to adjust the quality of the sent video based on the current call
 * state.
 *
 * The properties of the local video (like resolution or frame rate) can be
 * changed on the fly during a call with immediate effect, without having to
 * reconnect to the call. This class uses that feature to dynamically reduce or
 * increase the video quality depending on the call state. Basically the goal is
 * to reduce the CPU usage when there are too many participants in a call.
 *
 * @param {object} localMediaModel the model for the local media.
 * @param {object} callParticipantCollection the collection.
 *        that contains the models for the rest of the participants in the call.
 * @param {object} trackConstrainer the track constrainer node on which apply
 *        the constraints.
 */
export default function SentVideoQualityThrottler(localMediaModel, callParticipantCollection, trackConstrainer) {
	this._localMediaModel = localMediaModel
	this._callParticipantCollection = callParticipantCollection

	this._videoConstrainer = new VideoConstrainer(trackConstrainer)

	this._gracePeriodAfterSpeakingTimeout = null
	this._speakingOrInGracePeriodAfterSpeaking = false

	this._availableVideosThreshold = {}
	this._availableVideosThreshold[QUALITY.THUMBNAIL] = 15
	this._availableVideosThreshold[QUALITY.VERY_LOW] = 10
	this._availableVideosThreshold[QUALITY.LOW] = 7
	this._availableVideosThreshold[QUALITY.MEDIUM] = 4
	// QUALITY.HIGH otherwise

	this._availableAudiosThreshold = {}
	this._availableAudiosThreshold[QUALITY.THUMBNAIL] = 40
	this._availableAudiosThreshold[QUALITY.VERY_LOW] = 30
	this._availableAudiosThreshold[QUALITY.LOW] = 20
	this._availableAudiosThreshold[QUALITY.MEDIUM] = 10
	// QUALITY.HIGH otherwise

	this._handleLocalVideoAvailableChangeBound = this._handleLocalVideoAvailableChange.bind(this)
	this._handleAddParticipantBound = this._handleAddParticipant.bind(this)
	this._handleRemoveParticipantBound = this._handleRemoveParticipant.bind(this)
	this._handleLocalAudioEnabledChangeBound = this._handleLocalAudioEnabledChange.bind(this)
	this._handleLocalSpeakingChangeBound = this._handleLocalSpeakingChange.bind(this)
	this._adjustVideoQualityIfNeededBound = this._adjustVideoQualityIfNeeded.bind(this)

	this._localMediaModel.on('change:videoAvailable', this._handleLocalVideoAvailableChangeBound)

	if (this._localMediaModel.get('videoAvailable')) {
		this._startListeningToChanges()
	}
}
SentVideoQualityThrottler.prototype = {

	destroy() {
		this._localMediaModel.off('change:videoAvailable', this._handleLocalVideoAvailableChangeBound)

		this._stopListeningToChanges()
	},

	_handleLocalVideoAvailableChange(localMediaModel, videoAvailable) {
		if (videoAvailable) {
			this._startListeningToChanges()
		} else {
			this._stopListeningToChanges()
		}
	},

	_startListeningToChanges() {
		this._localMediaModel.on('change:videoEnabled', this._adjustVideoQualityIfNeededBound)
		this._localMediaModel.on('change:audioEnabled', this._handleLocalAudioEnabledChangeBound)
		this._localMediaModel.on('change:speaking', this._handleLocalSpeakingChangeBound)

		this._callParticipantCollection.on('add', this._handleAddParticipantBound)
		this._callParticipantCollection.on('remove', this._handleRemoveParticipantBound)

		this._callParticipantCollection.callParticipantModels.forEach((callParticipantModel) => {
			callParticipantModel.on('change:videoAvailable', this._adjustVideoQualityIfNeededBound)
			callParticipantModel.on('change:audioAvailable', this._adjustVideoQualityIfNeededBound)
		})

		this._handleLocalSpeakingChange()
		this._handleLocalAudioEnabledChange()

		this._adjustVideoQualityIfNeeded()
	},

	_stopListeningToChanges() {
		this._localMediaModel.off('change:videoEnabled', this._adjustVideoQualityIfNeededBound)
		this._localMediaModel.off('change:audioEnabled', this._handleLocalAudioEnabledChangeBound)
		this._localMediaModel.off('change:speaking', this._handleLocalSpeakingChangeBound)

		this._callParticipantCollection.off('add', this._handleAddParticipantBound)
		this._callParticipantCollection.off('remove', this._handleRemoveParticipantBound)

		this._callParticipantCollection.callParticipantModels.forEach((callParticipantModel) => {
			callParticipantModel.off('change:videoAvailable', this._adjustVideoQualityIfNeededBound)
			callParticipantModel.off('change:audioAvailable', this._adjustVideoQualityIfNeededBound)
		})
	},

	_handleAddParticipant(callParticipantCollection, callParticipantModel) {
		callParticipantModel.on('change:videoAvailable', this._adjustVideoQualityIfNeededBound)
		callParticipantModel.on('change:audioAvailable', this._adjustVideoQualityIfNeededBound)

		this._adjustVideoQualityIfNeeded()
	},

	_handleRemoveParticipant(callParticipantCollection, callParticipantModel) {
		callParticipantModel.off('change:videoAvailable', this._adjustVideoQualityIfNeededBound)
		callParticipantModel.off('change:audioAvailable', this._adjustVideoQualityIfNeededBound)

		this._adjustVideoQualityIfNeeded()
	},

	_handleLocalAudioEnabledChange() {
		if (this._localMediaModel.get('audioEnabled')) {
			return
		}

		window.clearTimeout(this._gracePeriodAfterSpeakingTimeout)
		this._gracePeriodAfterSpeakingTimeout = null

		this._speakingOrInGracePeriodAfterSpeaking = false

		this._adjustVideoQualityIfNeeded()
	},

	_handleLocalSpeakingChange() {
		if (this._localMediaModel.get('speaking')) {
			window.clearTimeout(this._gracePeriodAfterSpeakingTimeout)
			this._gracePeriodAfterSpeakingTimeout = null

			this._speakingOrInGracePeriodAfterSpeaking = true

			this._adjustVideoQualityIfNeeded()

			return
		}

		this._gracePeriodAfterSpeakingTimeout = window.setTimeout(() => {
			this._speakingOrInGracePeriodAfterSpeaking = false

			this._adjustVideoQualityIfNeeded()
		}, 5000)
	},

	_adjustVideoQualityIfNeeded() {
		if (!this._localMediaModel.get('videoAvailable') || !this._localMediaModel.get('videoEnabled')) {
			return
		}

		const quality = this._getQualityForState()
		this._videoConstrainer.applyConstraints(quality)
	},

	_getQualityForState() {
		if (this._speakingOrInGracePeriodAfterSpeaking) {
			return QUALITY.HIGH
		}

		let availableVideosCount = 0
		let availableAudiosCount = 0
		this._callParticipantCollection.callParticipantModels.forEach((callParticipantModel) => {
			if (callParticipantModel.get('videoAvailable')) {
				availableVideosCount++
			}
			if (callParticipantModel.get('audioAvailable')) {
				availableAudiosCount++
			}
		})

		for (let i = QUALITY.THUMBNAIL; i < QUALITY.HIGH; i++) {
			if (availableVideosCount >= this._availableVideosThreshold[i]
				|| availableAudiosCount >= this._availableAudiosThreshold[i]) {
				return i
			}
		}

		return QUALITY.HIGH
	},

}
