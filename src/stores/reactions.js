/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { defineStore } from 'pinia'
import { MESSAGE } from '../constants.ts'
import {
	addReactionToMessage,
	getReactionsDetails,
	removeReactionFromMessage,
} from '../services/reactionsService.ts'
import store from '../store/index.js'

/**
 * @typedef {string} Token
 */

/**
 * @typedef {number} MessageId
 */

/**
 * @typedef {object} Reactions
 * @property {string} emoji - reaction emoji
 * @property {object} participant - reacting participant
 */

/**
 * @typedef {object} State
 * @property {{[key: Token]: {[key: MessageId]: Reactions}}} reactions - The reactions per message.
 */

/**
 * Store for conversation extra chat features apart from messages
 *
 * @param {string} id store name
 * @param {State} options.state store state structure
 */
export const useReactionsStore = defineStore('reactions', {
	state: () => ({
		reactions: {},
	}),

	getters: {
		getReactions: (state) => (token, messageId) => {
			return state.reactions?.[token]?.[messageId]
		},
	},

	actions: {
		/**
		 * Remove reactions from the store for a given conversation.
		 *
		 * @param {string} token The conversation token
		 *
		 */
		purgeReactionsStore(token) {
			delete this.reactions[token]
		},

		checkForExistence(token, messageId) {
			if (!this.reactions[token]) {
				this.reactions[token] = {}
			}

			if (!this.reactions[token][messageId]) {
				this.reactions[token][messageId] = {}
			}
		},

		/**
		 * Add a reaction for a given message.
		 *
		 * @param {object} payload action payload
		 * @param {string} payload.token The conversation token
		 * @param {number} payload.messageId The id of message
		 * @param {string} payload.reaction The reaction to add
		 * @param {object} payload.actors The users who reacted
		 *
		 */
		addReaction({ token, messageId, reaction, actors }) {
			this.reactions[token][messageId][reaction] = actors
		},

		/**
		 * Remove a reaction for a given message.
		 *
		 * @param {object} payload action payload
		 * @param {string} payload.token The conversation token
		 * @param {number} payload.messageId The id of message
		 * @param {string} payload.reaction The reaction to remove
		 *
		 */
		removeReaction({ token, messageId, reaction }) {
			delete this.reactions[token][messageId][reaction]
		},

		/**
		 * Add an actor for a given reaction emoji.
		 *
		 * @param {object} payload action payload
		 * @param {string} payload.token The conversation token
		 * @param {number} payload.messageId The id of message
		 * @param {string} payload.reaction The reaction emoji
		 * @param {object} payload.actor The user who reacted
		 *
		 */
		addActorToReaction({ token, messageId, reaction, actor }) {
			this.checkForExistence(token, messageId)

			const actors = this.reactions[token][messageId][reaction] ?? []
			// Find if actor is already in the list
			// This is needed when loading as revoking messages fully updates the list
			if (actors.some((a) => a.actorId === actor.actorId && a.actorType === actor.actorType)) {
				return
			}
			actors.push(actor)
			this.reactions[token][messageId][reaction] = actors
		},

		/**
		 * Delete all reactions for a given message.
		 *
		 * @param {string} token The conversation token
		 * @param {number} messageId The id of message
		 *
		 */
		resetReactions(token, messageId) {
			if (!this.reactions[token]?.[messageId]) {
				return
			}
			delete this.reactions[token][messageId]
		},

		/**
		 * Updates reactions for a given message.
		 *
		 * @param {object} payload action payload
		 * @param {string} payload.token The conversation token
		 * @param {number} payload.messageId The id of message
		 * @param {object} payload.reactionsDetails The list of reactions with details for a given message
		 *
		 */
		updateReactions({ token, messageId, reactionsDetails }) {
			this.checkForExistence(token, messageId)

			if (Object.keys(reactionsDetails).length === 0) {
				this.resetReactions(token, messageId)
				return
			}

			const storedReactions = this.reactions[token][messageId]

			if (Object.keys(storedReactions).length === 0) {
				this.reactions[token][messageId] = reactionsDetails
				return
			}

			// Handle removed reactions
			const removedReactions = Object.keys(storedReactions).filter((reaction) => {
				return !reactionsDetails[reaction]
			})

			removedReactions.forEach((reaction) => {
				this.removeReaction({ token, messageId, reaction })
			})

			// Add new reactions and/or update existing ones
			Object.entries(reactionsDetails).forEach(([reaction, actors]) => {
				if (!storedReactions[reaction] || JSON.stringify(actors) !== JSON.stringify(storedReactions[reaction])) {
					this.addReaction({ token, messageId, reaction, actors })
				}
			})
		},

		/**
		 * Process a reaction system message.
		 *
		 * @param {Token} token the conversation token
		 * @param {object} message the system message
		 */
		processReaction(token, message) {
			// 'reaction_deleted' is not handled because it is a message replacement
			// for 'reaction' when the reaction is revoked, thus it doesn't exist anymore
			if (message.systemMessage === MESSAGE.SYSTEM_TYPE.REACTION) {
				const actorObject = {
					actorDisplayName: message.actorDisplayName,
					actorId: message.actorId,
					actorType: message.actorType,
					timestamp: message.timestamp,
				}
				this.addActorToReaction({
					token,
					messageId: message.parent.id,
					reaction: message.message,
					actor: actorObject,
				})
			} else if (message.systemMessage === MESSAGE.SYSTEM_TYPE.REACTION_REVOKED) {
				this.fetchReactions(token, message.parent.id)
			}
		},

		/**
		 * Adds a single reaction to a message for the current user.
		 *
		 * @param {object} payload the context object
		 * @param {string} payload.token The conversation token
		 * @param {number} payload.messageId The id of message
		 * @param {string} payload.selectedEmoji The selected emoji
		 *
		 */
		async addReactionToMessage({ token, messageId, selectedEmoji }) {
			try {
				store.commit('addReactionToMessage', {
					token,
					messageId,
					reaction: selectedEmoji,
				})
				// The response return an array with the reaction details for this message
				const response = await addReactionToMessage(token, messageId, selectedEmoji)
				this.updateReactions({
					token,
					messageId,
					reactionsDetails: response.data.ocs.data,
				})
			} catch (error) {
				// Restore the previous state if the request fails
				store.commit('removeReactionFromMessage', {
					token,
					messageId,
					reaction: selectedEmoji,
				})
				showError(t('spreed', 'Failed to add reaction'))
			}
		},

		/**
		 * Removes a single reaction from a message for the current user.
		 *
		 * @param {object} payload the context object
		 * @param {string} payload.token The conversation token
		 * @param {number} payload.messageId The id of message
		 * @param {string} payload.selectedEmoji The selected emoji
		 *
		 */
		async removeReactionFromMessage({ token, messageId, selectedEmoji }) {
			try {
				store.commit('removeReactionFromMessage', {
					token,
					messageId,
					reaction: selectedEmoji,
				})
				// The response return an array with the reaction details for this message
				const response = await removeReactionFromMessage(token, messageId, selectedEmoji)
				this.updateReactions({
					token,
					messageId,
					reactionsDetails: response.data.ocs.data,
				})
			} catch (error) {
				// Restore the previous state if the request fails
				store.commit('addReactionToMessage', {
					token,
					messageId,
					reaction: selectedEmoji,
				})
				console.error(error)
				showError(t('spreed', 'Failed to remove reaction'))
			}
		},

		/**
		 * Gets the full reactions list for a given message.
		 *
		 * @param {string} token The conversation token
		 * @param {number} messageId The id of message
		 *
		 */
		async fetchReactions(token, messageId) {
			console.debug('getting reactions details')
			try {
				const response = await getReactionsDetails(token, messageId)
				this.updateReactions({
					token,
					messageId,
					reactionsDetails: response.data.ocs.data,
				})
				return response
			} catch (error) {
				console.debug(error)
			}
		},

	},
})
