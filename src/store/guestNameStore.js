/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
import Vue from 'vue'
import { defineStore } from 'pinia'

export const useGuestNameStore = defineStore('guestNameStore', {
	state: () => ({
		guestNames: {},
	}),


	getters : {
	/**
	 * Gets the participants array
	 *
	 * @param {object} state the state object.
	 * @return {Array} the participants array (if there are participants in the store)
	 */
    getGuestName(state) {
		return (token, actorId) => {
		  if (state.guestNames[token] && state.guestNames[token][actorId]) {
			return state.guestNames[token][actorId] 
		  }
		  return t('spreed', 'Guest')
		}
	  },

	  getGuestNameWithGuestSuffix(state, getters) {
		return (token, actorId) => {
		  const displayName = getters.getGuestName(token, actorId)
		  if (displayName === t('spreed', 'Guest')) {
			return displayName 
		  }
		  return t('spreed', '{guest} (guest)', {
			guest: displayName
		  })
		}
	  }
	},

	actions : {

	/**
	 * Adds a guest name to the store
	 *
	 * @param {object} data the wrapping object;
	 * @param {boolean} data.noUpdate Only set the guest name if it was not set before
	 * @param {string} data.token the token of the conversation
	 * @param {string} data.actorId the guest
	 * @param {string} data.actorDisplayName the display name to set
	 */
		addGuestName({ noUpdate, token, actorId, actorDisplayName }) {
			if (!this.guestNames[token]) {
				Vue.set(this.guestNames, token, [])
			}
			if (!this.guestNames[token][actorId]) {
				Vue.set(this.guestNames[token], actorId, t('spreed', 'Guest'))
			} else if (noUpdate) {
				return
			}
			this.guestNames[token][actorId] = actorDisplayName
		},

	/**
	 * Add guest name of a chat message to the store
	 *
	 * @param {object} data the wrapping object;
	 * @param {string} data.token the token of the conversation
	 * @param {string} data.actorId the guest
	 * @param {string} data.actorDisplayName the display name to set
	 */
	setGuestNameIfEmpty({ token, actorId, actorDisplayName }) {
		this.addGuestName({ noUpdate: true, token, actorId, actorDisplayName })
	},

	/**
	 * Add guest name of a chat message to the store
	 *
	 * @param {object} data the wrapping object;
	 * @param {string} data.token the token of the conversation
	 * @param {string} data.actorId the guest
	 * @param {string} data.actorDisplayName the display name to set
	 */
	forceGuestName({ token, actorId, actorDisplayName }) {
		this.addGuestName({ noUpdate: false, token, actorId, actorDisplayName })
		},
	},
})
