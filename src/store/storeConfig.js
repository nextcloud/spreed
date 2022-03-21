/**
 * @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @author Marco Ambrosini <marcoambrosini@pm.me>
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

import actorStore from './actorStore'
import audioRecorderStore from './audioRecorderStore'
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
import soundsStore from './soundsStore'
import talkHashStore from './talkHashStore'
import tokenStore from './tokenStore'
import uiModeStore from './uiModeStore'
import windowVisibilityStore from './windowVisibilityStore'
import messageActionsStore from './messageActionsStore'
import reactionsStore from './reactionsStore'

export default {
	modules: {
		actorStore,
		audioRecorderStore,
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
		soundsStore,
		talkHashStore,
		tokenStore,
		uiModeStore,
		windowVisibilityStore,
		messageActionsStore,
		reactionsStore,
	},

	mutations: {},

	strict: process.env.NODE_ENV !== 'production',
}
