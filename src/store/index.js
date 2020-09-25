/**
 * @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @author Marco Ambrosini <marcoambrosini@pm.me>
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

import Vue from 'vue'
import Vuex, { Store } from 'vuex'
import actorStore from './actorStore'
import callViewStore from './callViewStore'
import conversationsStore from './conversationsStore'
import fileUploadStore from './fileUploadStore'
import guestNameStore from './guestNameStore'
import messagesStore from './messagesStore'
import newGroupConversationStore from './newGroupConversationStore'
import participantsStore from './participantsStore'
import quoteReplyStore from './quoteReplyStore'
import settingsStore from './settingsStore'
import sidebarStore from './sidebarStore'
import talkHashStore from './talkHashStore'
import tokenStore from './tokenStore'
import windowVisibilityStore from './windowVisibilityStore'

Vue.use(Vuex)

const mutations = {}

export default new Store({
	modules: {
		actorStore,
		callViewStore,
		conversationsStore,
		fileUploadStore,
		guestNameStore,
		messagesStore,
		newGroupConversationStore,
		participantsStore,
		quoteReplyStore,
		settingsStore,
		sidebarStore,
		talkHashStore,
		tokenStore,
		windowVisibilityStore,
	},

	mutations,

	strict: process.env.NODE_ENV !== 'production',
})
