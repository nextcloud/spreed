/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'

/**
 * @typedef {object} MessageAction
 * @property {Function} callback - The action callback
 * @property {string} icon - The icon of action
 * @property {string} label -The visible label of action
 */

/**
 * @typedef {object} ParticipantSearchAction
 * @property {Function} callback - The action callback
 * @property {Function} show - The action show function
 * @property {string} icon - The icon of action
 * @property {string} label -The visible label of action
 */

/**
 * @typedef {object} State
 * @property {MessageAction[]} messageActions - Message actions from other apps integrations (Deck, e.t.c).
 * @property {ParticipantSearchAction[]} participantSearchActions - Participant search actions from other apps integrations (Guests, e.t.c).
 */

/**
 * Store for other app integrations (additional actions for messages, participants, e.t.c)
 *
 * @param {string} id store name
 * @param {State} options.state store state structure
 */
export const useIntegrationsStore = defineStore('integrations', {
	state: () => ({
		messageActions: [],
		participantSearchActions: [],
	}),

	actions: {
		/**
		 * Add an action to the messageActions array
		 *
		 * @param {MessageAction} action action object
		 */
		addMessageAction(action) {
			this.messageActions.push(action)
		},
		/**
		 * Add an action to the participantSearchActions array
		 *
		 * @param {ParticipantSearchAction} action action object
		 */
		addParticipantSearchAction(action) {
			this.participantSearchActions.push(action)
		},
	},
})
