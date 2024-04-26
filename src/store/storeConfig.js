/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import actorStore from './actorStore.js'
import audioRecorderStore from './audioRecorderStore.js'
import callViewStore from './callViewStore.js'
import conversationsStore from './conversationsStore.js'
import fileUploadStore from './fileUploadStore.js'
import messagesStore from './messagesStore.js'
import participantsStore from './participantsStore.js'
import pollStore from './pollStore.js'
import sidebarStore from './sidebarStore.js'
import soundsStore from './soundsStore.js'
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
		participantsStore,
		sidebarStore,
		soundsStore,
		tokenStore,
		uiModeStore,
		windowVisibilityStore,
		pollStore,
	},

	mutations: {},

	strict: process.env.NODE_ENV !== 'production',
}
