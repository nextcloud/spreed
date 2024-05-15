/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getRequestToken } from '@nextcloud/auth'
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { generateFilePath, imagePath } from '@nextcloud/router'

import { requestRoomSelection } from './utils/requestRoomSelection.js'

import '@nextcloud/dialogs/style.css'

/**
 *
 */
function init() {
	if (!window.OCA.UnifiedSearch) {
		return
	}
	console.debug('Initializing unified search plugin-filters from talk')
	window.OCA.UnifiedSearch.registerFilterAction({
		id: 'talk-message',
		appId: 'spreed',
		label: t('spreed', 'In conversation'),
		icon: imagePath('spreed', 'app.svg'),
		callback: async () => {
			const conversation = await requestRoomSelection('spreed-unified-search-conversation-select', {
				dialogTitle: t('spreed', 'Select conversation'),
			})

			if (conversation) {
				emit('nextcloud:unified-search:add-filter', {
					id: 'talk-message',
					payload: conversation,
					filterUpdateText: t('spreed', 'Search in conversation: {conversation}', { conversation: conversation.displayName }),
					filterParams: { conversation: conversation.token }
				})
			}
		},
	})
}

// CSP config for webpack dynamic chunk loading
// eslint-disable-next-line
__webpack_nonce__ = btoa(getRequestToken())

// Correct the root of the app for chunk loading
// OC.linkTo matches the apps folders
// OC.generateUrl ensure the index.php (or not)
// We do not want the index.php since we're loading files
// eslint-disable-next-line
__webpack_public_path__ = generateFilePath('spreed', '', 'js/')

document.addEventListener('DOMContentLoaded', init)
