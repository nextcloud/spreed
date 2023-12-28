/**
 * @copyright Copyright (c) 2023 Maksim Sukharev <antreesy.web@gmail.com>
 *
 * @author Maksim Sukharev <antreesy.web@gmail.com>
 * @author Marco Ambrosini <marcoambrosini@icloud.com>
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
