/**
 *
 * @copyright Copyright (c) 2023 Maksim Sukharev <antreesy.web@gmail.com>
 *
 * @author Maksim Sukharev <antreesy.web@gmail.com>
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
 * Helper to handle speaking status changes notified by call models.
 *
 * The store is updated when local or remote participants change their speaking status.
 * It is expected that the speaking status of participant will be
 * modified only when the current conversation is joined and call is started.
 */
export default class SpeakingStatusHandler {

	// Constants, properties
	#store
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

		this.#callParticipantCollection.callParticipantModels.forEach(callParticipantModel => {
			callParticipantModel.off('change:speaking', this.#handleSpeakingBound)
			callParticipantModel.off('change:stoppedSpeaking', this.#handleSpeakingBound)
		})

		this.#store.dispatch('purgeSpeakingStore', { token: this.#store.getters.getToken() })
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
			token: this.#store.getters.getToken(),
			sessionId: this.#store.getters.getSessionId(),
			speaking,
		})
	}

	/**
	 * Dispatch speaking status of local participant to the store on peer ID
	 * changes.
	 */
	#handleLocalPeerId() {
		this.#store.dispatch('setSpeaking', {
			token: this.#store.getters.getToken(),
			sessionId: this.#store.getters.getSessionId(),
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
		this.#store.dispatch('setSpeaking', {
			token: this.#store.getters.getToken(),
			sessionId: callParticipantModel.attributes.nextcloudSessionId,
			speaking,
		})
	}

}
