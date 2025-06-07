/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Helper to handle speaking status changes notified by call models.
 *
 * The store is updated when local or remote participants change their speaking status.
 * It is expected that the speaking status of participant will be
 * modified only when the current conversation is joined and call is started.
 */

import { useActorStore } from '../../stores/actor.ts'
import pinia from '../../stores/pinia.ts'
export default class SpeakingStatusHandler {
	// Constants, properties
	#store
	#actorStore
	#localMediaModel
	#localCallParticipantModel
	#callParticipantCollection

	// Methods (bound to have access to 'this')
	#handleAddParticipantBound
	#handleRemoveParticipantBound
	#handleLocalSpeakingBound
	#handleLocalPeerIdBound
	#handleSpeakingBound

	constructor(store, localMediaModel, localCallParticipantModel, callParticipantCollection) {
		this.#store = store
		this.#actorStore = useActorStore(pinia)
		this.#localMediaModel = localMediaModel
		this.#localCallParticipantModel = localCallParticipantModel
		this.#callParticipantCollection = callParticipantCollection

		this.#handleAddParticipantBound = this.#handleAddParticipant.bind(this)
		this.#handleRemoveParticipantBound = this.#handleRemoveParticipant.bind(this)
		this.#handleLocalSpeakingBound = this.#handleLocalSpeaking.bind(this)
		this.#handleLocalPeerIdBound = this.#handleLocalPeerId.bind(this)
		this.#handleSpeakingBound = this.#handleSpeaking.bind(this)

		this.#localMediaModel.on('change:speaking', this.#handleLocalSpeakingBound)
		this.#localMediaModel.on('change:stoppedSpeaking', this.#handleLocalSpeakingBound)

		this.#localCallParticipantModel.on('change:peerId', this.#handleLocalPeerIdBound)

		this.#callParticipantCollection.on('add', this.#handleAddParticipantBound)
		this.#callParticipantCollection.on('remove', this.#handleRemoveParticipantBound)
	}

	/**
	 * Destroy a handler, remove all listeners, purge the speaking state from store
	 */
	destroy() {
		this.#localMediaModel.off('change:speaking', this.#handleLocalSpeakingBound)
		this.#localMediaModel.off('change:stoppedSpeaking', this.#handleLocalSpeakingBound)

		this.#localCallParticipantModel.off('change:peerId', this.#handleLocalPeerIdBound)

		this.#callParticipantCollection.off('add', this.#handleAddParticipantBound)
		this.#callParticipantCollection.off('remove', this.#handleRemoveParticipantBound)

		this.#callParticipantCollection.callParticipantModels.value.forEach((callParticipantModel) => {
			callParticipantModel.off('change:speaking', this.#handleSpeakingBound)
			callParticipantModel.off('change:stoppedSpeaking', this.#handleSpeakingBound)
		})

		this.#store.dispatch('purgeSpeakingStore')
	}

	/**
	 * Add listeners for speaking status changes on added participants model
	 *
	 * @param {object} callParticipantCollection the collection of external participant models
	 * @param {object} callParticipantModel the added participant model
	 */
	#handleAddParticipant(callParticipantCollection, callParticipantModel) {
		callParticipantModel.on('change:speaking', this.#handleSpeakingBound)
		callParticipantModel.on('change:stoppedSpeaking', this.#handleSpeakingBound)
	}

	/**
	 * Remove listeners for speaking status changes on removed participants model
	 *
	 * @param {object} callParticipantCollection the collection of external participant models
	 * @param {object} callParticipantModel the removed participant model
	 */
	#handleRemoveParticipant(callParticipantCollection, callParticipantModel) {
		callParticipantModel.off('change:speaking', this.#handleSpeakingBound)
		callParticipantModel.off('change:stoppedSpeaking', this.#handleSpeakingBound)
	}

	/**
	 * Dispatch speaking status of local participant to the store
	 *
	 * @param {object} localMediaModel the local media model
	 * @param {boolean} speaking whether the participant is speaking or not
	 */
	#handleLocalSpeaking(localMediaModel, speaking) {
		this.#store.dispatch('setSpeaking', {
			attendeeId: this.#actorStore.attendeeId,
			speaking,
		})
	}

	/**
	 * Dispatch speaking status of local participant to the store on peer ID
	 * changes.
	 */
	#handleLocalPeerId() {
		this.#store.dispatch('setSpeaking', {
			attendeeId: this.#actorStore.attendeeId,
			speaking: this.#localMediaModel.attributes.speaking,
		})
	}

	/**
	 * Dispatch speaking status of participant to the store
	 *
	 * @param {object} callParticipantModel the participant model
	 * @param {boolean} speaking whether the participant is speaking or not
	 */
	#handleSpeaking(callParticipantModel, speaking) {
		const attendeeId = this.#store.getters.findParticipant(
			this.#store.getters.getToken(),
			{ sessionId: callParticipantModel.attributes.nextcloudSessionId },
		)?.attendeeId

		if (!attendeeId) {
			return
		}

		this.#store.dispatch('setSpeaking', {
			attendeeId,
			speaking,
		})
	}
}
