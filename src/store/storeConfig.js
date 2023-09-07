/**
 * @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@icloud.com>
 *
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import actorStore from './actorStore.js'
import audioRecorderStore from './audioRecorderStore.js'
import breakoutRoomsStore from './breakoutRoomsStore.js'
import callViewStore from './callViewStore.js'
import conversationsStore from './conversationsStore.js'
import fileUploadStore from './fileUploadStore.js'
import integrationsStore from './integrationsStore.js'
import messagesStore from './messagesStore.js'
import newGroupConversationStore from './newGroupConversationStore.js'
import participantsStore from './participantsStore.js'
import pollStore from './pollStore.js'
import quoteReplyStore from './quoteReplyStore.js'
import reactionsStore from './reactionsStore.js'
import sharedItemStore from './sharedItemsStore.js'
import sidebarStore from './sidebarStore.js'
import soundsStore from './soundsStore.js'
import talkHashStore from './talkHashStore.js'
import tokenStore from './tokenStore.js'
import uiModeStore from './uiModeStore.js'
import windowVisibilityStore from './windowVisibilityStore.js'

export default {
	modules: {
		actorStore,
		audioRecorderStore,
		callViewStore,
		conversationsStore,
		fileUploadStore,
		messagesStore,
		newGroupConversationStore,
		participantsStore,
		quoteReplyStore,
		sidebarStore,
		soundsStore,
		talkHashStore,
		tokenStore,
		uiModeStore,
		windowVisibilityStore,
		integrationsStore,
		reactionsStore,
		sharedItemStore,
		pollStore,
		breakoutRoomsStore,
	},

	mutations: {},

	strict: process.env.NODE_ENV !== 'production',
}
