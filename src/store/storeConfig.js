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
import soundsStore from './soundsStore.js'
import tokenStore from './tokenStore.js'

export default {
	modules: {
		actorStore,
		audioRecorderStore,
		callViewStore,
		conversationsStore,
		fileUploadStore,
		messagesStore,
		participantsStore,
		soundsStore,
		tokenStore,
	},

	mutations: {},

	strict: process.env.NODE_ENV !== 'production',
}
