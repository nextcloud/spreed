/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'
import { requestRoomSelection } from './utils/requestRoomSelection.js'

__webpack_nonce__ = btoa(OC.requestToken)
// eslint-disable-next-line
__webpack_public_path__ = OC.linkTo('spreed', 'js/')

window.OCP.Collaboration.registerType('room', {
	action: async () => {
		const conversation = await requestRoomSelection('spreed-room-select', {})
		if (!conversation) {
			throw new Error('User cancelled resource selection')
		}
		return conversation.token
	},
	typeString: t('spreed', 'Link to a conversation'),
	typeIconClass: 'icon-talk',
})
