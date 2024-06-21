/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import { requestRoomSelection } from './utils/requestRoomSelection.js'

window.OCP.Collaboration.registerType('room', {
	action: async () => {
		const conversation = await requestRoomSelection('spreed-room-select', {
			// Even if it is used from Talk the Collections menu is
			// independently loaded, so the properties that depend
			// on the store need to be explicitly injected.
			container: window.store ? window.store.getters.getMainContainerSelector() : undefined,
		})
		if (!conversation) {
			throw new Error('User cancelled resource selection')
		}
		return conversation.token
	},
	typeString: t('spreed', 'Link to a conversation'),
	typeIconClass: 'icon-talk',
})
