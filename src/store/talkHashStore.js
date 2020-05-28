/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
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

const state = {
	initialNextcloudTalkHash: '',
	isNextcloudTalkHashDirty: false,
}

const getters = {
	isNextcloudTalkHashDirty: (state) => {
		return state.isNextcloudTalkHashDirty
	},
}

const mutations = {
	/**
	 * Set the initial NextcloudTalkHash
	 *
	 * @param {object} state current store state
	 * @param {string} hash Sha1 over some config information
	 */
	setInitialNextcloudTalkHash(state, hash) {
		state.initialNextcloudTalkHash = hash
	},

	/**
	 * Mark the NextcloudTalkHash as dirty
	 *
	 * @param {object} state current store state
	 */
	markNextcloudTalkHashDirty(state) {
		state.isNextcloudTalkHashDirty = true
	},
}

const actions = {
	/**
	 * Set the actor from the current user
	 *
	 * @param {object} context default store context;
	 * @param {string} hash Sha1 over some config information
	 */
	setNextcloudTalkHash(context, hash) {
		if (!context.state.initialNextcloudTalkHash) {
			console.debug('X-Nextcloud-Talk-Hash initialised: ', hash)
			context.commit('setInitialNextcloudTalkHash', hash)
		} else if (context.state.initialNextcloudTalkHash !== hash && !state.isNextcloudTalkHashDirty) {
			console.debug('X-Nextcloud-Talk-Hash marked dirty: ', hash)
			context.commit('markNextcloudTalkHashDirty')
		}
	},
}

export default { state, mutations, getters, actions }
