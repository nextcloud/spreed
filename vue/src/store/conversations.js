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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import axios from 'nextcloud-axios'
import Vue from 'vue'
import _ from 'lodash'

const state = {
	conversations: {}
}

const mutations = {
	addConversation(state, conversation) {
		Vue.set(state.conversations, conversation.token, conversation)
	}
}

const getters = {
	getConversations(state) {
		return state.conversations
	},
	getNavigations(state) {
		return state.conversations.forEach()
	}
}

const actions = {

	fetchConversations(context) {
		return axios
			.get(OC.linkToOCS('apps/spreed/api/v1', 2) + 'room')
			.then(response => {
				if (!_.isUndefined(response.data) && !_.isUndefined(response.data.ocs) && !_.isUndefined(response.data.ocs.data) && _.isArray(response.data.ocs.data)) {
					response.data.ocs.data.forEach(conversation => {
						context.commit('addConversation', conversation)
						// context.commit('addNavigation', {
						// 	id: room.token,
						// 	router: {
						// 		name: (filter.id !== 'all') ? 'activity-filter' : 'activity-base',
						// 		params: {
						// 			filter: filter.id
						// 		}
						// 	},
						// 	iconUrl: filter.icon,
						// 	text: filter.name
						// });
					})
				} else {
					console.info('data.ocs.data is undefined or not an array')
				}
			})
			.catch(() => {
				OC.Notification.showTemporary(t('spreed', 'Failed to load conversation list'))
			})
	}

}

export default { state, mutations, getters, actions }
